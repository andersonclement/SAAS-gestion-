<?php

namespace App\Http\Controllers;

use App\Models\JournalActivite;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class JournalController extends Controller
{
    /**
     * Journal d'activité (§4.12) : traçabilité complète pour le patron et
     * le comptable, restreinte à sa boutique (ou ses propres actions
     * hors boutique, ex. connexion) pour gérant/vendeur.
     */
    public function index(): View
    {
        $user = Auth::user();

        $activites = JournalActivite::with(['user', 'boutique'])
            ->when($user->boutique_id, fn ($query) => $query->where(fn ($q) => $q
                ->where('boutique_id', $user->boutique_id)
                ->orWhere('user_id', $user->id)))
            ->latest()
            ->paginate(50);

        return view('journal.index', compact('activites'));
    }
}
