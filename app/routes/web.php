<?php

use App\Http\Controllers\AbonnementController;
use App\Http\Controllers\AlerteController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BonCommandeController;
use App\Http\Controllers\BoutiqueContexteController;
use App\Http\Controllers\BoutiqueController;
use App\Http\Controllers\CategorieController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ComparatifController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepenseController;
use App\Http\Controllers\FournisseurController;
use App\Http\Controllers\InventaireController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProduitController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\RapportController;
use App\Http\Controllers\ReceptionController;
use App\Http\Controllers\ReglementController;
use App\Http\Controllers\RetourController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\TransfertStockController;
use App\Http\Controllers\TresorerieController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VenteController;
use App\Http\Controllers\VersementCaisseController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::put('/locale/{locale}', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:10,1');

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    // Filet de sécurité par IP, en complément du verrouillage par e-mail+IP
    // appliqué dans le contrôleur (App\Support\LimiteurConnexions).
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:20,1');

    Route::get('/mot-de-passe-oublie', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/mot-de-passe-oublie', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');
    Route::get('/reinitialiser-mot-de-passe/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reinitialiser-mot-de-passe', [NewPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/abonnement', [AbonnementController::class, 'index'])->name('abonnement.index');
    Route::post('/abonnement/activer', [AbonnementController::class, 'activer'])->name('abonnement.activer');
});

Route::middleware(['auth', 'abonnement.actif'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('boutiques', BoutiqueController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('/boutique-contexte', [BoutiqueContexteController::class, 'update'])->name('boutique-contexte.update');
    Route::resource('users', UserController::class)->only(['index', 'create', 'store', 'destroy']);
    Route::resource('produits', ProduitController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('/categories', [CategorieController::class, 'store'])->name('categories.store');

    Route::resource('fournisseurs', FournisseurController::class)->only(['index', 'create', 'store']);

    Route::resource('achats', BonCommandeController::class)
        ->parameters(['achats' => 'bon_commande'])
        ->only(['index', 'create', 'store', 'show']);
    Route::get('/achats/{bon_commande}/receptionner', [ReceptionController::class, 'create'])->name('achats.receptionner.create');
    Route::post('/achats/{bon_commande}/receptionner', [ReceptionController::class, 'store'])->name('achats.receptionner.store');

    Route::prefix('stock')->name('stock.')->group(function () {
        Route::get('/', [StockController::class, 'index'])->name('index');
        Route::resource('transferts', TransfertStockController::class)->only(['index', 'create', 'store']);
        Route::resource('inventaires', InventaireController::class)->only(['index', 'create', 'store', 'show']);
    });

    Route::resource('promotions', PromotionController::class)->only(['index', 'create', 'store']);

    Route::resource('clients', ClientController::class)->only(['index', 'create', 'store', 'show']);
    Route::resource('ventes', VenteController::class)->only(['index', 'create', 'store', 'show']);
    Route::get('/ventes/{vente}/facture', [VenteController::class, 'facture'])->name('ventes.facture');
    Route::post('/ventes/{vente}/reglements', [ReglementController::class, 'store'])->name('ventes.reglements.store');

    Route::get('/retours', [RetourController::class, 'index'])->name('retours.index');
    Route::get('/retours/{retour}', [RetourController::class, 'show'])->name('retours.show');
    Route::get('/lignes-vente/{ligne_vente}/retour', [RetourController::class, 'create'])->name('retours.create');
    Route::post('/lignes-vente/{ligne_vente}/retour', [RetourController::class, 'store'])->name('retours.store');

    Route::get('/tresorerie', [TresorerieController::class, 'index'])->name('tresorerie.index');
    Route::resource('depenses', DepenseController::class)->only(['index', 'create', 'store']);
    Route::resource('versements', VersementCaisseController::class)->only(['index', 'create', 'store']);

    Route::get('/alertes', [AlerteController::class, 'index'])->name('alertes.index');

    Route::get('/journal', [JournalController::class, 'index'])->name('journal.index');

    Route::get('/comparatif', [ComparatifController::class, 'index'])->name('comparatif.index');

    Route::prefix('rapports')->name('rapports.')->group(function () {
        Route::get('/', [RapportController::class, 'index'])->name('index');
        Route::get('/ventes.csv', [RapportController::class, 'ventes'])->name('ventes');
        Route::get('/achats.csv', [RapportController::class, 'achats'])->name('achats');
        Route::get('/stock.csv', [RapportController::class, 'stock'])->name('stock');
    });
});

require __DIR__.'/admin.php';
