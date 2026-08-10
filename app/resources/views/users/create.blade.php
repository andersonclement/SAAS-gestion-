@extends('layouts.app')

@section('title', __('Nouveau compte'))

@section('content')
    <h1>{{ __('Nouveau compte') }}</h1>

    <div class="card" style="max-width:480px;">
        <form method="POST" action="{{ route('users.store') }}">
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

            <div class="field">
                <label for="role">{{ __('Rôle') }}</label>
                <select id="role" name="role" required>
                    <option value="">— {{ __('Choisir') }} —</option>
                    @foreach (\App\Enums\UserRole::cases() as $role)
                        @if ($role !== \App\Enums\UserRole::Patron)
                            <option value="{{ $role->value }}" @selected(old('role') === $role->value)>{{ $role->label() }}</option>
                        @endif
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="boutique_id">{{ __('Boutique (obligatoire pour gérant/vendeur)') }}</label>
                <select id="boutique_id" name="boutique_id">
                    <option value="">— {{ __('Aucune (accès global)') }} —</option>
                    @foreach ($boutiques as $boutique)
                        <option value="{{ $boutique->id }}" @selected((string) old('boutique_id') === (string) $boutique->id)>{{ $boutique->nom }}</option>
                    @endforeach
                </select>
            </div>

            <button class="btn" type="submit">{{ __('Créer le compte') }}</button>
        </form>
    </div>
@endsection
