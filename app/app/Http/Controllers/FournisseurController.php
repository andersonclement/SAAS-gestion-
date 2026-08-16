<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFournisseurRequest;
use App\Http\Requests\UpdateFournisseurRequest;
use App\Models\Fournisseur;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FournisseurController extends Controller
{
    public function index(): View
    {
        $fournisseurs = Fournisseur::orderBy('nom')->get();

        return view('fournisseurs.index', compact('fournisseurs'));
    }

    public function create(): View
    {
        abort_unless(auth()->user()->isPatron() || auth()->user()->isGerant(), 403);

        return view('fournisseurs.create');
    }

    public function store(StoreFournisseurRequest $request): RedirectResponse
    {
        Fournisseur::create($request->validated());

        return redirect()->route('fournisseurs.index')->with('status', __('Fournisseur créé avec succès.'));
    }

    public function edit(Fournisseur $fournisseur): View
    {
        abort_unless(auth()->user()->isPatron() || auth()->user()->isGerant(), 403);

        return view('fournisseurs.edit', compact('fournisseur'));
    }

    public function update(UpdateFournisseurRequest $request, Fournisseur $fournisseur): RedirectResponse
    {
        $fournisseur->update($request->validated());

        return redirect()->route('fournisseurs.index')->with('status', __('Fournisseur mis à jour.'));
    }
}
