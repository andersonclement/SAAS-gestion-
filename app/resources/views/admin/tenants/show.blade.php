@extends('layouts.admin')

@section('title', $tenant->nom)

@section('content')
    <p><a href="{{ route('admin.tenants.index') }}">&larr; {{ __('Clients (tenants)') }}</a></p>
    <h1 style="margin-top:0;">{{ $tenant->nom }}</h1>

    <div class="kpi-grid">
        <div class="kpi-card">
            <p class="label">{{ __('Formule') }}</p>
            <p class="value" style="font-size:1.2rem;">{{ $tenant->plan?->label() ?? __('Aucun') }}</p>
        </div>
        <div class="kpi-card">
            <p class="label">{{ __('Boutiques') }}</p>
            <p class="value">{{ $tenant->boutiques->count() }}{{ $tenant->boutiquesMax() ? ' / '.$tenant->boutiquesMax() : '' }}</p>
        </div>
        <div class="kpi-card">
            <p class="label">{{ __('Abonnement') }}</p>
            <p class="value" style="font-size:1.1rem;">
                @if ($tenant->abonnementActif())
                    <span class="badge" style="background:#e6f4ea;color:#1e4620;">{{ $tenant->abonnement_expire_le->format('d/m/Y') }}</span>
                @else
                    <span class="badge" style="background:#fdecea;color:#7a1f1f;">{{ __('Expiré') }}</span>
                @endif
            </p>
        </div>
    </div>

    <p>
        <a class="btn" href="{{ route('admin.codes.create') }}?tenant_id={{ $tenant->id }}">+ {{ __('Générer un code pour ce client') }}</a>
    </p>

    <div class="card">
        <h2 style="margin-top:0;font-size:1.05rem;">{{ __('Coordonnées') }}</h2>
        <p><strong>{{ __('E-mail') }} :</strong> {{ $tenant->email_contact }}</p>
        <p><strong>{{ __('Téléphone') }} :</strong> {{ $tenant->telephone ?? '—' }}</p>
    </div>

    <div class="card">
        <h2 style="margin-top:0;font-size:1.05rem;">{{ __('Boutiques') }}</h2>
        @if ($tenant->boutiques->isEmpty())
            <p>{{ __('Aucune boutique pour le moment.') }}</p>
        @else
            <table>
                <thead>
                    <tr><th>{{ __('Nom') }}</th><th>{{ __('Adresse') }}</th></tr>
                </thead>
                <tbody>
                    @foreach ($tenant->boutiques as $boutique)
                        <tr><td>{{ $boutique->nom }}</td><td>{{ $boutique->adresse ?? '—' }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card">
        <h2 style="margin-top:0;font-size:1.05rem;">{{ __('Équipe') }}</h2>
        <table>
            <thead>
                <tr><th>{{ __('Nom') }}</th><th>{{ __('E-mail') }}</th><th>{{ __('Rôle') }}</th></tr>
            </thead>
            <tbody>
                @foreach ($tenant->users as $user)
                    <tr><td>{{ $user->name }}</td><td>{{ $user->email }}</td><td>{{ $user->role->label() }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2 style="margin-top:0;font-size:1.05rem;">{{ __("Historique des codes d'activation") }}</h2>
        @if ($tenant->codesActivation->isEmpty())
            <p>{{ __('Aucun code utilisé pour ce client.') }}</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Code') }}</th>
                        <th>{{ __('Formule') }}</th>
                        <th>{{ __('Durée') }}</th>
                        <th>{{ __('Statut') }}</th>
                        <th>{{ __('Utilisé le') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tenant->codesActivation as $code)
                        <tr>
                            <td><code class="code-pill">{{ $code->code }}</code></td>
                            <td>{{ $code->plan->label() }}</td>
                            <td>{{ __(':n mois', ['n' => $code->duree_mois]) }}</td>
                            <td>{{ $code->statut->label() }}</td>
                            <td>{{ $code->utilise_le?->format('d/m/Y') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
