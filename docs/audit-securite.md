# Audit de sécurité

Audit interne du code réalisé sur l'ensemble de l'application (espace
tenant + espace superadmin). Chaque faille confirmée a été corrigée et
couverte par un test de non-régression, référencé ci-dessous.

> **Portée et limites.** Il s'agit d'une revue de code et de configuration
> menée par le développeur, pas d'un audit externe indépendant ni d'un test
> d'intrusion. Aucun test de charge n'a été réalisé. Ces deux points restent
> ouverts (voir §5).

## 1. Failles trouvées et corrigées

### 1.1 Autorisation manquante sur les lignes de vente — *corrigé*

**Gravité : élevée** (potentiel de fuite inter-clients et de corruption de
stock).

`LigneVente` ne porte pas de colonne `tenant_id` : elle échappe donc au
scope global multi-tenant. Or les routes de retour l'exposaient par liage
de route :

```
GET  /lignes-vente/{ligne_vente}/retour
POST /lignes-vente/{ligne_vente}/retour
```

Le contrôle en place (`StoreRetourRequest`) comparait la boutique de la
ligne à `$user->boutique_id`. Un **patron** ayant `boutique_id = null`,
la comparaison était ignorée : n'importe quel patron pouvait viser
l'identifiant d'une ligne de vente appartenant à un autre client de la
plateforme.

**Vérification en conditions réelles.** Le scénario a été exécuté avant
correction. L'exploitation aboutissait à une erreur 500
(`Attempt to read property "nom" on null`) : les relations `produit`,
`lot` et `vente` étant elles-mêmes filtrées par le scope tenant, elles
revenaient nulles et la vue plantait. Aucune donnée n'était donc écrite,
et rien ne fuitait avec `APP_DEBUG=false`.

**Cette protection était accidentelle, pas délibérée** — c'est la raison
de la correction. Elle ne reposait sur aucun contrôle explicite : toute
évolution ultérieure (un chargement de relation sans scope, l'ajout d'un
champ non filtré à la vue) l'aurait transformée en fuite réelle, sans
qu'aucun test ne le signale.

**Correction.** `RetourPolicy::create` accepte désormais la ligne de vente
et vérifie explicitement le tenant *et* la boutique, avec appel depuis le
contrôleur et depuis le `FormRequest`. La réponse est un 403 franc au lieu
d'un plantage.

Tests : `tests/Feature/IsolationRetoursTest.php` (4 tests, dont un
vérifiant que le vendeur légitime peut toujours enregistrer son retour).

### 1.2 Vulnérabilités du framework — *corrigé*

`composer audit` remontait **3 advisories** sur `laravel/framework`
v11.55.0 :

| Gravité | Faille |
|---|---|
| Élevée | Injection CRLF dans la règle de validation `email` (CVE-2026-48019) |
| Moyenne | Confusion de chemin sur les URL signées temporaires |

L'injection CRLF touchait directement cette application : la règle `email`
est utilisée à l'inscription, à la connexion, à la création de comptes et
à la réinitialisation de mot de passe. Une adresse contenant des retours
chariot pouvait passer la validation, être stockée, puis servir
d'en-tête lors d'un envoi d'e-mail.

**Correction.** Montée de `laravel/framework` en v12.66.0. `composer audit`
renvoie désormais *No security vulnerability advisories found*. Les 163
tests passent et l'ensemble des écrans a été revalidé dans un navigateur.

### 1.3 Détournement du lien de réinitialisation via l'en-tête `Host` — *corrigé*

**Gravité : élevée** (prise de contrôle de compte).

Les URL absolues générées par l'application sont construites à partir de
l'en-tête `Host` de la requête. Sans liste blanche, un attaquant pouvait
demander une réinitialisation de mot de passe **pour le compte d'une
victime** en falsifiant cet en-tête : le lien reçu par la victime pointait
alors vers le domaine de l'attaquant, qui récupérait le jeton en clair dès
le clic — donc le compte.

**Vérification en conditions réelles.** Sur un serveur réel, une requête
portant `Host: attaquant.test` produisait bien des liens
`http://attaquant.test/…`. Cette faille était créée par l'ajout de la
réinitialisation de mot de passe : elle n'existait pas avant.

**Correction.** `trustHosts()` restreint les hôtes acceptés au domaine
déclaré dans `APP_URL` et à ses sous-domaines. Après correction, un `Host`
falsifié reçoit un **400**, l'hôte légitime un **200**.

> Ce contrôle ne s'active pas en environnement `local` (comportement
> Laravel). Il faut donc que `APP_ENV=production` **et** que `APP_URL`
> contienne le domaine réel — voir `deploiement-production.md`.

### 1.4 IP réelle derrière un proxy — *corrigé*

**Gravité : moyenne.**

Derrière nginx, un load-balancer ou un CDN, l'IP vue par l'application est
celle du proxy. Deux protections en dépendaient :

- le verrouillage anti-force-brute, qui aurait regroupé **tous** les
  utilisateurs sous une seule clé (un attaquant pouvait faire verrouiller
  les connexions de tout le monde, et le blocage par IP perdait son sens) ;
- le journal des connexions, qui n'aurait enregistré qu'une IP inutile.

La détection du HTTPS terminé par le proxy en dépendait aussi, et donc
l'émission de l'en-tête HSTS.

**Correction.** `trustProxies()` piloté par la variable `TRUSTED_PROXIES`.

### 1.5 Absence de limite sur l'activation d'abonnement — *corrigé*

**Gravité : faible.** Le point de terminaison `/abonnement/activer`
n'imposait aucune limite : un code d'activation valant un abonnement
payant, il était possible d'en tester un grand nombre. Un `throttle:10,1`
a été ajouté, et la génération des codes tire désormais directement dans
un alphabet non ambigu via `random_int()` — l'implémentation précédente
repliait des minuscules sur des majuscules, ce qui réduisait l'entropie
sans nécessité.

### 1.6 Politique de sécurité de contenu (CSP) — *ajouté*

Défense en profondeur contre le XSS. Les 9 gestionnaires d'évènements en
attribut (`onclick`, `onchange`, `onsubmit`) ont été convertis en
attributs `data-*` pilotés par un script externe, ce qui permet une
directive `script-src` **sans `'unsafe-inline'`** — celle qui neutralise
réellement les charges utiles XSS.

`style-src` conserve `'unsafe-inline'` : la mise en forme repose sur des
attributs `style="…"`, auxquels un nonce ne peut pas s'appliquer
(limitation de la spécification CSP). C'est une faiblesse assumée, sans
commune mesure avec l'exécution de script.

Un test (`test_no_view_reintroduces_an_inline_event_handler`) échoue si un
gestionnaire inline est réintroduit dans une vue : sans lui, la
fonctionnalité concernée casserait silencieusement, bloquée par la CSP.

## 2. Points vérifiés et jugés conformes

| Surface | Méthode | Résultat |
|---|---|---|
| Injection SQL | Revue de tous les `selectRaw` / `whereRaw` / `havingRaw` / `orderByRaw` | Aucune donnée utilisateur interpolée : toutes les expressions sont des chaînes constantes |
| XSS | Recherche de `{!! !!}` dans toutes les vues | Aucune sortie non échappée |
| Élévation de privilèges | `StoreUserRequest` | Le rôle `Patron` est explicitement exclu des rôles assignables ; `boutique_id` est contraint au tenant de l'appelant |
| Affectation de masse | `$fillable` des modèles + contrôleurs | `tenant_id` est imposé par le serveur (trait `BelongsToTenant`), jamais lu depuis la requête |
| Isolation multi-tenant | Revue des 6 liages de route de l'espace tenant | Tous les modèles liés portent le scope global, sauf `LigneVente` (voir §1.1, corrigé) |
| Séparation superadmin | Guard `admin` distinct, table `admins` séparée | Un compte tenant ne peut pas atteindre `/admin/*` ; un admin n'hérite d'aucune identité tenant |
| Cloisonnement des rôles | Politiques + `FormRequest` de chaque contrôleur | Le comptable est bien en lecture seule ; les gérants/vendeurs sont limités à leur boutique |
| CSRF | Middleware Laravel par défaut | Actif sur toutes les routes web ; tous les formulaires portent `@csrf` |
| Énumération de comptes | Réinitialisation de mot de passe | Réponse identique que l'adresse existe ou non |
| Secrets | `.gitignore` + historique git | `.env` n'a jamais été versionné |

## 3. Durcissements en place

- **Force brute** : verrouillage à 5 tentatives par couple e-mail + IP
  (60 s), sur l'espace tenant comme sur le superadmin, doublé d'un
  `throttle` par IP au niveau des routes.
- **Traçabilité** : toute tentative de connexion (réussie ou non) est
  enregistrée avec IP et appareil, consultable dans **Connexions**. Les
  actions métier sensibles alimentent le **Journal d'activité**.
- **Transport** : HTTPS forcé hors développement, HSTS, cookies `secure` +
  `httpOnly`, sessions chiffrées au repos.
- **En-têtes** : `X-Frame-Options`, `X-Content-Type-Options`,
  `Referrer-Policy`, `X-Permitted-Cross-Domain-Policies`,
  `Permissions-Policy` et `Content-Security-Policy` sur toutes les
  réponses (`tests/Feature/EnTetesSecuriteTest.php`).
- **Hôtes et proxys** : seuls le domaine d'`APP_URL` et ses sous-domaines
  sont acceptés ; l'IP réelle du client est rétablie derrière un proxy.
- **Intégrité des stocks** : verrous de ligne (`lockForUpdate`) et
  re-vérification dans la transaction, à la vente comme au transfert
  (`test_a_concurrent_sale_cannot_push_the_stock_negative`).
- **Données de démonstration** : le seeder refuse de s'exécuter en
  production (il crée des comptes dont le mot de passe est `password`).

## 4. Maintien dans le temps

- Exécuter `composer audit` à chaque déploiement **et** une fois par mois
  (une faille peut être publiée sur une version déjà installée).
- Faire tourner la suite de tests avant chaque déploiement : l'isolation
  multi-tenant et les contrôles d'autorisation y sont verrouillés par des
  tests dédiés, qui échoueront si une évolution les affaiblit.

## 5. Ce qui reste ouvert

Ces points ne peuvent pas être couverts par une revue de code :

1. **Test d'intrusion externe.** Un regard indépendant trouve des choses
   qu'un auteur ne voit pas dans son propre code.
2. **Test de charge.** Les volumes visés sont modestes, mais le
   comportement sous charge réelle — notamment la contention sur les
   verrous de stock — n'a pas été mesuré.
3. **Sauvegardes et supervision.** Décrits dans
   `deploiement-production.md`, ils relèvent de l'exploitation et doivent
   être mis en place sur le serveur avant la première donnée client.
