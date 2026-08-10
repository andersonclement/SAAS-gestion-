<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BonCommandeController;
use App\Http\Controllers\BoutiqueController;
use App\Http\Controllers\CategorieController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FournisseurController;
use App\Http\Controllers\InventaireController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProduitController;
use App\Http\Controllers\ReceptionController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\TransfertStockController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::put('/locale/{locale}', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('boutiques', BoutiqueController::class)->only(['index', 'create', 'store', 'show']);
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
});
