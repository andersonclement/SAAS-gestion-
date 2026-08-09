@extends('layouts.app')

@section('title', 'Nouvelle boutique')

@section('content')
    <h1>Nouvelle boutique</h1>

    <div class="card" style="max-width:480px;">
        <form method="POST" action="{{ route('boutiques.store') }}">
            @csrf

            <div class="field">
                <label for="nom">Nom de la boutique</label>
                <input id="nom" type="text" name="nom" value="{{ old('nom') }}" required autofocus>
            </div>

            <div class="field">
                <label for="adresse">Adresse</label>
                <input id="adresse" type="text" name="adresse" value="{{ old('adresse') }}">
            </div>

            <div class="field">
                <label for="telephone">Téléphone</label>
                <input id="telephone" type="text" name="telephone" value="{{ old('telephone') }}">
            </div>

            <button class="btn" type="submit">Créer la boutique</button>
        </form>
    </div>
@endsection
