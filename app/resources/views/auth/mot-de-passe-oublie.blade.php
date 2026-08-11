@extends('layouts.app')

@section('title', __('Mot de passe oublié'))

@section('content')
    <div class="card" style="max-width:420px;margin:3rem auto;">
        <h1 style="margin-top:0;">{{ __('Mot de passe oublié') }}</h1>
        <p style="color:#555;font-size:.9rem;">{{ __('Indiquez votre adresse e-mail : nous vous enverrons un lien pour choisir un nouveau mot de passe.') }}</p>

        <form method="POST" action="{{ route('password.email') }}">
            @csrf

            <div class="field">
                <label for="email">{{ __('Adresse e-mail') }}</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
            </div>

            <button class="btn" type="submit">{{ __('Envoyer le lien') }}</button>
        </form>

        <p style="margin-top:1.5rem;font-size:.9rem;">
            <a href="{{ route('login') }}">{{ __('Retour à la connexion') }}</a>
        </p>
    </div>
@endsection
