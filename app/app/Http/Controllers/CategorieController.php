<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategorieRequest;
use App\Models\Categorie;
use Illuminate\Http\RedirectResponse;

class CategorieController extends Controller
{
    public function store(StoreCategorieRequest $request): RedirectResponse
    {
        Categorie::create($request->validated());

        return redirect()->route('produits.index')->with('status', __('Catégorie créée avec succès.'));
    }
}
