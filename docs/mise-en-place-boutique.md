# Mettre une boutique en service

Ce document décrit le parcours prévu par l'application, du côté du patron
comme du côté du gérant, depuis la création d'une boutique jusqu'au moment où
le gérant la fait tourner seul.

## 1. Le patron crée la boutique

**Boutiques → Nouvelle boutique.**

- Le **nom** et la **localisation** sont obligatoires. La localisation figure
  sur les factures remises aux clients et distingue les boutiques dans les
  alertes, les rapports et le comparatif.
- Dans le même formulaire, le patron **rattache le gérant** : soit un gérant
  déjà créé et sans boutique, soit un nouveau compte créé sur place (nom,
  e-mail, mot de passe provisoire).

Le rattachement est ce qui donne au gérant l'accès à la boutique — et à elle
seule. Il ne verra jamais le stock, les ventes ni la caisse des autres.

## 2. Le patron garnit le stock

Deux gestes, selon le cas.

**Produit encore inconnu — Catalogue → Nouveau produit.** La fiche demande,
en une seule fois :

| Champ | Rôle |
|---|---|
| Stock minimum | seuil bas ; l'alerte se déclenche dès que la quantité l'atteint |
| Stock maximum | plafond ; au-delà, le produit est signalé en surstock |
| Boutique, quantité | le stock initial, physiquement présent en rayon |
| Numéro de lot | traçabilité, obligatoire pour les produits phytosanitaires |
| Date de péremption | **obligatoire** ; c'est elle qui alimente les alertes de péremption |

Aucun produit ne peut donc entrer au catalogue sans bornes de stock ni date de
péremption connue.

**Produit déjà au catalogue — Stock → Entrée de stock.** Même saisie, sans la
fiche produit : boutique, produit, quantité, lot, date de péremption. Un
numéro de lot déjà connu vient s'ajouter à l'existant plutôt que de créer un
doublon.

L'entrée de stock est **en ajout seul**. Elle ne se modifie ni ne s'annule :
un écart constaté entre le rayon et l'application se corrige par un
**inventaire**, réservé au patron.

## 3. Le patron passe la main au gérant

Par défaut, un gérant **vend** : il ne crée pas de produits et n'entre pas de
stock. Pour lui confier ces gestes, le patron lui délivre un **code d'accès**.

**Codes d'accès → Délivrer un code.** Le patron choisit :

- le **gérant** destinataire — le code est nominatif, il ne fonctionne pour
  personne d'autre et ne porte que sur la boutique de ce gérant ;
- la **portée** : ajout de produits, approvisionnement du stock, ou les deux ;
- la **durée de validité**, en heures (48 h par défaut, 30 jours au maximum) ;
- un **motif**, facultatif, pour se souvenir de la raison six mois plus tard.

L'application génère un code de la forme `ACC-XXXX-XXXX`. Le patron le
communique au gérant par SMS ou de vive voix — l'application ne l'envoie pas
elle-même.

Le gérant le saisit dans **Code d'accès** depuis son espace. Une bannière lui
rappelle alors, sur chaque écran, sous quelle délégation il travaille et
jusqu'à quand.

## 4. Le patron voit ce qui a été fait

Chaque opération réalisée sous couvert d'un code lui est rattachée. Sur la
fiche du code (**Codes d'accès → le code**), le patron lit dans l'ordre tout ce
que le gérant a fait grâce à cette délégation : produits créés, quantités
entrées, lots et dates. La colonne « Code d'accès » du **journal d'activité**
donne la même information depuis l'autre bout.

Le patron peut **révoquer** un code à tout moment. L'accès du gérant se ferme
à sa requête suivante, sans attendre l'échéance ni une reconnexion — la
validité est revérifiée à chaque appel.

Trois autres façons dont un code cesse de valoir :

- son échéance est passée ;
- le gérant le referme lui-même (« Refermer l'accès ») ;
- son compte est désactivé.

## 5. Ce que le gérant ne peut jamais faire

Même avec un code ouvert :

- il ne peut pas **modifier ou supprimer un stock déjà saisi** — seul
  l'inventaire corrige une quantité, et il est réservé au patron ;
- il ne peut pas **délivrer de code** ni consulter la liste des codes ;
- il ne peut pas **sortir de sa boutique** : approvisionner celle d'un
  collègue est refusé, code ou pas ;
- il ne peut pas **modifier les comptes**, pas même le sien.

## 6. Les alertes qui en découlent

Une fois le stock en place, l'écran **Alertes** signale en continu :

- les **ruptures** (quantité à zéro) ;
- les **stocks au minimum** (quantité ≤ stock minimum du produit) ;
- les **surstocks** (quantité > stock maximum) ;
- les **péremptions dépassées** et **proches** (30 jours) ;
- les **créances clients en retard**.

Le même récapitulatif part chaque matin par e-mail aux patrons et aux gérants
concernés — voir `deploiement-production.md`, section « Tâches planifiées ».
