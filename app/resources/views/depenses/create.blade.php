@extends('layouts.app')

@section('title', __('Nouvelle dépense'))

@section('content')
    <h1>{{ __('Nouvelle dépense') }}</h1>

    <div class="card" style="max-width:480px;">
        <form method="POST" action="{{ route('depenses.store') }}">
            @csrf

            <div class="field">
                <label for="boutique_id">{{ __('Boutique') }}</label>
                <select id="boutique_id" name="boutique_id" required>
                    <option value="">— {{ __('Choisir') }} —</option>
                    @foreach ($boutiques as $boutique)
                        <option value="{{ $boutique->id }}" @selected((string) old('boutique_id') === (string) $boutique->id)>{{ $boutique->nom }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="categorie">{{ __('Catégorie') }}</label>
                <select id="categorie" name="categorie" required>
                    @foreach (\App\Enums\CategorieDepense::cases() as $categorie)
                        <option value="{{ $categorie->value }}" @selected(old('categorie') === $categorie->value)>{{ $categorie->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="montant">{{ __('Montant (FCFA)') }}</label>
                <input id="montant" type="number" min="1" name="montant" value="{{ old('montant') }}" required>
            </div>

            <div class="field">
                <label for="date">{{ __('Date') }}</label>
                <input id="date" type="date" name="date" value="{{ old('date', now()->toDateString()) }}" required>
            </div>

            <div class="field">
                <label for="description">{{ __('Description (optionnel)') }}</label>
                <input id="description" type="text" name="description" value="{{ old('description') }}">
            </div>

            <button class="btn" type="submit">{{ __('Enregistrer la dépense') }}</button>
        </form>
    </div>
@endsection
