@extends('layouts.admin')

@section('title', __('Générer un code'))

@section('content')
    <p><a href="{{ route('admin.codes.index') }}">&larr; {{ __("Codes d'activation") }}</a></p>
    <h1 style="margin-top:0;">{{ __("Générer un code d'activation") }}</h1>

    <div class="card" style="max-width:560px;">
        <form method="POST" action="{{ route('admin.codes.store') }}">
            @csrf

            <div class="field">
                <label for="plan">{{ __('Formule') }}</label>
                <select id="plan" name="plan" required>
                    <option value="">— {{ __('Choisir') }} —</option>
                    @foreach (\App\Enums\Plan::cases() as $plan)
                        <option value="{{ $plan->value }}" @selected(old('plan') === $plan->value)>
                            {{ $plan->label() }} — {{ number_format($plan->prixMensuel(), 0, ',', ' ') }} FCFA/{{ __('mois') }} — {{ $plan->description() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="duree_mois">{{ __('Durée (mois)') }}</label>
                <input id="duree_mois" type="number" name="duree_mois" min="1" max="24" value="{{ old('duree_mois', 1) }}" required>
            </div>

            <div class="field">
                <label for="tenant_id">{{ __('Client (renouvellement — optionnel)') }}</label>
                <select id="tenant_id" name="tenant_id">
                    <option value="">— {{ __('Nouvelle inscription (aucun client)') }} —</option>
                    @foreach ($tenants as $tenant)
                        <option value="{{ $tenant->id }}" @selected((string) old('tenant_id', request('tenant_id')) === (string) $tenant->id)>{{ $tenant->nom }}</option>
                    @endforeach
                </select>
                <p style="color:#555;font-size:.8rem;margin:.3rem 0 0;">{{ __('Laissez vide pour un code destiné à une nouvelle inscription. Sélectionnez un client pour un renouvellement réservé à son compte.') }}</p>
            </div>

            <div class="field">
                <label for="notes">{{ __('Notes (optionnel)') }}</label>
                <textarea id="notes" name="notes" rows="2">{{ old('notes') }}</textarea>
            </div>

            <button class="btn" type="submit">{{ __('Générer le code') }}</button>
        </form>
    </div>
@endsection
