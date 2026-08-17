# Centre de notifications

Les alertes ne sont plus seulement un écran à consulter : elles deviennent une
pile de travail, adressée à quelqu'un, qui reste ouverte tant que le problème
n'est pas réglé.

---

## 1. Pourquoi, alors qu'il existe déjà une page « Alertes »

La page `/alertes` répond à « qu'est-ce qui ne va pas **en ce moment** ? ». Elle
recalcule tout à chaque affichage. C'est utile, mais on ne sait jamais ce qu'on
a déjà vu, ni ce qu'on a laissé traîner depuis trois semaines.

Le centre de notifications répond à l'autre question : « qu'est-ce qu'on m'a
signalé, et que je n'ai pas traité ? ». Les deux coexistent, et c'est voulu.

## 2. Les trois règles qui font la différence

Un centre de notifications échoue toujours de la même façon : il se remplit
plus vite qu'on ne le vide, et plus personne ne l'ouvre. Trois règles
l'empêchent.

**Une situation, une notification.** Chaque alerte porte une clé qui décrit la
*situation*, pas l'instant : « rupture du produit 12 dans la boutique 3 ». Tant
qu'elle dure, la génération la reconnaît et ne crée pas de doublon — même si
elle tourne huit fois par jour.

**Un rappel si ça traîne.** Une notification lue mais dont la situation persiste
redevient non lue au bout d'un délai propre à son type : deux jours pour une
rupture, trois pour une créance en retard, quatorze pour un surstock. Le nombre
de rappels est affiché — « rappelé 3 fois » dit ce qu'une date ne dit pas.

**Une clôture automatique.** Dès qu'une situation disparaît du calcul d'alertes,
sa notification se referme seule. Un stock réapprovisionné éteint sa pastille
sans que personne ait à cliquer. La pastille ne ment donc jamais.

Corollaire assumé : **lire n'est pas résoudre.** Marquer une notification comme
lue ne la ferme pas — elle reviendra en rappel. On ne peut pas faire disparaître
un problème d'un clic.

## 3. Qui reçoit quoi

| Type | Destinataires | Rappel |
|---|---|---|
| Rupture de stock | Patron, gérant de la boutique | 2 jours |
| Produit périmé | Patron, gérant | 2 jours |
| Écart de stock à trancher | Patron, gérant | 2 jours |
| Stock au minimum | Patron, gérant | 3 jours |
| Péremption proche | Patron, gérant | 3 jours |
| Créance en retard | Patron, gérant, **comptable** | 3 jours |
| Surstock | Patron, gérant | 14 jours |
| Abonnement bientôt expiré | **Patron seul** | 1 jour |

Un utilisateur rattaché à une boutique ne reçoit que ce qui la concerne. Le
patron et le comptable, sans rattachement, voient tout le compte. **Le vendeur
ne reçoit rien** : il n'a la main ni sur les achats ni sur les prix.

## 4. Où ça se voit

**Bouton dans la barre du haut**, avec le nombre à traiter. Il vire au rouge dès
qu'il y a quelque chose, et reste visible sans dérouler le menu — un vendeur au
comptoir ne déplie pas un menu.

**Tableau de bord du gérant** : un bloc « À traiter » placé **avant** les
indicateurs. On ouvre son tableau de bord pour savoir quoi faire aujourd'hui,
pas pour contempler son chiffre d'affaires. Chaque ligne a un bouton qui marque
comme lu et ouvre directement l'écran où agir.

**Tableau de bord du patron** : le même bloc, consolidé sur ses boutiques, avec
une colonne « boutique » — sans elle, il saurait qu'il y a un problème mais pas
où.

**Page `/notifications`** : la liste complète, filtrée sur « à traiter » par
défaut, avec l'historique à un clic et un bouton « tout marquer comme lu ».

**Espace superadmin** — deux ajouts :

- Un indicateur « alertes ouvertes, tous clients » et un bloc **« Clients à
  rappeler »** sur le tableau de bord : les comptes qui accumulent des alertes
  critiques non traitées, triés par nombre de rappels ignorés. C'est un signal
  avancé de décrochage : un client qui laisse s'empiler ses ruptures arrête
  d'utiliser l'outil bien avant de ne pas renouveler son abonnement. C'est le
  moment de l'appeler.
- Une colonne « alertes non traitées » dans la liste des clients, avec le
  nombre de critiques en évidence.

## 5. Fonctionnement technique

Une commande met la pile à jour :

```bash
php artisan notifications:generer            # tous les clients
php artisan notifications:generer --tenant=3 # un seul
```

Planifiée toutes les trois heures (`routes/console.php`). Une rupture constatée
le matin n'attend pas le lendemain. La déduplication rend ces passages répétés
sans effet tant que rien ne change.

Le récapitulatif quotidien par e-mail (`alertes:envoyer`) reste en place : il
sert à ceux qui ne se connectent pas tous les jours. Les deux s'appuient sur le
même calcul d'alertes.

Une ligne par destinataire, avec une contrainte d'unicité sur
`(user_id, cle)` : c'est elle qui garantit l'absence de doublon même si deux
exécutions se chevauchent.

## 6. Vérification

```bash
php artisan test --filter=NotificationsInternesTest
```

20 tests : destinataires par rôle et par boutique, exclusion du vendeur,
comptable pour les créances, patron seul pour l'abonnement, absence de doublon,
rappel après délai, absence de rappel avant délai, clôture automatique,
réouverture d'une situation qui revient, affichage sur les deux tableaux de
bord, isolation multi-tenant, interdiction de lire la notification d'autrui,
« tout marquer comme lu », et les deux écrans superadmin.

Vérifié aussi en navigateur : connexion gérant, bouton et pastille, bloc « À
traiter » sur le tableau de bord, page de liste, et passage du compteur à zéro
après lecture.
