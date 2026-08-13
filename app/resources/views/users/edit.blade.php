@extends('layouts.app')

@section('title', __('Modifier le compte'))

@section('content')
    <p><a href="{{ route('users.index') }}">&larr; {{ __('Équipe') }}</a></p>
    <h1 style="margin-top:0;">{{ __('Modifier le compte') }}</h1>

    <div class="card" style="max-width:520px;">
        <form method="POST" action="{{ route('users.update', $user) }}">
            @csrf
            @method('PUT')

            <div class="field">
                <label for="name">{{ __('Nom') }}</label>
                <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" required autofocus>
            </div>

            <div class="field">
                <label for="email">{{ __('Adresse e-mail') }}</label>
                <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required>
            </div>

            @if ($user->isPatron())
                <div class="field">
                    <label>{{ __('Rôle') }}</label>
                    <p style="margin:.2rem 0 0;">
                        {{ $user->role->label() }}
                        <span style="color:#56606b;">— {{ __('le compte propriétaire de l’espace ne change pas de rôle.') }}</span>
                    </p>
                </div>
            @else
                <div class="field">
                    <label for="role">{{ __('Rôle') }}</label>
                    <select id="role" name="role" required>
                        @foreach (\App\Enums\UserRole::cases() as $role)
                            @if ($role !== \App\Enums\UserRole::Patron)
                                <option value="{{ $role->value }}" @selected(old('role', $user->role->value) === $role->value)>{{ $role->label() }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="boutique_id">{{ __('Boutique (obligatoire pour gérant/vendeur)') }}</label>
                    <select id="boutique_id" name="boutique_id">
                        <option value="">— {{ __('Aucune (accès global)') }} —</option>
                        @foreach ($boutiques as $boutique)
                            <option value="{{ $boutique->id }}" @selected((string) old('boutique_id', $user->boutique_id) === (string) $boutique->id)>{{ $boutique->nom }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="field">
                <label for="password">{{ __('Nouveau mot de passe') }}</label>
                <input id="password" type="password" name="password" autocomplete="new-password">
                <small style="color:#56606b;">{{ __('Laissez vide pour conserver le mot de passe actuel.') }}</small>
            </div>

            <div class="field">
                <label for="password_confirmation">{{ __('Confirmer le nouveau mot de passe') }}</label>
                <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password">
            </div>

            @if (in_array($user->role, [\App\Enums\UserRole::Patron, \App\Enums\UserRole::Gerant], true))
                <div class="field">
                    <label for="alertes_email" style="display:flex;align-items:center;gap:.5rem;">
                        <input id="alertes_email" type="checkbox" name="alertes_email" value="1"
                               style="width:auto;min-height:0;" @checked(old('alertes_email', $user->alertes_email))>
                        {{ __('Recevoir le récapitulatif quotidien des alertes par e-mail') }}
                    </label>
                </div>
            @endif

            <button class="btn" type="submit">{{ __('Enregistrer') }}</button>
        </form>
    </div>
@endsection
