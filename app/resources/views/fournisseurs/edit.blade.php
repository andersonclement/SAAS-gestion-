@extends('layouts.app')

@section('title', __('Modifier le fournisseur'))

@section('content')
    <p><a href="{{ route('fournisseurs.index') }}">&larr; {{ __('Fournisseurs') }}</a></p>
    <h1 style="margin-top:0;">{{ __('Modifier le fournisseur') }}</h1>

    <div class="card" style="max-width:520px;">
        <form method="POST" action="{{ route('fournisseurs.update', $fournisseur) }}">
            @csrf
            @method('PUT')

            <div class="field">
                <label for="nom">{{ __('Nom') }}</label>
                <input id="nom" type="text" name="nom" value="{{ old('nom', $fournisseur->nom) }}" required autofocus>
            </div>

            <div class="field">
                <label for="contact">{{ __('Personne de contact') }}</label>
                <input id="contact" type="text" name="contact" value="{{ old('contact', $fournisseur->contact) }}">
            </div>

            <div class="field">
                <label for="telephone">{{ __('Téléphone') }}</label>
                <input id="telephone" type="text" name="telephone" value="{{ old('telephone', $fournisseur->telephone) }}">
            </div>

            <div class="field">
                <label for="adresse">{{ __('Adresse') }}</label>
                <input id="adresse" type="text" name="adresse" value="{{ old('adresse', $fournisseur->adresse) }}">
            </div>

            <button class="btn" type="submit">{{ __('Enregistrer les modifications') }}</button>
        </form>
    </div>
@endsection
