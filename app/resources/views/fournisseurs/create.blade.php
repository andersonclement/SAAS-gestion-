@extends('layouts.app')

@section('title', __('Nouveau fournisseur'))

@section('content')
    <h1>{{ __('Nouveau fournisseur') }}</h1>

    <div class="card" style="max-width:480px;">
        <form method="POST" action="{{ route('fournisseurs.store') }}">
            @csrf

            <div class="field">
                <label for="nom">{{ __('Nom') }}</label>
                <input id="nom" type="text" name="nom" value="{{ old('nom') }}" required autofocus>
            </div>

            <div class="field">
                <label for="contact">{{ __('Contact') }}</label>
                <input id="contact" type="text" name="contact" value="{{ old('contact') }}">
            </div>

            <div class="field">
                <label for="telephone">{{ __('Téléphone') }}</label>
                <input id="telephone" type="text" name="telephone" value="{{ old('telephone') }}">
            </div>

            <div class="field">
                <label for="adresse">{{ __('Adresse') }}</label>
                <input id="adresse" type="text" name="adresse" value="{{ old('adresse') }}">
            </div>

            <button class="btn" type="submit">{{ __('Créer le fournisseur') }}</button>
        </form>
    </div>
@endsection
