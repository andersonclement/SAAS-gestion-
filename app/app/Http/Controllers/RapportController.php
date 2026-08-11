<?php

namespace App\Http\Controllers;

use App\Models\BonCommande;
use App\Models\StockBoutique;
use App\Models\Vente;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RapportController extends Controller
{
    /**
     * Rapports exportables (§4.11) : ventes, achats, stock, au format
     * CSV — ouvrable directement dans Excel/LibreOffice, sans dépendance
     * externe. Le PDF est laissé pour une itération future.
     */
    public function index(): View
    {
        return view('rapports.index');
    }

    public function ventes(): StreamedResponse
    {
        $user = Auth::user();

        $ventes = Vente::with(['boutique', 'client', 'vendeur', 'lignes'])
            ->when($user->boutique_id, fn ($query) => $query->where('boutique_id', $user->boutique_id))
            ->orderBy('created_at')
            ->get();

        return $this->telechargerCsv('ventes.csv', [
            __('Numéro'), __('Date'), __('Boutique'), __('Client'), __('Vendeur'), __('Total'), __('Montant dû'),
        ], $ventes->map(fn (Vente $vente) => [
            $vente->numero,
            $vente->created_at->format('Y-m-d H:i'),
            $vente->boutique->nom,
            $vente->client?->nom ?? __('Client de passage'),
            $vente->vendeur->name,
            $vente->montantTotal(),
            $vente->montantDu(),
        ]));
    }

    public function achats(): StreamedResponse
    {
        $user = Auth::user();

        $bonsCommande = BonCommande::with(['boutique', 'fournisseur', 'lignes'])
            ->when($user->boutique_id, fn ($query) => $query->where('boutique_id', $user->boutique_id))
            ->orderBy('created_at')
            ->get();

        return $this->telechargerCsv('achats.csv', [
            __('Numéro'), __('Date'), __('Boutique'), __('Fournisseur'), __('Statut'), __('Montant total'),
        ], $bonsCommande->map(fn (BonCommande $bonCommande) => [
            $bonCommande->numero,
            $bonCommande->created_at->format('Y-m-d H:i'),
            $bonCommande->boutique->nom,
            $bonCommande->fournisseur->nom,
            $bonCommande->statut->label(),
            $bonCommande->montantTotal(),
        ]));
    }

    public function stock(): StreamedResponse
    {
        $user = Auth::user();

        $stocks = StockBoutique::with(['boutique', 'produit', 'lot'])
            ->when($user->boutique_id, fn ($query) => $query->where('boutique_id', $user->boutique_id))
            ->orderBy('boutique_id')
            ->get();

        return $this->telechargerCsv('stock.csv', [
            __('Boutique'), __('Produit'), __('Lot'), __('Péremption'), __('Quantité'),
        ], $stocks->map(fn (StockBoutique $stock) => [
            $stock->boutique->nom,
            $stock->produit->nom,
            $stock->lot->numero_lot,
            $stock->lot->date_peremption?->format('Y-m-d') ?? '',
            $stock->quantite,
        ]));
    }

    private function telechargerCsv(string $nomFichier, array $entetes, iterable $lignes): StreamedResponse
    {
        return response()->streamDownload(function () use ($entetes, $lignes) {
            $flux = fopen('php://output', 'w');
            fputcsv($flux, $entetes);

            foreach ($lignes as $ligne) {
                fputcsv($flux, $ligne);
            }

            fclose($flux);
        }, $nomFichier, ['Content-Type' => 'text/csv']);
    }
}
