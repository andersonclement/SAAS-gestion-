@extends('layouts.app')

@section('title', __('Connexion'))

@section('content')
    <div class="card" style="max-width:420px;margin:3rem auto;">
        <h1 style="margin-top:0;">{{ __('Connexion') }}</h1>

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="field">
                <label for="email">{{ __('Adresse e-mail') }}</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
            </div>

            <div class="field">
                <label for="password">{{ __('Mot de passe') }}</label>
                <input id="password" type="password" name="password" required>
            </div>

            <button class="btn" type="submit">{{ __('Se connecter') }}</button>
        </form>

        <p style="margin-top:1rem;font-size:.9rem;">
            <a href="{{ route('password.request') }}">{{ __('Mot de passe oublié ?') }}</a>
        </p>

        <p style="margin-top:1rem;font-size:.9rem;">
            {{ __('Vous êtes un nouveau patron ?') }} <a href="{{ route('register') }}">{{ __('Créer votre espace') }}</a>
        </p>
    </div>
@endsection
