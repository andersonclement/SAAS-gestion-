# Mode dégradé hors-ligne (§5)

La caisse continue à encaisser quand le réseau tombe. Les ventes sont conservées
sur l'appareil et remontent automatiquement au retour de la connexion.

---

## 1. Le problème, tel qu'il se pose en boutique

Le réseau mobile n'est pas fiable partout, et une boutique ne ferme pas parce
qu'il n'y a plus de connexion. Trois exigences en découlent, et la troisième est
la plus délicate :

1. Le vendeur doit pouvoir encaisser sans réseau.
2. Aucune vente ne doit être perdue.
3. **Aucune vente ne doit être comptée deux fois.** Une réponse serveur perdue
   dans le réseau est indiscernable d'un échec : sans précaution, chaque
   nouvelle tentative crée un doublon — et un doublon de vente, c'est du stock
   décrémenté deux fois et un chiffre d'affaires faux.

---

## 2. Comment ça marche

### Côté navigateur

| Élément | Rôle |
|---|---|
| `public/manifest.webmanifest` | Rend l'application installable sur l'écran d'accueil du téléphone |
| `public/sw.js` | Service worker : garde l'écran de caisse et les ressources statiques accessibles hors réseau |
| `public/js/hors-ligne.js` | Instantané du catalogue, file d'attente des ventes, synchronisation |

Le service worker sert les **pages par le réseau d'abord** : tant qu'il y a de la
connexion, l'utilisateur voit des données à jour, jamais une version en cache.
Le cache n'intervient qu'en cas de coupure, et ne conserve que l'écran de caisse.

L'instantané du catalogue (produits, prix promotionnels, stock connu) est
rafraîchi à chaque ouverture de la caisse en ligne, et stocké dans IndexedDB.

Quand le vendeur valide une vente sans réseau, elle part dans une file locale au
lieu d'être envoyée. Elle **ne quitte cette file qu'une fois l'accusé de
réception du serveur reçu** — jamais avant.

### Côté serveur

| Point d'entrée | Rôle |
|---|---|
| `GET /sync/catalogue` | Instantané vendable pour la boutique de l'utilisateur |
| `POST /sync/ventes` | Réception d'un lot de ventes encaissées hors-ligne |

Chaque vente du lot est traitée dans sa propre transaction : l'échec de l'une ne
fait pas perdre les autres.

---

## 3. Les trois décisions structurantes

### 3.1 L'idempotence repose sur un identifiant créé par le navigateur

La vente reçoit un UUID **au moment de l'encaissement**, avant tout contact avec
le serveur, stocké en base dans `ventes.uuid_client` avec une contrainte
d'unicité par tenant.

Conséquence : renvoyer dix fois la même vente ne l'enregistre qu'une fois. Le
serveur répond `deja_enregistree`, et le navigateur peut retirer la vente de sa
file en sachant qu'elle est bien passée. Si deux appareils poussent le même UUID
simultanément, c'est la contrainte d'unicité qui tranche.

C'est le seul mécanisme qui rend une synchronisation sûre sur un réseau dont on
ne peut rien présumer.

### 3.2 Le prix est celui du jour de l'encaissement, pas de la synchronisation

`ventes.encaissee_le` conserve l'heure réelle de la vente. Le serveur recalcule
les promotions à cette date. Une vente encaissée le 3 pendant une promotion et
remontée le 5 est facturée au tarif du 3.

Sans cela, une vente hors-ligne serait rattachée au mauvais jour dans les
rapports, et facturée au mauvais prix.

Le client, lui, n'envoie jamais de prix : il serait trivial de le manipuler.
Le serveur fait foi.

### 3.3 Un stock devenu insuffisant ne fait pas échouer la vente

C'est le cas qui demande un arbitrage métier, pas technique.

Le vendeur a encaissé 10 sacs sans réseau. Entre-temps, une autre boutique a été
servie depuis le même stock : il n'en reste que 6 en base à la synchronisation.

**La vente ne peut pas être refusée** — la marchandise est partie, l'argent est
encaissé. La refuser ferait disparaître une recette réelle des livres. Par
ailleurs, `stock_boutiques.quantite` est une colonne non signée : un stock
négatif est structurellement impossible.

Le système enregistre donc la vente pour ce qui a pu être alloué et consigne le
manquant dans `ecarts_synchronisation`. L'écart apparaît dans les **alertes**
jusqu'à ce qu'un gérant le traite — c'est le signal que le stock théorique
dépasse le stock réel, autrement dit qu'il y a eu vol, casse, ou erreur de
saisie quelque part.

Marquer l'écart comme traité ne corrige pas le stock : c'est un accusé de prise
en charge. La correction se fait avec l'inventaire, outil déjà en place. Seuls
le patron et le gérant de la boutique peuvent le faire — pas le vendeur, qui est
précisément à l'origine de l'écart.

---

## 4. Ce que le mode hors-ligne ne couvre pas

Volontairement, et c'est un choix à assumer :

- **Seule la caisse fonctionne hors réseau.** Achats, inventaires, transferts,
  rapports exigent une connexion. Ce sont des opérations de fond, pas des gestes
  de comptoir.
- **Le plafond de crédit n'est pas vérifié hors-ligne.** L'appareil ne connaît
  pas la dette contractée entre-temps dans une autre boutique. Une vente à
  crédit hors-ligne peut donc dépasser le plafond ; elle est enregistrée et
  visible dans les créances.
- **Le stock affiché hors-ligne est celui du dernier instantané.** Le vendeur
  est averti quand il dépasse ce stock, mais n'est pas bloqué : c'est le rayon
  qui fait foi, pas la base.
- **Pas de Background Sync API.** La synchronisation se déclenche au retour du
  réseau et à l'ouverture d'une page, pas en arrière-plan navigateur fermé. Le
  support de cette API reste inégal selon les navigateurs.

---

## 5. Confidentialité et sécurité

- Le cache du service worker contient des pages d'un utilisateur authentifié :
  il est **entièrement purgé à la déconnexion**.
- Le service worker n'est enregistré que pour un utilisateur connecté.
- La politique de sécurité de contenu autorise explicitement `worker-src`,
  `manifest-src` et `connect-src` en `'self'` — aucun assouplissement de
  `script-src`, qui reste sans `'unsafe-inline'`.
- Le jeton CSRF de la synchronisation est lu dans le cookie, non dans la page :
  une page servie depuis le cache porte un jeton périmé, alors que le cookie de
  session, lui, est à jour.
- Les règles d'autorisation sont les mêmes qu'en ligne : un vendeur ne peut
  synchroniser que pour sa propre boutique, un comptable ne peut pas
  synchroniser du tout, et l'isolation multi-tenant s'applique.

---

## 6. Vérification

### Suite PHPUnit — 18 tests dédiés

```bash
php artisan test --filter=SynchronisationHorsLigne
```

Couvre : enregistrement et décrément du stock, conservation de l'heure réelle,
idempotence sur renvoi, lot partiellement déjà synchronisé, écart de stock,
prix promotionnel à la date d'encaissement, cloisonnement par boutique et par
tenant, autorisations, vente à crédit, instantané du catalogue, résolution des
écarts.

### Test de bout en bout en navigateur

La suite PHP ne peut rien dire du service worker ni d'IndexedDB. Un test
Playwright coupe réellement le réseau et vérifie le comportement complet :

```bash
npm install
php artisan serve --port=8123 --no-reload
node tests/Navigateur/hors-ligne.mjs
```

Résultat attendu :

```
✓ connexion du vendeur
✓ service worker actif
✓ instantané du catalogue enregistré
✓ caisse accessible hors réseau
✓ bandeau « hors ligne » affiché
✓ vente mise en file locale
✓ deuxième vente mise en file
✓ file vidée après reconnexion
✓ ventes visibles côté serveur
✓ rejeu de la même vente refusé comme doublon
✓ aucune vente dupliquée
✓ aucune violation CSP ni erreur JS
```

---

## 7. Mise en production

Le service worker exige un **contexte sécurisé** : il ne s'enregistre qu'en
HTTPS (ou sur `localhost`). En HTTP simple, l'application reste pleinement
fonctionnelle, mais sans mode hors-ligne — l'enregistrement échoue en silence.

Après un déploiement qui modifie `sw.js`, `hors-ligne.js` ou l'écran de caisse,
incrémenter la constante `VERSION` dans `public/sw.js` : c'est ce qui purge les
anciens caches sur les appareils déjà installés.
