@extends('layouts.app')

@section('title', __('Créer votre espace'))

@section('content')
    <div class="card" style="max-width:460px;margin:3rem auto;">
        <h1 style="margin-top:0;">{{ __('Créer votre espace SaaS') }}</h1>
        <p style="color:#555;font-size:.9rem;">{{ __('Vous êtes le patron : cet espace vous appartient et sera totalement isolé des autres comptes de la plateforme.') }}</p>

        <form method="POST" action="{{ route('register') }}">
            @csrf

            <div class="field">
                <label for="entreprise">{{ __("Nom de l'entreprise") }}</label>
                <input id="entreprise" type="text" name="entreprise" value="{{ old('entreprise') }}" required autofocus>
            </div>

            <div class="field">
                <label for="name">{{ __('Votre nom') }}</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required>
            </div>

            <div class="field">
                <label for="email">{{ __('Adresse e-mail') }}</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required>
            </div>

            <div class="field">
                <label for="password">{{ __('Mot de passe') }}</label>
                <input id="password" type="password" name="password" required>
            </div>

            <div class="field">
                <label for="password_confirmation">{{ __('Confirmer le mot de passe') }}</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required>
            </div>

            <button class="btn" type="submit">{{ __('Créer mon espace') }}</button>
        </form>

        <p style="margin-top:1.5rem;font-size:.9rem;">
            {{ __('Déjà un compte ?') }} <a href="{{ route('login') }}">{{ __('Se connecter') }}</a>
        </p>
    </div>
@endsection
