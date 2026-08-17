# Prévisions de réapprovisionnement (§4.6)

Répondre à la question que le gérant se pose chaque semaine : **qu'est-ce que je
commande, et combien ?**

---

## 1. La méthode

Trois étapes, volontairement simples — le gérant doit pouvoir refaire le calcul
de tête et contester le résultat.

### 1.1 Moyenne mobile pondérée sur douze semaines

Chaque semaine reçoit un poids décroissant : la semaine écoulée pèse 12, celle
d'il y a trois mois pèse 1.

```
demande_journalière = Σ(poids × ventes_semaine) / Σ(poids × 7)
```

Une baisse installée depuis un mois pèse donc plus qu'un pic isolé du trimestre
précédent — ce qui est le comportement attendu quand la clientèle change.

### 1.2 Coefficient saisonnier

Les intrants agricoles suivent le calendrier cultural, pas la moyenne annuelle.
Le coefficient compare le même mois l'an dernier à la moyenne de cette
année-là :

```
coefficient = demande_journalière_du_mois_l'an_dernier / demande_journalière_annuelle
```

Un coefficient de 1,4 signifie « ce mois-ci se vend 40 % de plus que la
moyenne » — le pic des semis, typiquement.

Deux garde-fous :

- **Sans une année complète d'historique, le coefficient reste à 1** et l'écran
  l'indique. Mieux vaut afficher « pas encore de saisonnalité » qu'un chiffre
  inventé sur trois mois de données.
- Le coefficient est **borné entre 0,5 et 2**. Un mois creux de l'an dernier
  avec deux ventes produirait sinon un rapport aberrant, et une commande
  absurde.

### 1.3 Le besoin

```
horizon = délai de livraison + couverture souhaitée
besoin  = demande_journalière × coefficient × horizon + stock_min − stock_actuel
```

Le résultat est arrondi au format de commande supérieur : on ne commande pas
54 kg à un fournisseur qui livre par sacs de 50.

## 2. Pourquoi pas d'apprentissage automatique

Sur les séries d'une boutique — courtes, bruitées, souvent trouées — un modèle
appris ne fait pas mieux qu'une moyenne pondérée bien posée. Il coûte en
revanche une dépendance, un entraînement à maintenir, et surtout
l'inexplicabilité : un gérant qui ne comprend pas d'où sort un chiffre ne
commandera pas dessus.

Si l'historique devient long et dense sur plusieurs années, une méthode de
lissage exponentiel avec tendance et saisonnalité (Holt-Winters) serait le pas
suivant naturel.

## 3. L'écran

Deux réglages, parce que ce sont les seuls que le gérant connaît vraiment :

- **Délai de livraison** du fournisseur (défaut 7 jours)
- **Couverture souhaitée** après livraison (défaut 30 jours)

Le tableau « À commander » donne, par produit : stock actuel, demande par jour,
date de rupture prévue, **date limite pour commander** (rupture moins délai
fournisseur), et quantité suggérée exprimée en formats.

Deux badges signalent ce qu'il faut savoir avant de faire confiance au chiffre :
« historique court » (moins de quatre semaines avec des ventes) et
« saison × 1,40 » quand une correction saisonnière s'applique.

Un export CSV produit la liste de commande à transmettre au fournisseur.

**Accès** : patron, gérant, comptable. Pas le vendeur — les prévisions exposent
les volumes d'achat et le rythme de vente de la boutique.

**Par boutique, jamais consolidé** : le stock se tient par point de vente, une
prévision agrégée n'aurait aucune traduction opérationnelle.

## 4. Ce que la méthode ne fait pas

- **Les retours ne sont pas déduits** de la demande. Marginaux en volume, et les
  rattacher à leur date de vente d'origine ajouterait une approximation.
- **Les ruptures passées ne sont pas corrigées.** Un produit en rupture pendant
  trois semaines affiche une demande sous-estimée : on n'a vendu que ce qu'on
  avait. C'est la limite classique de ce type de calcul, et elle mérite d'être
  connue avant de commander.
- **Les promotions passées ne sont pas neutralisées** : un mois dopé par une
  remise tire la moyenne vers le haut.
- **Les dates retenues sont celles de l'encaissement réel**, y compris pour les
  ventes hors-ligne remontées plus tard — sinon la série serait creusée un jour
  et bosselée le suivant.

## 5. Vérification

```bash
php artisan test --filter=PrevisionsTest
```

14 tests : fidélité de la moyenne sur demande régulière, effet de la
pondération sur une tendance baissière, déclenchement d'une suggestion, prise en
compte du stock de sécurité, arrondi au format, signalement d'un historique
court, absence de saisonnalité inventée, effet d'un mois de forte saison et
bornage du coefficient, produit sans vente, cloisonnement par boutique, droits
d'accès, isolation multi-tenant, export CSV.
