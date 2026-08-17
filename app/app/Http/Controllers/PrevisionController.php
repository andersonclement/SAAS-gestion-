<?php

namespace App\Http\Controllers;

use App\Models\Boutique;
use App\Services\PrevisionReapprovisionnement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Prévisions de réapprovisionnement (§4.6).
 *
 * L'écran répond à « qu'est-ce que je commande cette semaine, et combien ? ».
 * Les deux réglages exposés — délai de livraison et couverture souhaitée —
 * sont les seuls paramètres que le gérant connaît vraiment : combien de temps
 * met son fournisseur, et pour combien de temps il veut être tranquille.
 */
class PrevisionController extends Controller
{
    public function __construct(private readonly PrevisionReapprovisionnement $previsions) {}

    public function index(Request $request): View
    {
        $user = Auth::user();

        // Le stock se tient par boutique : une prévision consolidée sur
        // plusieurs points de vente n'aurait aucun sens opérationnel.
        $boutiques = $user->boutique_id
            ? Boutique::whereKey($user->boutique_id)->get()
            : Boutique::orderBy('nom')->get();

        $boutiqueId = (int) ($user->effectiveBoutiqueId() ?? $request->query('boutique_id') ?? $boutiques->first()?->id);

        abort_unless($boutiques->contains('id', $boutiqueId), 404);

        [$delai, $couverture] = $this->reglages($request);

        return view('previsions.index', [
            'boutiques' => $boutiques,
            'boutiqueId' => $boutiqueId,
            'delai' => $delai,
            'couverture' => $couverture,
            'lignes' => $this->previsions->pour($boutiqueId, $delai, $couverture),
        ]);
    }

    /**
     * Export de la liste de commande, à transmettre au fournisseur.
     */
    public function export(Request $request): StreamedResponse
    {
        $user = Auth::user();

        $boutiqueId = (int) ($user->effectiveBoutiqueId() ?? $request->query('boutique_id'));

        abort_unless(Boutique::whereKey($boutiqueId)->exists(), 404);
        abort_unless($user->boutique_id === null || $user->boutique_id === $boutiqueId, 403);

        [$delai, $couverture] = $this->reglages($request);

        $lignes = $this->previsions->pour($boutiqueId, $delai, $couverture);
        $boutique = Boutique::find($boutiqueId);

        return response()->streamDownload(function () use ($lignes) {
            $sortie = fopen('php://output', 'w');

            fputcsv($sortie, [
                __('Produit'), __('Unité'), __('Stock actuel'), __('Demande par jour'),
                __('Coefficient saisonnier'), __('Jours avant rupture'), __('À commander'),
                __('Format'), __('Fiabilité'),
            ]);

            foreach ($lignes as $ligne) {
                fputcsv($sortie, [
                    $ligne['produit']->nom,
                    $ligne['produit']->unite_mesure->value,
                    $ligne['stock'],
                    $ligne['demande_journaliere'],
                    $ligne['coefficient_saisonnier'],
                    $ligne['jours_avant_rupture'] ?? '',
                    $ligne['suggestion'],
                    $ligne['conditionnement']?->libelle ?? '',
                    $ligne['fiable'] ? __('suffisante') : __('historique trop court'),
                ]);
            }

            fclose($sortie);
        }, 'previsions-'.($boutique?->nom ?? 'boutique').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function reglages(Request $request): array
    {
        return [
            max(0, min(90, (int) $request->query('delai', 7))),
            max(1, min(365, (int) $request->query('couverture', 30))),
        ];
    }
}
