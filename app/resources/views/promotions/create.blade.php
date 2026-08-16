@extends('layouts.app')

@section('title', __('Nouvelle promotion'))

@section('content')
    <h1>{{ __('Nouvelle promotion') }}</h1>

    <div class="card" style="max-width:480px;">
        <form method="POST" action="{{ route('promotions.store') }}">
            @csrf

            <div class="field">
                <label for="nom">{{ __('Nom de la promotion') }}</label>
                <input id="nom" type="text" name="nom" value="{{ old('nom') }}" required autofocus placeholder="{{ __('Ex. campagne de semis') }}">
            </div>

            <div class="field">
                <label for="type">{{ __('Type de remise') }}</label>
                <select id="type" name="type" required>
                    @foreach (\App\Enums\TypePromotion::cases() as $type)
                        <option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="valeur">{{ __('Valeur (% ou FCFA selon le type)') }}</label>
                <input id="valeur" type="number" min="1" name="valeur" value="{{ old('valeur') }}" required>
            </div>

            <div class="field">
                <label for="portee">{{ __('Portée') }}</label>
                <select id="portee" name="portee" required>
                    @foreach (\App\Enums\PorteePromotion::cases() as $portee)
                        <option value="{{ $portee->value }}" @selected(old('portee') === $portee->value)>{{ $portee->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field" data-portee="produit">
                <label for="produit_id">{{ __('Produit') }}</label>
                <select id="produit_id" name="produit_id">
                    <option value="">— {{ __('Choisir') }} —</option>
                    @foreach ($produits as $produit)
                        <option value="{{ $produit->id }}" @selected((string) old('produit_id') === (string) $produit->id)>{{ $produit->nom }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field" data-portee="categorie">
                <label for="categorie_id">{{ __('Catégorie') }}</label>
                <select id="categorie_id" name="categorie_id">
                    <option value="">— {{ __('Choisir') }} —</option>
                    @foreach ($categories as $categorie)
                        <option value="{{ $categorie->id }}" @selected((string) old('categorie_id') === (string) $categorie->id)>{{ $categorie->nom }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field" data-portee="client">
                <label for="client_id">{{ __('Client') }}</label>
                <select id="client_id" name="client_id">
                    <option value="">— {{ __('Choisir') }} —</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected((string) old('client_id') === (string) $client->id)>{{ $client->nom }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="date_debut">{{ __('Date de début') }}</label>
                <input id="date_debut" type="date" name="date_debut" value="{{ old('date_debut', now()->toDateString()) }}" required>
            </div>

            <div class="field">
                <label for="date_fin">{{ __('Date de fin') }}</label>
                <input id="date_fin" type="date" name="date_fin" value="{{ old('date_fin') }}" required>
            </div>

            <button class="btn" type="submit">{{ __('Créer la promotion') }}</button>
        </form>
    </div>

    <script nonce="{{ $cspNonce }}">
        (function () {
            const porteeSelect = document.getElementById('portee');
            const champsPortee = document.querySelectorAll('[data-portee]');

            function basculerChamps() {
                champsPortee.forEach(function (champ) {
                    champ.style.display = champ.dataset.portee === porteeSelect.value ? 'block' : 'none';
                });
            }

            porteeSelect.addEventListener('change', basculerChamps);
            basculerChamps();
        })();
    </script>
@endsection
