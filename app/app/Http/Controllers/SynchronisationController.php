<?php

namespace App\Http\Controllers;

use App\Enums\ModePaiement;
use App\Http\Requests\SyncVentesRequest;
use App\Models\Boutique;
use App\Models\Client;
use App\Models\Conditionnement;
use App\Models\EcartSynchronisation;
use App\Models\Paiement;
use App\Models\Produit;
use App\Models\StockBoutique;
use App\Models\Vente;
use App\Services\AllocationStock;
use App\Support\Journal;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Mode dégradé hors-ligne (§5 du cahier des charges).
 *
 * La caisse doit continuer à encaisser quand le réseau tombe — coupure
 * d'électricité chez l'opérateur, boutique en zone mal couverte, forfait
 * épuisé. Deux points d'entrée :
 *
 * - `catalogue()` : instantané de ce dont le navigateur a besoin pour vendre
 *   sans serveur (produits, prix promotionnels du jour, stock disponible,
 *   clients). Rafraîchi à chaque ouverture de la caisse tant qu'il y a du
 *   réseau.
 * - `ventes()` : réception des ventes encaissées pendant la coupure.
 *
 * Le point délicat est la réconciliation. Une vente hors-ligne est un fait
 * accompli : la marchandise est partie, l'argent est encaissé. Le serveur ne
 * peut donc pas la refuser au motif que le stock a bougé entre-temps — il
 * enregistre ce qu'il peut et signale l'écart au gérant.
 */
class SynchronisationController extends Controller
{
    public function __construct(private readonly AllocationStock $allocation) {}

    /**
     * Instantané du catalogue vendable pour la boutique de l'utilisateur.
     */
    public function catalogue(): JsonResponse
    {
        $this->authorize('create', Vente::class);

        $user = Auth::user();
        $boutiqueId = $user->effectiveBoutiqueId();

        // Sans boutique de rattachement (patron en vue globale), on ne peut pas
        // constituer d'instantané de stock cohérent : la caisse hors-ligne est
        // un outil de terrain, rattaché à un point de vente.
        if (! $boutiqueId) {
            return response()->json([
                'disponible' => false,
                'motif' => __('Sélectionnez une boutique pour activer la caisse hors-ligne.'),
            ]);
        }

        $stocks = StockBoutique::where('boutique_id', $boutiqueId)
            ->selectRaw('produit_id, SUM(quantite) as quantite_totale')
            ->groupBy('produit_id')
            ->pluck('quantite_totale', 'produit_id');

        $produits = Produit::with('conditionnements')->where('actif', true)->orderBy('nom')->get()
            ->map(fn (Produit $produit) => [
                'id' => $produit->id,
                'nom' => $produit->nom,
                // Prix promotionnel du jour. Le serveur le recalculera à la
                // date réelle de l'encaissement : c'est lui qui fait foi.
                'prix' => $this->allocation->prixApresPromotion($produit, null),
                'stock' => (int) ($stocks[$produit->id] ?? 0),
                'conditionnements' => $produit->conditionnements->where('actif', true)->map(fn ($c) => [
                    'id' => $c->id,
                    'libelle' => $c->libelle,
                    'facteur' => $c->facteur,
                    'prix' => $c->prix_vente,
                ])->values(),
            ])
            ->values();

        return response()->json([
            'disponible' => true,
            'genere_le' => now()->toIso8601String(),
            'boutique' => ['id' => $boutiqueId, 'nom' => Boutique::find($boutiqueId)?->nom],
            'produits' => $produits,
            'clients' => Client::orderBy('nom')->get()
                ->map(fn (Client $client) => ['id' => $client->id, 'nom' => $client->nom])
                ->values(),
            'modes_paiement' => collect(ModePaiement::cases())
                ->map(fn ($mode) => ['valeur' => $mode->value, 'libelle' => $mode->label()])
                ->values(),
        ]);
    }

    /**
     * Enregistre un lot de ventes encaissées hors-ligne.
     *
     * Chaque vente est traitée dans sa propre transaction : l'échec de l'une
     * ne doit pas faire perdre les autres, que le navigateur considérerait
     * alors comme envoyées.
     */
    public function ventes(SyncVentesRequest $request): JsonResponse
    {
        $user = Auth::user();
        $resultats = [];

        foreach ($request->validated('ventes') as $donnees) {
            $resultats[] = $this->enregistrerUneVente($donnees, $user->boutique_id);
        }

        $enregistrees = collect($resultats)->where('statut', 'enregistree')->count();

        if ($enregistrees > 0) {
            Journal::enregistrer(
                'vente.synchronisee',
                trans_choice(
                    '{1} :nombre vente encaissée hors-ligne a été synchronisée.|[2,*] :nombre ventes encaissées hors-ligne ont été synchronisées.',
                    $enregistrees,
                    ['nombre' => $enregistrees]
                ),
                $user->effectiveBoutiqueId()
            );
        }

        return response()->json(['resultats' => $resultats]);
    }

    /**
     * @param  array<string, mixed>  $donnees
     * @return array<string, mixed>
     */
    private function enregistrerUneVente(array $donnees, ?int $boutiqueDuVendeur): array
    {
        $uuid = $donnees['uuid_client'];

        // Idempotence : le navigateur retente tant qu'il n'a pas reçu d'accusé
        // de réception, et une réponse perdue est indiscernable d'un échec.
        $existante = Vente::where('uuid_client', $uuid)->first();

        if ($existante) {
            return $this->resultat($uuid, 'deja_enregistree', $existante->numero);
        }

        // Même règle qu'en ligne : un vendeur rattaché à une boutique ne peut
        // pas encaisser pour une autre.
        if ($boutiqueDuVendeur !== null && (int) $donnees['boutique_id'] !== $boutiqueDuVendeur) {
            return $this->resultat($uuid, 'refusee', null, [], __('Vous ne pouvez vendre que depuis votre propre boutique.'));
        }

        $encaisseeLe = Carbon::parse($donnees['encaissee_le']);
        $client = ! empty($donnees['client_id']) ? Client::find($donnees['client_id']) : null;

        try {
            $resultat = DB::transaction(function () use ($donnees, $client, $encaisseeLe, $uuid) {
                $vente = Vente::create([
                    'boutique_id' => $donnees['boutique_id'],
                    'client_id' => $donnees['client_id'] ?? null,
                    'date_echeance' => $donnees['date_echeance'] ?? null,
                    'uuid_client' => $uuid,
                    'encaissee_le' => $encaisseeLe,
                ]);

                $ecarts = [];

                foreach ($donnees['lignes'] as $ligne) {
                    $demandee = (int) $ligne['quantite'];

                    $conditionnement = ! empty($ligne['conditionnement_id'])
                        ? Conditionnement::find($ligne['conditionnement_id'])
                        : null;

                    // Un format appartient à un produit précis : appliqué à un
                    // autre, il fausserait le prix et la conversion de quantité.
                    if ($conditionnement && $conditionnement->produit_id !== (int) $ligne['produit_id']) {
                        $conditionnement = null;
                    }

                    // L'écart se compte en unités de base, comme le stock.
                    $demandee = AllocationStock::quantiteBase($demandee, $conditionnement);

                    $allouee = $this->allocation->allouer(
                        $vente,
                        (int) $ligne['produit_id'],
                        (int) $ligne['quantite'],
                        (int) $donnees['boutique_id'],
                        $client,
                        $encaisseeLe,
                        tolererPenurie: true,
                        conditionnement: $conditionnement,
                    );

                    if ($allouee >= $demandee) {
                        continue;
                    }

                    EcartSynchronisation::create([
                        'boutique_id' => $donnees['boutique_id'],
                        'vente_id' => $vente->id,
                        'produit_id' => $ligne['produit_id'],
                        'quantite_demandee' => $demandee,
                        'quantite_allouee' => $allouee,
                        'quantite_manquante' => $demandee - $allouee,
                    ]);

                    $ecarts[] = [
                        'produit_id' => (int) $ligne['produit_id'],
                        'quantite_manquante' => $demandee - $allouee,
                    ];
                }

                $montantPaye = (int) $donnees['montant_paye'];

                if ($montantPaye > 0) {
                    Paiement::create([
                        'vente_id' => $vente->id,
                        'mode' => $donnees['mode_paiement'],
                        'montant' => $montantPaye,
                    ]);
                }

                return [$vente, $ecarts];
            });
        } catch (QueryException $exception) {
            // Deux onglets, ou deux appareils, ont poussé la même vente en même
            // temps : la contrainte d'unicité a tranché, l'autre a gagné.
            $deja = Vente::where('uuid_client', $uuid)->first();

            if ($deja) {
                return $this->resultat($uuid, 'deja_enregistree', $deja->numero);
            }

            throw $exception;
        }

        [$vente, $ecarts] = $resultat;

        $vente->load('lignes');

        Journal::enregistrer(
            'vente.creee',
            __('Vente :numero synchronisée depuis la caisse hors-ligne (:montant FCFA, encaissée le :date).', [
                'numero' => $vente->numero,
                'montant' => number_format($vente->montantTotal(), 0, ',', ' '),
                'date' => $encaisseeLe->format('d/m/Y H:i'),
            ]),
            $vente->boutique_id
        );

        if ($ecarts !== []) {
            Journal::enregistrer(
                'stock.ecart_synchronisation',
                __("Écart de stock à la synchronisation de la vente :numero : :lignes ligne(s) n'ont pas pu être servies intégralement.", [
                    'numero' => $vente->numero,
                    'lignes' => count($ecarts),
                ]),
                $vente->boutique_id
            );
        }

        return $this->resultat($uuid, 'enregistree', $vente->numero, $ecarts);
    }

    /**
     * @param  array<int, array<string, int>>  $ecarts
     * @return array<string, mixed>
     */
    private function resultat(string $uuid, string $statut, ?string $numero = null, array $ecarts = [], ?string $message = null): array
    {
        return array_filter([
            'uuid_client' => $uuid,
            'statut' => $statut,
            'numero' => $numero,
            'ecarts' => $ecarts,
            'message' => $message,
        ], fn ($valeur) => $valeur !== null && $valeur !== []);
    }
}
