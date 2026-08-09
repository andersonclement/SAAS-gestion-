# Analyse du cahier des charges — SaaS de Gestion de Stock Multi-Boutiques

Document source : `cahierdeschargesgestionstock.md`
Objectif de cette analyse : identifier les points forts, les zones à clarifier, les lacunes fonctionnelles, et proposer une feuille de route de développement (MVP → phases suivantes).

---

## 1. Synthèse

Le cahier des charges est **complet et cohérent** au niveau fonctionnel : 17 modules couvrant tout le cycle achat → stock → vente → trésorerie → reporting, avec une architecture multi-tenant explicite dès le §4.1. C'est un bon socle. Il reste cependant au niveau "besoin métier" et laisse ouvertes plusieurs décisions techniques et de priorisation qui doivent être tranchées avant le développement.

## 2. Points forts

- **Multi-tenancy explicite** (§4.1) : isolation des données par patron, hiérarchie patron → boutique → utilisateur clairement posée.
- **Domaine métier bien couvert** : suivi par lot, date de péremption, produits phytosanitaires — ce sont des contraintes réglementaires réelles, pas de simples détails.
- **Cycle complet** achat → stock → vente → trésorerie → reporting, cohérent de bout en bout.
- **Fonctionnalités différenciantes pertinentes** : vente à crédit avec échéancier (adapté au secteur agricole), comparatif inter-boutiques, prévisions saisonnières, mode dégradé hors-ligne.

## 3. Points à clarifier avant développement

| # | Sujet | Question à trancher |
|---|---|---|
| 1 | Prévisions (§4.6) | Méthode attendue ? Pour un MVP, une approche statistique simple (moyenne mobile pondérée + coefficient saisonnier mensuel) suffit ; pas besoin de ML au départ. |
| 2 | Rôle Comptable (§3) | Listé comme acteur mais absent de tous les modules fonctionnels. Quels droits précis (lecture seule sur quoi) ? |
| 3 | Mode hors-ligne (§5) | Exigence lourde (sync différée, résolution de conflits) à impact architectural majeur — mérite un chantier dédié plutôt qu'une ligne parmi les exigences non fonctionnelles. |
| 4 | Abonnement SaaS (§4.13) | Détail des plans (nb boutiques/utilisateurs, paliers de prix) et moyen de paiement (Stripe, mobile money local) non précisés. |
| 5 | Devise / localisation | Non mentionné ; le contexte (mobile money) suggère une cible africaine francophone — à confirmer pour formats monétaires/langue. |
| 6 | Matrice de permissions | Rôles définis (patron/gérant/vendeur) mais droits fins non détaillés (ex. vendeur : accès au prix d'achat ? annulation de vente ?). |
| 7 | Réglementation phytosanitaire (§6) | Mentionnée comme contrainte sans spécification (agrément vendeur obligatoire ? restrictions par produit ?) — impacte le modèle de données produit. |

## 4. Lacunes fonctionnelles identifiées

- Pas de gestion explicite de la **TVA/taxes** sur les ventes et factures.
- Pas de **conditionnements multiples** pour un même produit (ex. vente au sac de 50kg *et* au kg détaillé) — implicite en §4.2 mais non détaillé.
- Pas d'**export/sauvegarde de données** pour le patron (portabilité, conformité RGPD si applicable).
- Pas de **suivi de dette fournisseur** (symétrique aux dettes clients du §4.9), pourtant courant dans ce secteur.

## 5. Recommandations d'architecture

- **Multi-tenancy** : schéma de base de données partagé avec `tenant_id` (= patron_id) sur chaque table plutôt qu'un schéma par tenant — plus simple à opérer et suffisant pour l'isolation logique exigée en §4.1, tout en restant évolutif.
- **Modèle de données central** : `Tenant (Patron) → Boutique → Utilisateur (rôle)`, avec `Produit`, `Lot`, `Stock (par boutique)`, `Achat`, `Vente`, `Client`, `Fournisseur`, `Transaction financière` comme entités pivots.
- **Permissions** : RBAC (Role-Based Access Control) avec scope obligatoire par boutique pour gérant/vendeur, scope global pour le patron.
- **Mode hors-ligne** : à traiter comme un chantier à part (PWA + stockage local + file de synchronisation + résolution de conflits), pas comme une simple case à cocher.

## 6. Feuille de route proposée

**Phase 1 — MVP (cœur métier)**
§4.1 Comptes & boutiques · §4.2 Catalogue produits · §4.3 Achats · §4.4 Stock · §4.5 Ventes · §4.7 Tableaux de bord basiques

**Phase 2 — Pilotage financier**
§4.8 Alertes · §4.9 Ventes à crédit / dettes clients · §4.10 Trésorerie & dépenses

**Phase 3 — Valeur ajoutée**
§4.6 Prévisions · §4.11 Rapports/exports · §4.12 Audit · §4.14 à §4.17 (notifications clients, retours, promotions, comparatif) · Mode hors-ligne (§5) en dernier, vu sa complexité technique

## 7. Risques principaux

- **Mode hors-ligne** et **prévisions** sont les deux chantiers les plus risqués techniquement — à cadrer en priorité avec le porteur de projet pour éviter les dérapages de planning.
- La **réglementation phytosanitaire locale** doit être confirmée tôt car elle peut contraindre le modèle de données produit (champs obligatoires, restrictions de vente).

## 8. Prochaines étapes

1. Valider les points de clarification (section 3) avec le porteur de projet.
2. Choisir la stack technique (frontend, backend, base de données, hébergement).
3. Concevoir le modèle de données détaillé pour la Phase 1.
4. Démarrer le développement du MVP.
