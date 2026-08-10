@extends('layouts.app')

@section('title', __('Nouveau produit'))

@section('content')
    <h1>{{ __('Nouveau produit') }}</h1>

    <div class="card" style="max-width:520px;">
        <form method="POST" action="{{ route('produits.store') }}">
            @csrf

            <div class="field">
                <label for="nom">{{ __('Nom du produit') }}</label>
                <input id="nom" type="text" name="nom" value="{{ old('nom') }}" required autofocus>
            </div>

            <div class="field">
                <label for="type">{{ __('Type') }}</label>
                <select id="type" name="type" required>
                    <option value="">— {{ __('Choisir') }} —</option>
                    @foreach (\App\Enums\TypeProduit::cases() as $type)
                        <option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="categorie_id">{{ __('Catégorie') }}</label>
                <select id="categorie_id" name="categorie_id">
                    <option value="">— {{ __('Aucune') }} —</option>
                    @foreach ($categories as $categorie)
                        <option value="{{ $categorie->id }}" @selected((string) old('categorie_id') === (string) $categorie->id)>{{ $categorie->nom }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="unite_mesure">{{ __('Unité de mesure') }}</label>
                <select id="unite_mesure" name="unite_mesure" required>
                    <option value="">— {{ __('Choisir') }} —</option>
                    @foreach (\App\Enums\UniteMesure::cases() as $unite)
                        <option value="{{ $unite->value }}" @selected(old('unite_mesure') === $unite->value)>{{ $unite->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="code_barres">{{ __('Code-barres / QR code (optionnel)') }}</label>
                <input id="code_barres" type="text" name="code_barres" value="{{ old('code_barres') }}">
            </div>

            <div class="field">
                <label for="prix_achat">{{ __("Prix d'achat (FCFA)") }}</label>
                <input id="prix_achat" type="number" min="0" name="prix_achat" value="{{ old('prix_achat', 0) }}" required>
            </div>

            <div class="field">
                <label for="prix_vente">{{ __('Prix de vente (FCFA)') }}</label>
                <input id="prix_vente" type="number" min="0" name="prix_vente" value="{{ old('prix_vente', 0) }}" required>
            </div>

            <div class="field">
                <label for="seuil_alerte">{{ __("Seuil d'alerte de stock") }}</label>
                <input id="seuil_alerte" type="number" min="0" name="seuil_alerte" value="{{ old('seuil_alerte', 0) }}" required>
            </div>

            <button class="btn" type="submit">{{ __('Créer le produit') }}</button>
        </form>
    </div>

    <div class="card" style="max-width:520px;">
        <h2 style="margin-top:0;font-size:1.1rem;">{{ __('Ajouter une catégorie') }}</h2>
        <form method="POST" action="{{ route('categories.store') }}" style="display:flex;gap:.5rem;align-items:end;">
            @csrf
            <div class="field" style="flex:1;margin-bottom:0;">
                <label for="categorie_nom">{{ __('Nom') }}</label>
                <input id="categorie_nom" type="text" name="nom">
            </div>
            <button class="btn" type="submit">{{ __('Ajouter') }}</button>
        </form>
    </div>
@endsection
