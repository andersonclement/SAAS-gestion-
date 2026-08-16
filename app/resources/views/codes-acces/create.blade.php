@extends('layouts.app')

@section('title', __('Délivrer un code'))

@section('content')
    <p><a href="{{ route('codes-acces.index') }}">&larr; {{ __("Codes d'accès") }}</a></p>
    <h1 style="margin-top:0;">{{ __('Délivrer un code') }}</h1>

    @if ($gerants->isEmpty())
        <div class="card">
            <p>{{ __("Vous n'avez pas encore de gérant actif. Créez-en un depuis l'équipe ou à la création d'une boutique.") }}</p>
            <p><a class="btn" href="{{ route('users.create') }}">+ {{ __('Nouveau compte') }}</a></p>
        </div>
    @else
        <div class="card" style="max-width:520px;">
            <form method="POST" action="{{ route('codes-acces.store') }}">
                @csrf

                <div class="field">
                    <label for="destinataire_user_id">{{ __('Gérant destinataire') }}</label>
                    <select id="destinataire_user_id" name="destinataire_user_id" required autofocus>
                        <option value="">— {{ __('Choisir') }} —</option>
                        @foreach ($gerants as $gerant)
                            <option value="{{ $gerant->id }}" @selected((string) old('destinataire_user_id') === (string) $gerant->id)>
                                {{ $gerant->name }} — {{ $gerant->boutique?->nom ?? __('sans boutique') }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="portee">{{ __('Ce que le code autorise') }}</label>
                    <select id="portee" name="portee" required>
                        @foreach ($portees as $portee)
                            <option value="{{ $portee->value }}" @selected(old('portee', \App\Enums\PorteeCodeAcces::Complet->value) === $portee->value)>
                                {{ $portee->label() }}
                            </option>
                        @endforeach
                    </select>
                    <small style="color:#56606b;">
                        @foreach ($portees as $portee)
                            <strong>{{ $portee->label() }}</strong> — {{ $portee->description() }}<br>
                        @endforeach
                    </small>
                </div>

                <div class="field">
                    <label for="duree_heures">{{ __('Durée de validité (heures)') }}</label>
                    <input id="duree_heures" type="number" min="1" max="720" name="duree_heures" value="{{ old('duree_heures', 48) }}" required>
                    <small style="color:#56606b;">{{ __('Au-delà de la durée, le code cesse de fonctionner de lui-même.') }}</small>
                </div>

                <div class="field">
                    <label for="motif">{{ __('Motif (optionnel)') }}</label>
                    <input id="motif" type="text" name="motif" value="{{ old('motif') }}"
                           placeholder="{{ __('Ex. : mise en place du stock initial') }}">
                </div>

                <button class="btn" type="submit">{{ __('Générer le code') }}</button>
            </form>
        </div>
    @endif
@endsection
