<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepenseRequest;
use App\Models\Boutique;
use App\Models\Depense;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DepenseController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Depense::class);

        $user = Auth::user();

        $depenses = Depense::with(['boutique', 'creePar'])
            ->when($user->boutique_id, fn ($query) => $query->where('boutique_id', $user->boutique_id))
            ->latest('date')
            ->get();

        return view('depenses.index', compact('depenses'));
    }

    public function create(): View
    {
        $this->authorize('create', Depense::class);

        $user = Auth::user();

        $boutiques = $user->boutique_id
            ? Boutique::whereKey($user->boutique_id)->get()
            : Boutique::orderBy('nom')->get();

        return view('depenses.create', compact('boutiques'));
    }

    public function store(StoreDepenseRequest $request): RedirectResponse
    {
        $depense = Depense::create($request->validated());

        Journal::enregistrer('depense.creee', __('Dépense de :montant FCFA enregistrée (:categorie).', [
            'montant' => number_format($depense->montant, 0, ',', ' '),
            'categorie' => $depense->categorie->label(),
        ]), $depense->boutique_id);

        return redirect()->route('depenses.index')->with('status', __('Dépense enregistrée avec succès.'));
    }
}
