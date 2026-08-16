@extends('layouts.admin')

@section('title', __('Nouvel administrateur'))

@section('content')
    <p><a href="{{ route('admin.admins.index') }}">&larr; {{ __('Administrateurs') }}</a></p>
    <h1 style="margin-top:0;">{{ __('Nouvel administrateur') }}</h1>

    <div class="card" style="max-width:480px;">
        <form method="POST" action="{{ route('admin.admins.store') }}">
            @csrf

            <div class="field">
                <label for="name">{{ __('Nom') }}</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus>
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

            <button class="btn" type="submit">{{ __('Créer') }}</button>
        </form>
    </div>
@endsection
