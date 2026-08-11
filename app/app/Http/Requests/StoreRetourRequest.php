<?php

namespace App\Http\Requests;

use App\Enums\ModeRemboursement;
use App\Enums\MotifRetour;
use App\Models\LigneVente;
use App\Models\Retour;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRetourRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Retour::class);
    }

    public function rules(): array
    {
        return [
            'quantite' => ['required', 'integer', 'min:1'],
            'motif' => ['required', Rule::enum(MotifRetour::class)],
            'remis_en_stock' => ['boolean'],
            'mode_remboursement' => ['required', Rule::enum(ModeRemboursement::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var LigneVente $ligneVente */
            $ligneVente = $this->route('ligne_vente');
            $user = $this->user();
            $boutiqueId = $ligneVente->vente->boutique_id;

            if ($user->boutique_id !== null && $user->boutique_id !== $boutiqueId) {
                $validator->errors()->add('quantite', __('Vous ne pouvez traiter un retour que pour votre propre boutique.'));

                return;
            }

            $retournable = $ligneVente->quantiteRetournable();

            if ((int) $this->input('quantite') > $retournable) {
                $validator->errors()->add('quantite', __('Quantité retournable maximale : :retournable.', ['retournable' => $retournable]));
            }
        });
    }
}
