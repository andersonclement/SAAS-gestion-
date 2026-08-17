<?php

namespace App\Http\Requests;

use App\Models\Lot;
use App\Models\Produit;
use App\Models\StockBoutique;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreApprovisionnementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', StockBoutique::class);
    }

    public function rules(): array
    {
        return [
            'boutique_id' => [
                'required',
                Rule::exists('boutiques', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'produit_id' => [
                'required',
                Rule::exists('produits', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'quantite' => ['required', 'integer', 'min:1'],
            'numero_lot' => ['required', 'string', 'max:64'],
            'date_fabrication' => ['nullable', 'date', 'before_or_equal:today'],
            // Comme à la création d'un produit : exigée seulement quand la
            // traçabilité l'impose, d'après le type du produit approvisionné.
            'date_peremption' => [
                Rule::requiredIf(fn () => $this->peremptionObligatoire()),
                'nullable',
                'date',
                'after:today',
            ],
        ];
    }

    /**
     * Le type du produit approvisionné décide, comme à sa création.
     */
    private function peremptionObligatoire(): bool
    {
        $produit = Produit::find($this->input('produit_id'));

        return $produit?->type->tracabiliteObligatoire() ?? false;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $boutiqueUtilisateur = $this->user()->boutique_id;

            if ($boutiqueUtilisateur !== null && (int) $this->input('boutique_id') !== $boutiqueUtilisateur) {
                $validator->errors()->add('boutique_id', __('Vous ne pouvez approvisionner que votre boutique.'));
            }

            $fabrication = $this->input('date_fabrication');
            $peremption = $this->input('date_peremption');

            if ($fabrication && $peremption && $fabrication >= $peremption) {
                $validator->errors()->add('date_peremption', __('La date de péremption doit suivre la date de fabrication.'));
            }

            // Un numéro de lot déjà connu désigne le même lot physique : lui
            // attribuer une autre péremption fausserait la traçabilité et les
            // alertes de tout ce qui est déjà en rayon sous ce numéro. Deux
            // lots différents doivent porter deux numéros différents.
            $lot = Lot::where('produit_id', $this->input('produit_id'))
                ->where('numero_lot', $this->input('numero_lot'))
                ->first();

            if ($lot && $peremption && $lot->date_peremption?->toDateString() !== $peremption) {
                $validator->errors()->add('numero_lot', __(
                    'Le lot :lot existe déjà avec la péremption du :date. Utilisez ce numéro et cette date, ou un autre numéro de lot.',
                    ['lot' => $lot->numero_lot, 'date' => $lot->date_peremption?->format('d/m/Y') ?? '—']
                ));
            }
        });
    }
}
