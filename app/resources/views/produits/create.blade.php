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
                <label for="prix_vente">{{ __('Prix de vente au détail (FCFA par unité de mesure)') }}</label>
                <input id="prix_vente" type="number" min="0" name="prix_vente" value="{{ old('prix_vente') }}">
                <small style="color:#555;display:block;">
                    {{ __("Laisser vide si le produit ne se vend pas au détail : il ne sera alors vendable qu'en formats entiers (sac, bidon...), définis sur sa fiche.") }}
                </small>
            </div>

            <div class="field">
                <label for="stock_min">{{ __('Stock minimum') }}</label>
                <input id="stock_min" type="number" min="0" name="stock_min" value="{{ old('stock_min', 0) }}" required>
                <small style="color:#56606b;">{{ __("Une alerte s'affiche dès que le stock descend à ce niveau.") }}</small>
            </div>

            <div class="field">
                <label for="stock_max">{{ __('Stock maximum') }}</label>
                <input id="stock_max" type="number" min="1" name="stock_max" value="{{ old('stock_max') }}" required>
                <small style="color:#56606b;">{{ __('Quantité à ne pas dépasser en boutique ; au-delà, le produit est signalé en surstock.') }}</small>
            </div>

            <h2 style="font-size:1.05rem;margin:1.75rem 0 .25rem;">{{ __('Stock initial') }}</h2>
            <p style="color:#56606b;font-size:.9rem;margin-top:0;">
                {{ __('Le produit entre au catalogue avec sa première quantité en boutique et la date de péremption de ce lot.') }}
            </p>

            <div class="field">
                <label for="boutique_id">{{ __('Boutique') }}</label>
                <select id="boutique_id" name="boutique_id" required>
                    @if ($boutiques->count() !== 1)
                        <option value="">— {{ __('Choisir') }} —</option>
                    @endif
                    @foreach ($boutiques as $boutique)
                        <option value="{{ $boutique->id }}" @selected((string) old('boutique_id', $boutiques->count() === 1 ? $boutiques->first()->id : '') === (string) $boutique->id)>
                            {{ $boutique->nom }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="quantite_initiale">{{ __('Quantité en stock') }}</label>
                <input id="quantite_initiale" type="number" min="1" name="quantite_initiale" value="{{ old('quantite_initiale') }}" required>
            </div>

            <div class="field">
                <label for="numero_lot">{{ __('Numéro de lot') }}</label>
                <input id="numero_lot" type="text" name="numero_lot" value="{{ old('numero_lot') }}" required>
            </div>

            <div class="field">
                <label for="date_fabrication">{{ __('Date de fabrication (optionnelle)') }}</label>
                <input id="date_fabrication" type="date" name="date_fabrication" value="{{ old('date_fabrication') }}">
            </div>

            <div class="field">
                <label for="date_peremption">{{ __('Date de péremption') }}</label>
                <input id="date_peremption" type="date" name="date_peremption" value="{{ old('date_peremption') }}" required>
                <small style="color:#56606b;">{{ __("Une alerte est levée à l'approche de cette date.") }}</small>
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
