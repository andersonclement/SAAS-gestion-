@extends('layouts.app')

@section('title', __('Modifier le produit'))

@section('content')
    <p><a href="{{ route('produits.show', $produit) }}">&larr; {{ $produit->nom }}</a></p>
    <h1 style="margin-top:0;">{{ __('Modifier le produit') }}</h1>

    <div class="card" style="max-width:520px;">
        <form method="POST" action="{{ route('produits.update', $produit) }}">
            @csrf
            @method('PUT')

            <div class="field">
                <label for="nom">{{ __('Nom du produit') }}</label>
                <input id="nom" type="text" name="nom" value="{{ old('nom', $produit->nom) }}" required autofocus>
            </div>

            <div class="field">
                <label for="type">{{ __('Type') }}</label>
                <select id="type" name="type" required>
                    @foreach (\App\Enums\TypeProduit::cases() as $type)
                        <option value="{{ $type->value }}" @selected(old('type', $produit->type->value) === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="categorie_id">{{ __('Catégorie') }}</label>
                <select id="categorie_id" name="categorie_id">
                    <option value="">— {{ __('Aucune') }} —</option>
                    @foreach ($categories as $categorie)
                        <option value="{{ $categorie->id }}" @selected((string) old('categorie_id', $produit->categorie_id) === (string) $categorie->id)>{{ $categorie->nom }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="unite_mesure">{{ __('Unité de mesure') }}</label>
                <select id="unite_mesure" name="unite_mesure" required>
                    @foreach (\App\Enums\UniteMesure::cases() as $unite)
                        <option value="{{ $unite->value }}" @selected(old('unite_mesure', $produit->unite_mesure->value) === $unite->value)>{{ $unite->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="code_barres">{{ __('Code-barres (optionnel)') }}</label>
                <input id="code_barres" type="text" name="code_barres" value="{{ old('code_barres', $produit->code_barres) }}">
            </div>

            <div class="field">
                <label for="prix_achat">{{ __("Prix d'achat (FCFA)") }}</label>
                <input id="prix_achat" type="number" min="0" name="prix_achat" value="{{ old('prix_achat', $produit->prix_achat) }}" required>
            </div>

            <div class="field">
                <label for="prix_vente">{{ __('Prix de vente (FCFA)') }}</label>
                <input id="prix_vente" type="number" min="0" name="prix_vente" value="{{ old('prix_vente', $produit->prix_vente) }}" required>
                <p style="color:#555;font-size:.8rem;margin:.3rem 0 0;">{{ __('Le nouveau prix ne s’applique qu’aux ventes futures ; les ventes déjà enregistrées gardent leur prix.') }}</p>
            </div>

            <div class="field">
                <label for="seuil_alerte">{{ __("Seuil d'alerte") }}</label>
                <input id="seuil_alerte" type="number" min="0" name="seuil_alerte" value="{{ old('seuil_alerte', $produit->seuil_alerte) }}" required>
            </div>

            <div class="field">
                <label>
                    <input type="checkbox" name="actif" value="1" @checked(old('actif', $produit->actif))>
                    {{ __('Produit actif (proposé à la vente)') }}
                </label>
            </div>

            <button class="btn" type="submit">{{ __('Enregistrer les modifications') }}</button>
        </form>
    </div>
@endsection
