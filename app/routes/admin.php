<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AuthenticatedSessionController;
use App\Http\Controllers\Admin\CodeActivationController;
use App\Http\Controllers\Admin\ConnexionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\JournalController;
use App\Http\Controllers\Admin\TenantController;
use Illuminate\Support\Facades\Route;

/**
 * Espace superadmin : entièrement séparé de l'espace tenant, authentifié
 * via le guard "admin" (voir config/auth.php et App\Models\Admin).
 */
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('/login', [AuthenticatedSessionController::class, 'store']);
    });

    Route::middleware('auth:admin')->group(function () {
        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
        Route::get('/tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
        Route::get('/tenants/{tenant}/journal', [TenantController::class, 'journal'])->name('tenants.journal');
        Route::get('/tenants/{tenant}/ventes', [TenantController::class, 'ventes'])->name('tenants.ventes');
        Route::post('/tenants/{tenant}/basculer-actif', [TenantController::class, 'toggleActif'])->name('tenants.toggle-actif');

        Route::get('/codes', [CodeActivationController::class, 'index'])->name('codes.index');
        Route::get('/codes/creer', [CodeActivationController::class, 'create'])->name('codes.create');
        Route::post('/codes', [CodeActivationController::class, 'store'])->name('codes.store');
        Route::post('/codes/{code}/revoquer', [CodeActivationController::class, 'revoquer'])->name('codes.revoquer');

        Route::get('/journal', [JournalController::class, 'index'])->name('journal.index');
        Route::get('/connexions', [ConnexionController::class, 'index'])->name('connexions.index');

        Route::get('/administrateurs', [AdminController::class, 'index'])->name('admins.index');
        Route::get('/administrateurs/creer', [AdminController::class, 'create'])->name('admins.create');
        Route::post('/administrateurs', [AdminController::class, 'store'])->name('admins.store');
        Route::delete('/administrateurs/{admin}', [AdminController::class, 'destroy'])->name('admins.destroy');
    });
});
