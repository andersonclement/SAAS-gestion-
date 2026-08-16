@extends('layouts.app')

@section('title', __('Nouvelle boutique'))

@section('content')
    <h1>{{ __('Nouvelle boutique') }}</h1>

    <div class="card" style="max-width:520px;">
        <form method="POST" action="{{ route('boutiques.store') }}">
            @csrf

            <div class="field">
                <label for="nom">{{ __('Nom de la boutique') }}</label>
                <input id="nom" type="text" name="nom" value="{{ old('nom') }}" required autofocus>
            </div>

            <div class="field">
                <label for="adresse">{{ __('Localisation') }}</label>
                <input id="adresse" type="text" name="adresse" value="{{ old('adresse') }}"
                       placeholder="{{ __('Quartier, ville') }}" required>
                <small style="color:#56606b;">{{ __('Elle apparaît sur les factures et distingue vos boutiques dans les alertes et les rapports.') }}</small>
            </div>

            <div class="field">
                <label for="telephone">{{ __('Téléphone') }}</label>
                <input id="telephone" type="text" name="telephone" value="{{ old('telephone') }}">
            </div>

            <h2 style="font-size:1.05rem;margin:1.75rem 0 .25rem;">{{ __('Gérant responsable') }}</h2>
            <p style="color:#56606b;font-size:.9rem;margin-top:0;">
                {{ __("Le gérant rattaché ne voit que cette boutique. Vous pourrez lui délivrer un code d'accès pour qu'il ajoute des produits ou approvisionne le stock.") }}
            </p>

            @if ($gerantsDisponibles->isNotEmpty())
                <div class="field">
                    <label for="gerant_id">{{ __('Rattacher un gérant existant') }}</label>
                    <select id="gerant_id" name="gerant_id">
                        <option value="">— {{ __('Aucun pour le moment') }} —</option>
                        @foreach ($gerantsDisponibles as $gerant)
                            <option value="{{ $gerant->id }}" @selected((string) old('gerant_id') === (string) $gerant->id)>
                                {{ $gerant->name }} ({{ $gerant->email }})
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <details class="bloc-repliable" @if (old('gerant_nom') || old('gerant_email')) open @endif>
                <summary>{{ __('Créer le compte du gérant maintenant') }}</summary>

                <div class="field">
                    <label for="gerant_nom">{{ __('Nom du gérant') }}</label>
                    <input id="gerant_nom" type="text" name="gerant_nom" value="{{ old('gerant_nom') }}">
                </div>

                <div class="field">
                    <label for="gerant_email">{{ __('Adresse e-mail du gérant') }}</label>
                    <input id="gerant_email" type="email" name="gerant_email" value="{{ old('gerant_email') }}">
                </div>

                <div class="field">
                    <label for="gerant_mot_de_passe">{{ __('Mot de passe provisoire') }}</label>
                    <input id="gerant_mot_de_passe" type="password" name="gerant_mot_de_passe" autocomplete="new-password">
                </div>

                <div class="field">
                    <label for="gerant_mot_de_passe_confirmation">{{ __('Confirmer le mot de passe') }}</label>
                    <input id="gerant_mot_de_passe_confirmation" type="password" name="gerant_mot_de_passe_confirmation" autocomplete="new-password">
                </div>
            </details>

            <button class="btn" type="submit">{{ __('Créer la boutique') }}</button>
        </form>
    </div>
@endsection
