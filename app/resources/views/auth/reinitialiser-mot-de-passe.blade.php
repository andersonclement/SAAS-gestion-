@extends('layouts.app')

@section('title', __('Nouveau mot de passe'))

@section('content')
    <div class="card" style="max-width:420px;margin:3rem auto;">
        <h1 style="margin-top:0;">{{ __('Nouveau mot de passe') }}</h1>

        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="field">
                <label for="email">{{ __('Adresse e-mail') }}</label>
                <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required>
            </div>

            <div class="field">
                <label for="password">{{ __('Nouveau mot de passe') }}</label>
                <input id="password" type="password" name="password" required autofocus>
            </div>

            <div class="field">
                <label for="password_confirmation">{{ __('Confirmer le mot de passe') }}</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required>
            </div>

            <button class="btn" type="submit">{{ __('Réinitialiser le mot de passe') }}</button>
        </form>
    </div>
@endsection
