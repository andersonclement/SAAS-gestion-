@extends('layouts.app')

@section('title', __('Nouvelle boutique'))

@section('content')
    <h1>{{ __('Nouvelle boutique') }}</h1>

    <div class="card" style="max-width:480px;">
        <form method="POST" action="{{ route('boutiques.store') }}">
            @csrf

            <div class="field">
                <label for="nom">{{ __('Nom de la boutique') }}</label>
                <input id="nom" type="text" name="nom" value="{{ old('nom') }}" required autofocus>
            </div>

            <div class="field">
                <label for="adresse">{{ __('Adresse') }}</label>
                <input id="adresse" type="text" name="adresse" value="{{ old('adresse') }}">
            </div>

            <div class="field">
                <label for="telephone">{{ __('Téléphone') }}</label>
                <input id="telephone" type="text" name="telephone" value="{{ old('telephone') }}">
            </div>

            <button class="btn" type="submit">{{ __('Créer la boutique') }}</button>
        </form>
    </div>
@endsection
