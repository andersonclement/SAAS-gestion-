@extends('layouts.app')

@section('title', __('Modifier le client'))

@section('content')
    <p><a href="{{ route('clients.show', $client) }}">&larr; {{ $client->nom }}</a></p>
    <h1 style="margin-top:0;">{{ __('Modifier le client') }}</h1>

    <div class="card" style="max-width:520px;">
        <form method="POST" action="{{ route('clients.update', $client) }}">
            @csrf
            @method('PUT')

            <div class="field">
                <label for="nom">{{ __('Nom') }}</label>
                <input id="nom" type="text" name="nom" value="{{ old('nom', $client->nom) }}" required autofocus>
            </div>

            <div class="field">
                <label for="type">{{ __('Type de client') }}</label>
                <select id="type" name="type" required>
                    @foreach (\App\Enums\TypeClient::cases() as $type)
                        <option value="{{ $type->value }}" @selected(old('type', $client->type->value) === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="telephone">{{ __('Téléphone') }}</label>
                <input id="telephone" type="text" name="telephone" value="{{ old('telephone', $client->telephone) }}">
            </div>

            <div class="field">
                <label for="email">{{ __('Adresse e-mail') }}</label>
                <input id="email" type="email" name="email" value="{{ old('email', $client->email) }}">
            </div>

            <div class="field">
                <label for="adresse">{{ __('Adresse') }}</label>
                <input id="adresse" type="text" name="adresse" value="{{ old('adresse', $client->adresse) }}">
            </div>

            <div class="field">
                <label for="plafond_credit">{{ __('Plafond de crédit (FCFA)') }}</label>
                <input id="plafond_credit" type="number" min="0" name="plafond_credit" value="{{ old('plafond_credit', $client->plafond_credit) }}">
                <p style="color:#555;font-size:.8rem;margin:.3rem 0 0;">
                    {{ __('Dette en cours :') }} <strong>{{ number_format($client->soldeDette(), 0, ',', ' ') }} FCFA</strong>.
                    {{ __('Laissez vide pour interdire toute vente à crédit.') }}
                </p>
            </div>

            <button class="btn" type="submit">{{ __('Enregistrer les modifications') }}</button>
        </form>
    </div>
@endsection
