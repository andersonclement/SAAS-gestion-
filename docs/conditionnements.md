# Conditionnements multiples

Vendre le même produit au sac de 50 kg **et** au kilo détail.

---

## 1. Le problème

Avant, un engrais vendu en sac et au détail exigeait deux fiches produit. Donc
deux stocks distincts pour une seule marchandise physique — et un inventaire
faux dès la première vente au détail, puisque le kilo prélevé ne sortait pas du
même compteur que le sac ouvert.

## 2. Détaillable ou non : ça se décide à la création du produit

Le champ **« Prix de vente au détail »** de la fiche produit est facultatif, et
c'est lui qui tranche :

- **Renseigné** — le produit se vend à la mesure. Un engrais à 600 F le kilo
  s'ouvre au comptoir.
- **Laissé vide** — le produit n'est **pas détaillable**. Il ne se vendra qu'en
  formats entiers.

Ce n'est pas un confort d'affichage. Beaucoup d'intrants arrivent scellés —
semences traitées, bidons de phytosanitaire — et les ouvrir au détail est soit
impossible, soit interdit. La caisse **refuse** donc la vente à la mesure dans
ce cas, côté serveur comme hors-ligne ; elle ne se contente pas de ne pas la
proposer.

Un produit sans prix au détail **et** sans format n'est vendable par aucun
chemin : sa fiche le signale explicitement plutôt que de laisser le vendeur le
découvrir au comptoir.

Cas particulier utile : un produit qui ne se vend jamais qu'en sacs entiers gagne
à être déclaré avec `unite_mesure = sac`. Le stock se compte alors en sacs, et la
question du détail ne se pose plus.

## 3. Le principe

Le stock reste **toujours** compté dans l'unité de base du produit (celle de
`produits.unite_mesure`). Un conditionnement n'est qu'un multiplicateur assorti
de son propre prix :

| Format | Contenu | Prix | Soit à l'unité |
|---|---|---|---|
| Sac de 50 kg | 50 kg | 28 000 F | 560 F |
| Détail au kilo | 1 kg | 600 F | 600 F |

Vendre 2 sacs prélève 100 kg du stock. Vendre 3 kg en prélève 3. Un seul
compteur, deux façons de compter à la caisse — et le tarif dégressif du sac
devient possible, ce qui est précisément l'intérêt commercial du format.

## 4. La contrainte de divisibilité, et pourquoi elle existe

Le prix d'un format doit être un multiple de son contenu. Un sac de 50 kg à
27 510 F est refusé : cela ferait 550,20 F le kilo, impossible à représenter en
francs entiers.

Cette contrainte n'est pas un caprice. Toute l'application calcule le chiffre
d'affaires et les marges avec `quantite * prix_unitaire` — une douzaine de
requêtes SQL brutes réparties dans le tableau de bord, le comparatif
inter-boutiques, la trésorerie, le calcul de marge et l'espace superadmin. Tant
que le prix unitaire tombe juste, **cette égalité reste vraie et aucune de ces
requêtes n'a besoin de changer**. C'est ce qui a permis d'introduire les
conditionnements sans toucher à un seul calcul existant.

La saisie propose les deux valeurs valides les plus proches quand le prix ne
tombe pas juste.

## 5. Où ça se voit

| Écran | Comportement |
|---|---|
| Fiche produit | Section « Formats de vente » : ajout, prix, format par défaut. Droits identiques au catalogue — patron, ou gérant muni d'un code d'accès. |
| Caisse | Un sélecteur de format par ligne, filtré sur le produit choisi. « Détail » vend à l'unité de base au prix catalogue. |
| Achats | Commande au format ; quantité et prix sont convertis en unités de base à l'enregistrement. |
| Facture, vente, bon de commande | « 2 × Sac de 50 kg = 100 kg ». |
| Caisse hors-ligne | Le format voyage dans la file de synchronisation et dans l'instantané du catalogue. |

## 6. Deux décisions à connaître

**Le nombre de formats n'est jamais stocké.** Une vente peut se répartir sur
plusieurs lots (FEFO) : 2 sacs de 50 kg peuvent sortir en 60 + 40 kg si le
premier lot est presque épuisé. La ligne ne correspond alors plus à un compte
entier de sacs. Le nombre est donc déduit de la quantité quand il tombe juste,
et l'affichage se rabat sinon sur la quantité brute — qui, elle, reste exacte.

**Retirer un format le désactive, sans l'effacer.** Les ventes déjà
enregistrées y font référence : le supprimer amputerait l'historique et rendrait
illisibles des factures déjà remises aux clients.

**Les promotions s'appliquent par unité de base.** Une remise de 10 % vaut le
même montant au kilo, qu'on achète au sac ou au détail — 60 F sur un prix
catalogue de 600 F, appliqués aussi bien aux 560 F du sac.

## 7. Vérification

```bash
php artisan test --filter=ConditionnementsTest
```

21 tests : ajout au catalogue, refus d'un prix non divisible, unicité du format
par défaut, droits du vendeur, désactivation sans perte d'historique, vente au
sac et au détail, stock jugé en unités de base, refus d'un format d'un autre
produit ou d'un autre tenant, promotion, affichage, achat au format, refus d'un
prix d'achat non divisible — et, pour les produits non détaillables : création
sans prix au détail, refus de la vente à la mesure en ligne comme hors-ligne,
vente au format, barème de promotion assis sur le format, signalement d'un
produit non vendable, instantané hors-ligne.

Les 258 tests préexistants passent sans modification — c'était le critère de
conception. Suite complète : 293 tests.
