@extends('layouts.app')

@section('title', __('Abonnement'))

@section('content')
    <h1>{{ __('Abonnement') }}</h1>

    @if (! $tenant->abonnementActif())
        <div class="errors">
            {{ __("L'abonnement de votre espace a expiré. L'accès aux fonctionnalités est suspendu jusqu'au renouvellement.") }}
        </div>
    @endif

    <div class="card" style="max-width:560px;">
        <h2 style="margin-top:0;font-size:1.1rem;">{{ __('Situation actuelle') }}</h2>

        @if ($tenant->plan)
            <p><strong>{{ __('Formule') }} :</strong> {{ $tenant->plan->label() }} — {{ number_format($tenant->plan->prixMensuel(), 0, ',', ' ') }} FCFA/{{ __('mois') }}</p>
            <p><strong>{{ __('Boutiques') }} :</strong> {{ $nombreBoutiques }} / {{ $tenant->boutiquesMax() }}</p>
            <p>
                <strong>{{ __("Expire le") }} :</strong>
                {{ $tenant->abonnement_expire_le->format('d/m/Y') }}
                @if ($tenant->abonnementActif())
                    <span class="badge" style="background:#e6f4ea;color:#1e4620;">{{ __(':jours jour(s) restant(s)', ['jours' => $tenant->joursAvantExpiration()]) }}</span>
                @else
                    <span class="badge" style="background:#fdecea;color:#611a15;">{{ __('Expiré') }}</span>
                @endif
            </p>
        @else
            <p>{{ __('Aucun abonnement actif.') }}</p>
        @endif
    </div>

    @if (auth()->user()->isPatron())
        <div class="card" style="max-width:560px;">
            <h2 style="margin-top:0;font-size:1.1rem;">{{ __("Activer un code") }}</h2>
            <p style="color:#555;font-size:.9rem;">{{ __('Saisissez le code que vous a communiqué le superadmin pour activer ou renouveler votre abonnement.') }}</p>

            <form method="POST" action="{{ route('abonnement.activer') }}">
                @csrf
                <div class="field">
                    <label for="code_activation">{{ __("Code d'activation") }}</label>
                    <input id="code_activation" type="text" name="code_activation" placeholder="AGRO-XXXX-XXXX" required autofocus>
                </div>
                <button class="btn" type="submit">{{ __('Activer') }}</button>
            </form>
        </div>
    @else
        <div class="card" style="max-width:560px;">
            <p>{{ __("Seul le patron peut renouveler l'abonnement. Merci de le contacter.") }}</p>
        </div>
    @endif
@endsection
