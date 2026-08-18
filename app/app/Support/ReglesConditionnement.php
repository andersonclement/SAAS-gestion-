<?php

namespace App\Support;

use App\Models\Conditionnement;
use Illuminate\Validation\Validator;

/**
 * Contrôles d'une série de formats de vente déclarés d'un seul geste — à la
 * création d'un produit ou dans une ligne de fichier importé.
 *
 * Ils ne peuvent pas s'exprimer entièrement en règles de validation : le
 * libellé doit être unique et le défaut unique **à l'intérieur du lot soumis**,
 * or aucun de ces formats n'existe encore en base au moment du contrôle.
 */
class ReglesConditionnement
{
    /**
     * Règles champ par champ, à préfixer par le nom du tableau.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function regles(string $prefixe = 'conditionnements'): array
    {
        return [
            $prefixe => ['nullable', 'array', 'max:10'],
            $prefixe.'.*.libelle' => ['required', 'string', 'max:60'],
            // Facteur 1 accepté : « le sachet » est un format comme un autre
            // quand l'unité de base est le sachet lui-même.
            $prefixe.'.*.facteur' => ['required', 'integer', 'min:1', 'max:100000'],
            $prefixe.'.*.prix_vente' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * Contrôles qui portent sur l'ensemble des formats soumis.
     *
     * @param  array<int, array<string, mixed>>  $conditionnements
     */
    public static function verifier(Validator $validator, array $conditionnements, string $prefixe = 'conditionnements'): void
    {
        $libellesVus = [];

        foreach ($conditionnements as $index => $format) {
            $libelle = trim((string) ($format['libelle'] ?? ''));
            $facteur = (int) ($format['facteur'] ?? 0);
            $prix = (int) ($format['prix_vente'] ?? 0);

            $cle = mb_strtolower($libelle);

            if ($libelle !== '' && isset($libellesVus[$cle])) {
                $validator->errors()->add($prefixe.'.'.$index.'.libelle', __(
                    'Le format « :libelle » est déjà déclaré plus haut.',
                    ['libelle' => $libelle]
                ));
            } elseif ($libelle !== '') {
                $libellesVus[$cle] = true;
            }

            if ($facteur < 1 || Conditionnement::prixCoherent($prix, $facteur)) {
                continue;
            }

            // Le prix unitaire doit tomber juste en francs entiers : c'est lui
            // que retiennent les lignes de vente, et donc tous les calculs de
            // chiffre d'affaires et de marge.
            $inferieur = intdiv($prix, $facteur) * $facteur;

            $validator->errors()->add($prefixe.'.'.$index.'.prix_vente', __(
                'Le prix du format « :libelle » doit être un multiple de :facteur pour rester exact à l\'unité (:inferieur ou :superieur, par exemple).',
                [
                    'libelle' => $libelle !== '' ? $libelle : __('sans nom'),
                    'facteur' => $facteur,
                    'inferieur' => number_format($inferieur, 0, ',', ' '),
                    'superieur' => number_format($inferieur + $facteur, 0, ',', ' '),
                ]
            ));
        }
    }

    /**
     * Retire les lignes entièrement vides : le formulaire affiche des lignes
     * en réserve, et le fichier importé a ses colonnes de format vides pour la
     * plupart des produits.
     *
     * @param  array<int|string, array<string, mixed>>  $conditionnements
     * @return array<int, array<string, mixed>>
     */
    public static function nettoyer(array $conditionnements): array
    {
        $retenus = array_filter($conditionnements, function ($format) {
            return is_array($format) && implode('', array_map(
                fn ($valeur) => trim((string) $valeur),
                array_intersect_key($format, array_flip(['libelle', 'facteur', 'prix_vente']))
            )) !== '';
        });

        return array_values($retenus);
    }
}
