<?php

namespace App\Http\Controllers;

use App\Models\Boutique;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();

        $boutiques = $user->isPatron() || $user->isComptable()
            ? Boutique::withCount('utilisateurs')->orderBy('nom')->get()
            : Boutique::withCount('utilisateurs')->whereKey($user->boutique_id)->get();

        return view('dashboard', [
            'boutiques' => $boutiques,
            'nombreBoutiques' => $boutiques->count(),
        ]);
    }
}
