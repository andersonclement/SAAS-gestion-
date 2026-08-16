@extends('layouts.app')

@section('title', __('Modifier la boutique'))

@section('content')
    <p><a href="{{ route('boutiques.show', $boutique) }}">&larr; {{ $boutique->nom }}</a></p>
    <h1 style="margin-top:0;">{{ __('Modifier la boutique') }}</h1>

    <div class="card" style="max-width:520px;">
        <form method="POST" action="{{ route('boutiques.update', $boutique) }}">
            @csrf
            @method('PUT')

            <div class="field">
                <label for="nom">{{ __('Nom de la boutique') }}</label>
                <input id="nom" type="text" name="nom" value="{{ old('nom', $boutique->nom) }}" required autofocus>
            </div>

            <div class="field">
                <label for="adresse">{{ __('Localisation') }}</label>
                <input id="adresse" type="text" name="adresse" value="{{ old('adresse', $boutique->adresse) }}"
                       placeholder="{{ __('Quartier, ville') }}" required>
            </div>

            <div class="field">
                <label for="telephone">{{ __('Téléphone') }}</label>
                <input id="telephone" type="text" name="telephone" value="{{ old('telephone', $boutique->telephone) }}">
            </div>

            <button class="btn" type="submit">{{ __('Enregistrer les modifications') }}</button>
        </form>
    </div>
@endsection
