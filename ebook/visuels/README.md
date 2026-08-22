# Visuels de chapitre

Plaques d'ouverture illustrees pour le roman "Sous ton Emprise".

## Fichiers

- `plaques-chapitres-complet.html` — Page autonome (HTML/CSS/SVG, aucune dependance externe hors Google Fonts) contenant les 31 plaques d'ouverture pour les 30 chapitres et l'epilogue du roman complet. Ouvrir dans un navigateur.

## Utilisation

1. Ouvrir le fichier HTML dans un navigateur.
2. Faire une capture d'ecran de chaque plaque individuellement (ratio 5:8, proche d'une page de livre au format 5x8 pouces).
3. Inserer l'image en tete du chapitre correspondant dans le manuscrit final (Word, InDesign, ou outil KDP).

## Version en ligne

Une version publiee est disponible ici (privee, partageable depuis le menu de partage de la page) :
https://claude.ai/code/artifact/36420c0c-ac90-4345-a767-3e971bbef6fc

## Systeme visuel

- **Palette** : noir unique assume (esthetique roman noir / dark romance) — Encre `#0A0C10`, Encre-2 `#12151C`, Papier `#EAE3D6`, Vin `#8A1F3B`, Or `#C6A15B`, Brume `#445466`
- **Typographie** : Cinzel (titres, gravure) + Cormorant Garamond italique (exergue) + Jost (labels)
- **Format** : plaque verticale façon frontispice grave, cadre fin dore, scene SVG + bloc typographique

Chaque scene est ancree dans le contenu reel du chapitre correspondant (voir `../STRUCTURE.md` et `../chapitres/`).

## Etendre a de nouveaux chapitres

Pour ajouter une plaque pour un nouveau chapitre, dupliquer un bloc `<article class="plate">...</article>` existant et adapter :
- Le `viewBox` et les formes SVG a la scene du nouveau chapitre (garder des formes simples : rects, cercles, arcs courts)
- Le contenu de `.eyebrow`, `.chapter-num`, `.chapter-title`, `.epigraph`
