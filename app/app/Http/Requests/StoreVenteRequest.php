<?php

namespace App\Http\Requests;

use App\Enums\ModePaiement;
use App\Models\Client;
use App\Models\Produit;
use App\Models\StockBoutique;
use App\Models\Vente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Vente::class);
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'boutique_id' => [
                'required',
                Rule::exists('boutiques', 'id')->where('tenant_id', $tenantId),
            ],
            'client_id' => [
                'nullable',
                Rule::exists('clients', 'id')->where('tenant_id', $tenantId),
            ],
            'mode_paiement' => ['required', Rule::enum(ModePaiement::class)],
            'montant_paye' => ['nullable', 'integer', 'min:0'],
            'date_echeance' => ['nullable', 'date', 'after:today'],
            'lignes' => ['required', 'array', 'min:1'],
            'lignes.*.produit_id' => [
                'required',
                Rule::exists('produits', 'id')->where('tenant_id', $tenantId),
            ],
            'lignes.*.quantite' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $user = $this->user();
            $boutiqueId = $this->input('boutique_id');

            if ($user->boutique_id !== null && (int) $boutiqueId !== $user->boutique_id) {
                $validator->errors()->add('boutique_id', __('Vous ne pouvez vendre que depuis votre propre boutique.'));

                return;
            }

            $totalVente = 0;

            foreach ($this->input('lignes', []) as $index => $ligne) {
                $disponible = StockBoutique::where('boutique_id', $boutiqueId)
                    ->where('produit_id', $ligne['produit_id'] ?? null)
                    ->sum('quantite');

                if ((int) ($ligne['quantite'] ?? 0) > $disponible) {
                    $validator->errors()->add(
                        "lignes.{$index}.quantite",
                        __('Stock insuffisant dans la boutique source (:disponible disponible).', ['disponible' => $disponible])
                    );

                    continue;
                }

                $produit = Produit::find($ligne['produit_id'] ?? null);
                $totalVente += $produit ? $produit->prix_vente * (int) $ligne['quantite'] : 0;
            }

            $montantPaye = $this->filled('montant_paye') ? (int) $this->input('montant_paye') : $totalVente;
            $resteAPayer = $totalVente - $montantPaye;

            if ($resteAPayer <= 0) {
                return;
            }

            if (! $this->filled('client_id')) {
                $validator->errors()->add('client_id', __('Un client doit être désigné pour une vente à crédit.'));
            }

            if (! $this->filled('date_echeance')) {
                $validator->errors()->add('date_echeance', __("La date d'échéance est obligatoire pour une vente à crédit."));
            }

            if (! $this->filled('client_id')) {
                return;
            }

            $client = Client::find($this->input('client_id'));

            if (! $client) {
                return;
            }

            if ($client->plafond_credit === null) {
                $validator->errors()->add('client_id', __("Ce client n'a pas de plafond de crédit défini."));

                return;
            }

            if ($client->soldeDette() + $resteAPayer > $client->plafond_credit) {
                $validator->errors()->add('montant_paye', __(
                    'Ce crédit dépasserait le plafond du client (:disponible FCFA disponibles).',
                    ['disponible' => $client->creditDisponible()]
                ));
            }
        });
    }
}
