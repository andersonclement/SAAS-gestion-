<?php

namespace App\Http\Controllers;

use App\Models\Boutique;
use App\Models\LigneVente;
use App\Models\NotificationInterne;
use App\Models\Produit;
use App\Models\StockBoutique;
use App\Support\CalculMarge;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Tableau de bord (§4.7 du cahier des charges) : vue consolidée pour le
     * patron/comptable, vue restreinte à sa boutique pour gérant/vendeur.
     * Indicateurs : chiffre d'affaires, marge, meilleures ventes, stock dormant.
     */
    public function index(): View
    {
        $user = Auth::user();

        $boutiques = $user->effectiveBoutiqueId()
            ? Boutique::withCount('utilisateurs')->whereKey($user->effectiveBoutiqueId())->get()
            : Boutique::withCount('utilisateurs')->orderBy('nom')->get();

        $boutiqueIds = $boutiques->pluck('id');

        $lignesVendues = LigneVente::whereHas('vente', fn ($query) => $query->whereIn('boutique_id', $boutiqueIds));

        $chiffreAffairesTotal = (clone $lignesVendues)->selectRaw('SUM(quantite * prix_unitaire) as total')->value('total') ?? 0;

        $chiffreAffairesDuMois = (clone $lignesVendues)
            ->whereHas('vente', fn ($query) => $query->where('created_at', '>=', Carbon::now()->startOfMonth()))
            ->selectRaw('SUM(quantite * prix_unitaire) as total')
            ->value('total') ?? 0;

        $margeTotale = (clone $lignesVendues)
            ->join('produits', 'ligne_ventes.produit_id', '=', 'produits.id')
            ->selectRaw(CalculMarge::expression())
            ->value('marge') ?? 0;

        $meilleuresVentes = (clone $lignesVendues)
            ->selectRaw('produit_id, SUM(quantite) as quantite_vendue, SUM(quantite * prix_unitaire) as chiffre_affaires')
            ->groupBy('produit_id')
            ->orderByDesc('quantite_vendue')
            ->with('produit')
            ->limit(5)
            ->get();

        $stockDormant = $this->produitsEnStockDormant($boutiqueIds);

        // Ce qui demande une décision aujourd'hui passe avant les indicateurs :
        // un gérant ouvre son tableau de bord pour savoir quoi faire, pas
        // seulement pour contempler son chiffre d'affaires.
        $notifications = NotificationInterne::pour($user)
            ->ouvertes()
            ->with('boutique')
            ->parUrgence()
            ->limit(6)
            ->get();

        return view('dashboard', [
            'notifications' => $notifications,
            'nombreNotificationsOuvertes' => NotificationInterne::pour($user)->ouvertes()->count(),
            'boutiques' => $boutiques,
            'nombreBoutiques' => $boutiques->count(),
            'chiffreAffairesTotal' => $chiffreAffairesTotal,
            'chiffreAffairesDuMois' => $chiffreAffairesDuMois,
            'margeTotale' => $margeTotale,
            'meilleuresVentes' => $meilleuresVentes,
            'stockDormant' => $stockDormant,
            'evolutionMensuelle' => $this->evolutionMensuelle($boutiqueIds),
            'chiffreParBoutique' => $this->chiffreParBoutique($boutiques),
        ]);
    }

    /**
     * Chiffre d'affaires des 12 derniers mois, mois courant inclus.
     *
     * L'agrégation SQL se fait à la journée (DATE() existe aussi bien sur
     * SQLite que sur MySQL) puis le regroupement par mois est fait en PHP :
     * cela évite les fonctions de date propres à chaque moteur, tout en
     * ramenant au plus 366 lignes.
     *
     * @return array<string, float> libellé de mois => chiffre d'affaires
     */
    private function evolutionMensuelle($boutiqueIds): array
    {
        $debut = Carbon::now()->startOfMonth()->subMonths(11);

        $parJour = LigneVente::query()
            ->join('ventes', 'ligne_ventes.vente_id', '=', 'ventes.id')
            ->whereIn('ventes.boutique_id', $boutiqueIds)
            ->where('ventes.created_at', '>=', $debut)
            ->selectRaw('DATE(ventes.created_at) as jour, SUM(ligne_ventes.quantite * ligne_ventes.prix_unitaire) as total')
            ->groupBy('jour')
            ->pluck('total', 'jour');

        // Les 12 mois sont initialisés à zéro : un mois sans vente doit
        // apparaître comme un creux sur la courbe, pas comme un trou.
        $mois = [];
        for ($i = 0; $i < 12; $i++) {
            $mois[$debut->copy()->addMonths($i)->format('Y-m')] = 0.0;
        }

        foreach ($parJour as $jour => $total) {
            $cle = substr((string) $jour, 0, 7);

            if (array_key_exists($cle, $mois)) {
                $mois[$cle] += (float) $total;
            }
        }

        $libelles = [];
        foreach ($mois as $cle => $total) {
            $libelles[Carbon::createFromFormat('Y-m-d', $cle.'-01')->translatedFormat('M')] = $total;
        }

        return $libelles;
    }

    /**
     * Répartition du chiffre d'affaires par boutique, pour comparer les points
     * de vente d'un coup d'œil. Sans objet quand l'utilisateur n'en voit qu'une.
     *
     * @return array<string, float> nom de boutique => chiffre d'affaires
     */
    private function chiffreParBoutique(Collection $boutiques): array
    {
        if ($boutiques->count() < 2) {
            return [];
        }

        $totaux = LigneVente::query()
            ->join('ventes', 'ligne_ventes.vente_id', '=', 'ventes.id')
            ->whereIn('ventes.boutique_id', $boutiques->pluck('id'))
            ->selectRaw('ventes.boutique_id, SUM(ligne_ventes.quantite * ligne_ventes.prix_unitaire) as total')
            ->groupBy('ventes.boutique_id')
            ->pluck('total', 'boutique_id');

        $repartition = [];
        foreach ($boutiques as $boutique) {
            $repartition[$boutique->nom] = (float) ($totaux[$boutique->id] ?? 0);
        }

        arsort($repartition);

        return $repartition;
    }

    /**
     * Produits encore en stock mais sans vente depuis 60 jours (ou jamais
     * vendus) : le "stock dormant" à écouler en priorité.
     */
    private function produitsEnStockDormant($boutiqueIds): Collection
    {
        $quantitesEnStock = StockBoutique::whereIn('boutique_id', $boutiqueIds)
            ->selectRaw('produit_id, SUM(quantite) as quantite_stock')
            ->groupBy('produit_id')
            ->havingRaw('SUM(quantite) > 0')
            ->pluck('quantite_stock', 'produit_id');

        if ($quantitesEnStock->isEmpty()) {
            return collect();
        }

        $dernieresVentes = LigneVente::whereHas('vente', fn ($query) => $query->whereIn('boutique_id', $boutiqueIds))
            ->whereIn('produit_id', $quantitesEnStock->keys())
            ->selectRaw('produit_id, MAX(ventes.created_at) as derniere_vente')
            ->join('ventes', 'ligne_ventes.vente_id', '=', 'ventes.id')
            ->groupBy('produit_id')
            ->pluck('derniere_vente', 'produit_id');

        $seuil = Carbon::now()->subDays(60);
        $produits = Produit::whereIn('id', $quantitesEnStock->keys())->get()->keyBy('id');

        return $quantitesEnStock
            ->filter(function ($quantite, $produitId) use ($dernieresVentes, $seuil) {
                $derniereVente = $dernieresVentes->get($produitId);

                return $derniereVente === null || Carbon::parse($derniereVente)->lt($seuil);
            })
            ->map(fn ($quantite, $produitId) => [
                'produit' => $produits->get($produitId),
                'quantite' => $quantite,
                'derniere_vente' => $dernieresVentes->get($produitId),
            ])
            ->values();
    }
}
