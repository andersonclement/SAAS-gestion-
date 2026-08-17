<?php

namespace App\Http\Controllers;

use App\Enums\TypeProduit;
use App\Enums\UniteMesure;
use App\Http\Requests\StoreProduitRequest;
use App\Models\Boutique;
use App\Models\Categorie;
use App\Models\Lot;
use App\Models\Produit;
use App\Models\StockBoutique;
use App\Support\Csv;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reprise d'un catalogue existant à partir d'un fichier tableur.
 *
 * Une boutique qui démarre arrive avec deux cents références. Les saisir une à
 * une au formulaire, c'est deux cents fois le même geste — et c'est là que
 * l'essai s'arrête. Le fichier reprend exactement ce que fait
 * ProduitController::store : la fiche, son premier lot et son stock en
 * boutique, en une seule opération.
 */
class ImportCatalogueController extends Controller
{
    /**
     * Colonnes du fichier, dans l'ordre du gabarit.
     */
    private const COLONNES = [
        'nom', 'type', 'unite_mesure', 'code_barres', 'categorie',
        'prix_achat', 'prix_vente', 'stock_min', 'stock_max',
        'quantite_initiale', 'numero_lot', 'date_fabrication', 'date_peremption',
    ];

    /**
     * Intitulés que l'on rencontre quand le fichier n'a pas été construit à
     * partir du gabarit. Csv::lire a déjà retiré casse, accents et ponctuation :
     * il ne reste ici que les tournures réellement différentes.
     */
    private const ALIAS = [
        'unite' => 'unite_mesure',
        'unite_de_mesure' => 'unite_mesure',
        'mesure' => 'unite_mesure',
        'code_barre' => 'code_barres',
        'code_a_barres' => 'code_barres',
        'prix_d_achat' => 'prix_achat',
        'prix_de_vente' => 'prix_vente',
        'prix_detail' => 'prix_vente',
        'prix_au_detail' => 'prix_vente',
        'stock_minimum' => 'stock_min',
        'stock_maximum' => 'stock_max',
        'quantite' => 'quantite_initiale',
        'lot' => 'numero_lot',
        'numero_de_lot' => 'numero_lot',
        'date_de_fabrication' => 'date_fabrication',
        'fabrication' => 'date_fabrication',
        'date_de_peremption' => 'date_peremption',
        'peremption' => 'date_peremption',
    ];

    public function create(): View
    {
        $this->authorize('create', Produit::class);

        $user = Auth::user();

        return view('produits.import', [
            'boutiques' => $user->boutique_id
                ? Boutique::whereKey($user->boutique_id)->get()
                : Boutique::orderBy('nom')->get(),
            'colonnes' => self::COLONNES,
        ]);
    }

    /**
     * Le gabarit tient lieu de documentation : deux lignes d'exemple valent
     * mieux qu'une notice, dont un phytosanitaire daté et un outil qui ne
     * périme pas.
     */
    public function modele(): StreamedResponse
    {
        $this->authorize('create', Produit::class);

        return Csv::telecharger('modele-catalogue.csv', self::COLONNES, [
            [
                'Glyphosate 1L', TypeProduit::ProduitPhytosanitaire->value, UniteMesure::Litre->value,
                '5901234123457', 'Herbicides', 3500, 4500, 10, 200, 60,
                'LOT-2026-04', '2026-01-10', now()->addYear()->toDateString(),
            ],
            [
                'Houe manuelle', TypeProduit::IntrantAgricole->value, UniteMesure::Unite->value,
                '', 'Outillage', 2000, 3000, 5, 50, 25, 'LOT-OUTIL-01', '', '',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Produit::class);

        $user = Auth::user();

        $formulaire = $request->validate([
            'fichier' => ['required', 'file', 'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel', 'max:2048'],
            'boutique_id' => array_filter([
                'required',
                Rule::exists('boutiques', 'id')->where('tenant_id', $user->tenant_id),
                // Un gérant ne garnit que sa propre boutique, comme au
                // formulaire (StoreProduitRequest::withValidator).
                $user->boutique_id ? Rule::in([$user->boutique_id]) : null,
            ]),
        ]);

        $lignes = Csv::lire($request->file('fichier')->getRealPath());

        if ($lignes === []) {
            return back()->with('erreurs', [__('Le fichier est vide ou ne contient que son en-tête.')]);
        }

        [$valides, $erreurs] = $this->verifier($lignes, (int) $formulaire['boutique_id'], $user->tenant_id);

        // Tout ou rien. Un import à moitié passé laisse un catalogue dont
        // personne ne sait où il s'est arrêté : mieux vaut rendre le fichier
        // avec la liste des lignes à corriger.
        if ($erreurs !== []) {
            return back()->with('erreurs', $erreurs);
        }

        $this->importer($valides, (int) $formulaire['boutique_id']);

        Journal::enregistrer('produit.importe', __(
            ':nombre produits importés depuis :fichier.',
            ['nombre' => count($valides), 'fichier' => $request->file('fichier')->getClientOriginalName()]
        ), (int) $formulaire['boutique_id']);

        return redirect()->route('produits.index')->with('status', __(
            ':nombre produits ajoutés au catalogue avec leur stock initial.',
            ['nombre' => count($valides)]
        ));
    }

    /**
     * Valide chaque ligne aux règles du formulaire, et rend d'un côté les
     * lignes retenues, de l'autre les erreurs désignées par leur numéro tel
     * que le tableur l'affiche.
     *
     * @param  array<int, array{numero: int, valeurs: array<string, string>}>  $lignes
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>}
     */
    private function verifier(array $lignes, int $boutiqueId, int $tenantId): array
    {
        $valides = [];
        $erreurs = [];
        $codesBarresVus = [];

        foreach ($lignes as $ligne) {
            $donnees = $this->normaliser($ligne['valeurs'], $boutiqueId);

            $validateur = Validator::make(
                $donnees,
                StoreProduitRequest::reglesCommunes($tenantId, $donnees) + [
                    'categorie' => ['nullable', 'string', 'max:255'],
                ]
            );

            foreach ($validateur->errors()->all() as $message) {
                $erreurs[] = __('Ligne :numero : :message', ['numero' => $ligne['numero'], 'message' => $message]);
            }

            // L'unicité du code-barres se vérifie en base, où rien n'est encore
            // écrit : deux lignes du même fichier portant le même code
            // passeraient toutes les deux, puis heurteraient l'index unique.
            $codeBarres = $donnees['code_barres'];

            if ($codeBarres !== null && isset($codesBarresVus[$codeBarres])) {
                $erreurs[] = __('Ligne :numero : le code-barres :code est déjà utilisé ligne :precedente.', [
                    'numero' => $ligne['numero'],
                    'code' => $codeBarres,
                    'precedente' => $codesBarresVus[$codeBarres],
                ]);
            } elseif ($codeBarres !== null) {
                $codesBarresVus[$codeBarres] = $ligne['numero'];
            }

            if ($validateur->fails()) {
                continue;
            }

            $valides[] = $donnees;
        }

        return [$valides, $erreurs];
    }

    /**
     * Traduit une ligne du fichier en la charge utile qu'attendrait le
     * formulaire. Les cellules vides deviennent null : le tableur écrit une
     * chaîne vide là où le formulaire n'envoie rien.
     *
     * @param  array<string, string>  $valeurs
     * @return array<string, mixed>
     */
    private function normaliser(array $valeurs, int $boutiqueId): array
    {
        foreach (self::ALIAS as $variante => $canonique) {
            if (isset($valeurs[$variante]) && ! isset($valeurs[$canonique])) {
                $valeurs[$canonique] = $valeurs[$variante];
            }
        }

        $lire = function (string $colonne) use ($valeurs): ?string {
            $valeur = trim($valeurs[$colonne] ?? '');

            return $valeur === '' ? null : $valeur;
        };

        return [
            'nom' => $lire('nom'),
            'type' => $lire('type'),
            'unite_mesure' => $lire('unite_mesure'),
            'code_barres' => $lire('code_barres'),
            'categorie' => $lire('categorie'),
            'prix_achat' => $lire('prix_achat'),
            'prix_vente' => $lire('prix_vente'),
            'stock_min' => $lire('stock_min'),
            'stock_max' => $lire('stock_max'),
            'boutique_id' => $boutiqueId,
            'quantite_initiale' => $lire('quantite_initiale'),
            'numero_lot' => $lire('numero_lot'),
            'date_fabrication' => $lire('date_fabrication'),
            'date_peremption' => $lire('date_peremption'),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $lignes
     */
    private function importer(array $lignes, int $boutiqueId): void
    {
        DB::transaction(function () use ($lignes, $boutiqueId) {
            $categories = [];

            foreach ($lignes as $donnees) {
                $categorieId = null;

                if ($donnees['categorie'] !== null) {
                    // Créée à la volée : réclamer que les catégories existent
                    // d'abord obligerait à un second import préalable.
                    $categorieId = $categories[$donnees['categorie']]
                        ??= Categorie::firstOrCreate(['nom' => $donnees['categorie']])->id;
                }

                $produit = Produit::create([
                    'categorie_id' => $categorieId,
                    'nom' => $donnees['nom'],
                    'type' => $donnees['type'],
                    'unite_mesure' => $donnees['unite_mesure'],
                    'code_barres' => $donnees['code_barres'],
                    'prix_achat' => (int) $donnees['prix_achat'],
                    // Colonne laissée vide = produit non détaillable. La
                    // ramener à 0 le mettrait en vente gratuite au comptoir.
                    'prix_vente' => $donnees['prix_vente'] !== null ? (int) $donnees['prix_vente'] : null,
                    'stock_min' => (int) $donnees['stock_min'],
                    'stock_max' => (int) $donnees['stock_max'],
                ]);

                $lot = Lot::create([
                    'produit_id' => $produit->id,
                    'numero_lot' => $donnees['numero_lot'],
                    'date_fabrication' => $donnees['date_fabrication'],
                    'date_peremption' => $donnees['date_peremption'],
                ]);

                StockBoutique::create([
                    'boutique_id' => $boutiqueId,
                    'produit_id' => $produit->id,
                    'lot_id' => $lot->id,
                    'quantite' => (int) $donnees['quantite_initiale'],
                ]);
            }
        });
    }
}
