@extends('layouts.admin')

@section('title', __('Tableau de bord'))

@section('content')
    <h1 style="margin-top:0;">{{ __('Tableau de bord') }}</h1>

    <div class="kpi-grid">
        <a class="kpi-card" href="{{ route('admin.tenants.index') }}" style="text-decoration:none;color:inherit;display:block;">
            <p class="label">{{ __('Clients (tenants)') }}</p>
            <p class="value">{{ $nombreTenants }}</p>
        </a>
        <a class="kpi-card" href="{{ route('admin.tenants.index', ['statut' => 'actif']) }}" style="text-decoration:none;color:inherit;display:block;">
            <p class="label">{{ __('Abonnements actifs') }}</p>
            <p class="value" style="color:#1e4620;">{{ $nombreTenantsActifs }}</p>
        </a>
        <a class="kpi-card" href="{{ route('admin.tenants.index', ['statut' => 'expire']) }}" style="text-decoration:none;color:inherit;display:block;">
            <p class="label">{{ __('Abonnements expirés') }}</p>
            <p class="value" style="color:#7a1f1f;">{{ $nombreTenantsExpires }}</p>
        </a>
        <a class="kpi-card" href="{{ route('admin.tenants.index', ['statut' => 'expire_bientot']) }}" style="text-decoration:none;color:inherit;display:block;">
            <p class="label">{{ __('Expirent sous 7 jours') }}</p>
            <p class="value" style="color:#92400e;">{{ $nombreTenantsExpirentBientot }}</p>
        </a>
        <a class="kpi-card" href="{{ route('admin.tenants.index', ['statut' => 'suspendu']) }}" style="text-decoration:none;color:inherit;display:block;">
            <p class="label">{{ __('Clients suspendus') }}</p>
            <p class="value" style="color:#7a1f1f;">{{ $nombreTenantsSuspendus }}</p>
        </a>
        <div class="kpi-card">
            <p class="label">{{ __('Revenu mensuel récurrent') }}</p>
            <p class="value">{{ number_format($revenuMensuelRecurrent, 0, ',', ' ') }} FCFA</p>
        </div>
        <a class="kpi-card" href="{{ route('admin.codes.index', ['statut' => 'en_attente']) }}" style="text-decoration:none;color:inherit;display:block;">
            <p class="label">{{ __("Codes en attente d'utilisation") }}</p>
            <p class="value">{{ $codesEnAttente }}</p>
        </a>
        <a class="kpi-card" href="{{ route('admin.connexions.index', ['statut' => 'reussie']) }}" style="text-decoration:none;color:inherit;display:block;">
            <p class="label">{{ __('Connexions réussies (24h)') }}</p>
            <p class="value" style="color:#1e4620;">{{ $connexionsReussies24h }}</p>
        </a>
        <a class="kpi-card" href="{{ route('admin.connexions.index', ['statut' => 'echouee']) }}" style="text-decoration:none;color:inherit;display:block;">
            <p class="label">{{ __('Connexions échouées (24h)') }}</p>
            <p class="value" style="color:{{ $connexionsEchouees24h > 0 ? '#b91c1c' : '#1e4620' }};">{{ $connexionsEchouees24h }}</p>
        </a>
    </div>

    <p><a class="btn" href="{{ route('admin.codes.create') }}">+ {{ __("Générer un code d'activation") }}</a></p>

    <div class="card">
        <h2 style="margin-top:0;font-size:1.05rem;">{{ __('Derniers clients') }}</h2>
        @if ($derniersTenants->isEmpty())
            <p>{{ __('Aucun client pour le moment.') }}</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Nom') }}</th>
                        <th>{{ __('Formule') }}</th>
                        <th>{{ __('Boutiques') }}</th>
                        <th>{{ __('Expiration') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($derniersTenants as $tenant)
                        <tr>
                            <td>{{ $tenant->nom }}</td>
                            <td>{{ $tenant->plan?->label() ?? __('Aucun') }}</td>
                            <td>{{ $tenant->boutiques_count }}{{ $tenant->boutiquesMax() ? ' / '.$tenant->boutiquesMax() : '' }}</td>
                            <td>
                                @if ($tenant->abonnementActif())
                                    <span class="badge" style="background:#e6f4ea;color:#1e4620;">{{ $tenant->abonnement_expire_le->format('d/m/Y') }}</span>
                                @else
                                    <span class="badge" style="background:#fdecea;color:#7a1f1f;">{{ __('Expiré') }}</span>
                                @endif
                            </td>
                            <td><a href="{{ route('admin.tenants.show', $tenant) }}">{{ __('Voir') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card">
        <h2 style="margin-top:0;font-size:1.05rem;">{{ __('Derniers codes générés') }}</h2>
        @if ($derniersCodes->isEmpty())
            <p>{{ __('Aucun code pour le moment.') }}</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Code') }}</th>
                        <th>{{ __('Formule') }}</th>
                        <th>{{ __('Statut') }}</th>
                        <th>{{ __('Client') }}</th>
                        <th>{{ __('Généré le') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($derniersCodes as $code)
                        <tr>
                            <td><code class="code-pill">{{ $code->code }}</code></td>
                            <td>{{ $code->plan->label() }}</td>
                            <td>{{ $code->statut->label() }}</td>
                            <td>{{ $code->tenant?->nom ?? '—' }}</td>
                            <td>{{ $code->created_at->format('d/m/Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card">
        <h2 style="margin-top:0;font-size:1.05rem;">{{ __('Dernières connexions') }}</h2>
        @if ($dernieresConnexions->isEmpty())
            <p>{{ __('Aucune tentative de connexion enregistrée.') }}</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('E-mail') }}</th>
                        <th>{{ __('Client') }}</th>
                        <th>{{ __('Résultat') }}</th>
                        <th>{{ __('Adresse IP') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($dernieresConnexions as $tentative)
                        <tr>
                            <td>{{ $tentative->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $tentative->email }}</td>
                            <td>{{ $tentative->tenant?->nom ?? '—' }}</td>
                            <td>
                                @if ($tentative->reussie)
                                    <span class="badge" style="background:#e6f4ea;color:#1e4620;">{{ __('Réussie') }}</span>
                                @else
                                    <span class="badge" style="background:#fdecea;color:#7a1f1f;">{{ __('Échouée') }}</span>
                                @endif
                            </td>
                            <td>{{ $tentative->adresse_ip ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p style="margin-top:1rem;"><a href="{{ route('admin.connexions.index') }}">{{ __('Voir toutes les connexions') }} &rarr;</a></p>
        @endif
    </div>
@endsection
