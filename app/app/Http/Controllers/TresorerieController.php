<?php

namespace App\Http\Controllers;

use App\Models\Boutique;
use App\Models\Depense;
use App\Models\LigneBonCommande;
use App\Models\LigneVente;
use App\Models\Paiement;
use App\Models\VersementCaisse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TresorerieController extends Controller
{
    /**
     * Trésorerie (§4.10 du cahier des charges) : caisse journalière par
     * boutique et bénéfice net du mois (ventes − achats − dépenses).
     */
    public function index(): View
    {
        $user = Auth::user();

        $boutiques = $user->effectiveBoutiqueId()
            ? Boutique::whereKey($user->effectiveBoutiqueId())->get()
            : Boutique::orderBy('nom')->get();

        $boutiqueIds = $boutiques->pluck('id');
        $debutMois = Carbon::now()->startOfMonth();

        $encaissementsDuJour = Paiement::whereHas('vente', fn ($query) => $query->whereIn('boutique_id', $boutiqueIds))
            ->whereDate('paiements.created_at', Carbon::today())
            ->sum('montant');

        $depensesDuJour = Depense::whereIn('boutique_id', $boutiqueIds)
            ->whereDate('date', Carbon::today())
            ->sum('montant');

        $versementsDuJour = VersementCaisse::whereIn('boutique_id', $boutiqueIds)
            ->whereDate('date', Carbon::today())
            ->sum('montant');

        $caisseDuJour = $encaissementsDuJour - $depensesDuJour - $versementsDuJour;

        $ventesDuMois = LigneVente::whereHas('vente', fn ($query) => $query
            ->whereIn('boutique_id', $boutiqueIds)
            ->where('created_at', '>=', $debutMois))
            ->selectRaw('SUM(quantite * prix_unitaire) as total')
            ->value('total') ?? 0;

        $achatsDuMois = LigneBonCommande::whereHas('bonCommande', fn ($query) => $query
            ->whereIn('boutique_id', $boutiqueIds)
            ->where('statut', 'recu')
            ->where('recu_le', '>=', $debutMois))
            ->selectRaw('SUM(quantite * prix_unitaire) as total')
            ->value('total') ?? 0;

        $depensesDuMois = Depense::whereIn('boutique_id', $boutiqueIds)
            ->where('date', '>=', $debutMois)
            ->sum('montant');

        $beneficeNetDuMois = $ventesDuMois - $achatsDuMois - $depensesDuMois;

        $depensesRecentes = Depense::with(['boutique', 'creePar'])
            ->whereIn('boutique_id', $boutiqueIds)
            ->latest('date')
            ->limit(10)
            ->get();

        $versementsRecents = VersementCaisse::with(['boutique', 'remisPar'])
            ->whereIn('boutique_id', $boutiqueIds)
            ->latest('date')
            ->limit(10)
            ->get();

        return view('tresorerie.index', [
            'caisseDuJour' => $caisseDuJour,
            'encaissementsDuJour' => $encaissementsDuJour,
            'depensesDuJour' => $depensesDuJour,
            'versementsDuJour' => $versementsDuJour,
            'ventesDuMois' => $ventesDuMois,
            'achatsDuMois' => $achatsDuMois,
            'depensesDuMois' => $depensesDuMois,
            'beneficeNetDuMois' => $beneficeNetDuMois,
            'depensesRecentes' => $depensesRecentes,
            'versementsRecents' => $versementsRecents,
        ]);
    }
}
