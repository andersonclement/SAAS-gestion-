<?php

namespace App\Http\Requests;

use App\Enums\TypeProduit;
use App\Enums\UniteMesure;
use App\Models\Produit;
use App\Support\ReglesConditionnement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProduitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Produit::class);
    }

    /**
     * Le formulaire affiche des lignes de format en réserve : celles qui sont
     * restées vides ne doivent pas être validées comme des formats incomplets.
     */
    protected function prepareForValidation(): void
    {
        if (is_array($this->input('conditionnements'))) {
            $this->merge([
                'conditionnements' => ReglesConditionnement::nettoyer($this->input('conditionnements')),
            ]);
        }
    }

    public function rules(): array
    {
        return self::reglesCommunes($this->user()->tenant_id, $this->all()) + [
            'categorie_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
        ];
    }

    /**
     * Règles partagées entre la saisie manuelle et l'import CSV.
     *
     * Les deux chemins mènent au même catalogue : les faire diverger
     * reviendrait à tolérer par fichier ce qu'on refuse au formulaire, et à
     * peupler la base de fiches de qualités inégales. Seule la désignation de
     * la catégorie change (identifiant au formulaire, nom dans le fichier) et
     * reste donc à la charge de chaque appelant.
     *
     * @param  array<string, mixed>  $donnees
     * @return array<string, array<int, mixed>>
     */
    public static function reglesCommunes(int $tenantId, array $donnees): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(TypeProduit::class)],
            'unite_mesure' => ['required', Rule::enum(UniteMesure::class)],
            'code_barres' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('produits', 'code_barres')->where('tenant_id', $tenantId),
            ],
            'prix_achat' => ['required', 'integer', 'min:0'],
            // Prix au détail (à l'unité de mesure). Vide = produit non
            // détaillable : il ne se vendra qu'en formats entiers.
            'prix_vente' => ['nullable', 'integer', 'min:0'],

            // Les deux bornes sont posées dès la création : c'est sur elles que
            // reposent l'alerte de seuil bas et le repérage des surstocks.
            'stock_min' => ['required', 'integer', 'min:0'],
            'stock_max' => ['required', 'integer', 'min:1', 'gte:stock_min'],

            // Stock initial : un produit entre au catalogue parce qu'il est
            // physiquement en boutique. Le lot et sa date de péremption sont
            // donc renseignés dans le même geste — indispensable pour des
            // produits phytosanitaires.
            'boutique_id' => [
                'required',
                Rule::exists('boutiques', 'id')->where('tenant_id', $tenantId),
            ],
            'quantite_initiale' => ['required', 'integer', 'min:1'],
            'numero_lot' => ['required', 'string', 'max:64'],
            'date_fabrication' => ['nullable', 'date', 'before_or_equal:today'],

            // Obligatoire pour les produits dont la traçabilité l'impose
            // (phytosanitaires), facultative ailleurs. Exiger une date sur une
            // houe ou un arrosoir pousserait à en inventer une, et les alertes
            // de péremption se rempliraient d'échéances fictives — une alerte
            // à laquelle on ne croit plus ne sert plus à rien.
            'date_peremption' => [
                Rule::requiredIf(fn () => self::peremptionObligatoire($donnees)),
                'nullable',
                'date',
                'after:today',
            ],

            // Formats de vente déclarés dès la création : carton, sachet,
            // sac... Les définir plus tard obligerait à ouvrir la fiche produit
            // juste après l'avoir créée, et laisserait le produit invendable
            // dans l'intervalle.
            'conditionnement_par_defaut' => ['nullable', 'integer', 'min:0'],
        ] + ReglesConditionnement::regles();
    }

    /**
     * Un produit doit avoir au moins un prix : au détail, ou porté par un
     * format. Sans cela il entre au catalogue sans pouvoir être vendu, et le
     * vendeur ne le découvre qu'au comptoir, client devant lui.
     *
     * @param  array<string, mixed>  $donnees
     */
    public static function auMoinsUnPrix(array $donnees): bool
    {
        return ($donnees['prix_vente'] ?? null) !== null && trim((string) $donnees['prix_vente']) !== ''
            || ReglesConditionnement::nettoyer($donnees['conditionnements'] ?? []) !== [];
    }

    /**
     * Le type de produit décide : voir TypeProduit::tracabiliteObligatoire().
     *
     * @param  array<string, mixed>  $donnees
     */
    private static function peremptionObligatoire(array $donnees): bool
    {
        $type = TypeProduit::tryFrom((string) ($donnees['type'] ?? ''));

        return $type?->tracabiliteObligatoire() ?? false;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $boutiqueUtilisateur = $this->user()->boutique_id;

            // Un gérant n'approvisionne que sa propre boutique.
            if ($boutiqueUtilisateur !== null && (int) $this->input('boutique_id') !== $boutiqueUtilisateur) {
                $validator->errors()->add('boutique_id', __('Vous ne pouvez approvisionner que votre boutique.'));
            }

            $fabrication = $this->input('date_fabrication');
            $peremption = $this->input('date_peremption');

            if ($fabrication && $peremption && $fabrication >= $peremption) {
                $validator->errors()->add('date_peremption', __('La date de péremption doit suivre la date de fabrication.'));
            }

            ReglesConditionnement::verifier($validator, $this->input('conditionnements') ?? []);

            if (! self::auMoinsUnPrix($this->all())) {
                $validator->errors()->add('prix_vente', __(
                    'Indiquez un prix : au détail, ou en déclarant au moins un format de vente (carton, sachet, sac...).'
                ));
            }
        });
    }
}
