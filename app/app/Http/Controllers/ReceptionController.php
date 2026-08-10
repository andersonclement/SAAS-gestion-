<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReceptionRequest;
use App\Models\BonCommande;
use App\Models\Lot;
use App\Models\StockBoutique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReceptionController extends Controller
{
    public function create(BonCommande $bonCommande): View
    {
        $this->authorize('receptionner', $bonCommande);

        $bonCommande->load('lignes.produit');

        return view('achats.receptionner', compact('bonCommande'));
    }

    /**
     * Réceptionne un bon de commande : crée un lot par ligne (numéro,
     * dates de fabrication/péremption) et met à jour automatiquement le
     * stock de la boutique concernée (§4.3 du cahier des charges).
     */
    public function store(StoreReceptionRequest $request, BonCommande $bonCommande): RedirectResponse
    {
        DB::transaction(function () use ($request, $bonCommande) {
            $bonCommande->load('lignes.produit');

            foreach ($bonCommande->lignes as $ligne) {
                $donnees = $request->validated("lignes.{$ligne->id}");

                $lot = Lot::firstOrCreate(
                    ['produit_id' => $ligne->produit_id, 'numero_lot' => $donnees['numero_lot']],
                    [
                        'tenant_id' => $bonCommande->tenant_id,
                        'date_fabrication' => $donnees['date_fabrication'] ?? null,
                        'date_peremption' => $donnees['date_peremption'] ?? null,
                    ]
                );

                $ligne->update(['lot_id' => $lot->id]);

                $stock = StockBoutique::firstOrCreate(
                    [
                        'boutique_id' => $bonCommande->boutique_id,
                        'lot_id' => $lot->id,
                    ],
                    [
                        'tenant_id' => $bonCommande->tenant_id,
                        'produit_id' => $ligne->produit_id,
                        'quantite' => 0,
                    ]
                );

                $stock->increment('quantite', $ligne->quantite);
            }

            $bonCommande->update(['statut' => 'recu', 'recu_le' => now()]);
        });

        return redirect()->route('achats.show', $bonCommande)->with('status', __('Réception enregistrée, le stock a été mis à jour.'));
    }
}
