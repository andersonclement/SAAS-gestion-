<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBoutiqueRequest;
use App\Models\Boutique;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BoutiqueController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Boutique::class);

        $user = Auth::user();

        $boutiques = $user->effectiveBoutiqueId()
            ? Boutique::withCount('utilisateurs')->whereKey($user->effectiveBoutiqueId())->get()
            : Boutique::withCount('utilisateurs')->orderBy('nom')->get();

        return view('boutiques.index', compact('boutiques'));
    }

    public function create(): View
    {
        $this->authorize('create', Boutique::class);

        return view('boutiques.create');
    }

    public function store(StoreBoutiqueRequest $request): RedirectResponse
    {
        $boutique = Boutique::create($request->validated());

        Journal::enregistrer('boutique.creee', __('Boutique :nom créée.', ['nom' => $boutique->nom]), $boutique->id);

        return redirect()->route('boutiques.index')->with('status', __('Boutique créée avec succès.'));
    }

    public function show(Boutique $boutique): View
    {
        $this->authorize('view', $boutique);

        $boutique->load('utilisateurs');

        return view('boutiques.show', compact('boutique'));
    }
}
