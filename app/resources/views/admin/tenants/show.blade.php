@extends('layouts.admin')

@section('title', $tenant->nom)

@section('content')
    <p><a href="{{ route('admin.tenants.index') }}">&larr; {{ __('Clients (tenants)') }}</a></p>
    <h1 style="margin-top:0;display:flex;align-items:center;gap:.75rem;">
        {{ $tenant->nom }}
        @if (! $tenant->actif)
            <span class="badge" style="background:#fdecea;color:#7a1f1f;font-size:.85rem;">{{ __('Suspendu') }}</span>
        @endif
    </h1>

    <p>
        <form method="POST" action="{{ route('admin.tenants.toggle-actif', $tenant) }}" onsubmit="return confirm('{{ $tenant->actif ? __('Suspendre ce client ? Il sera immédiatement déconnecté et ne pourra plus se reconnecter.') : __('Réactiver ce client ?') }}');">
            @csrf
            <button type="submit" class="btn {{ $tenant->actif ? 'btn-danger' : '' }}">
                {{ $tenant->actif ? __('Suspendre ce client') : __('Réactiver ce client') }}
            </button>
        </form>
    </p>

    <div style="margin-bottom:1.5rem;">
        <a href="{{ route('admin.tenants.show', $tenant) }}" style="margin-right:1.25rem;font-weight:700;">{{ __("Vue d'ensemble") }}</a>
        <a href="{{ route('admin.tenants.ventes', $tenant) }}" style="margin-right:1.25rem;">{{ __('Ventes') }} ({{ $nombreVentes }})</a>
        <a href="{{ route('admin.tenants.journal', $tenant) }}">{{ __("Journal d'activité") }}</a>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card">
            <p class="label">{{ __('Formule') }}</p>
            <p class="value" style="font-size:1.2rem;">{{ $tenant->plan?->label() ?? __('Aucun') }}</p>
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
        <div class="kpi-card">
            <p class="label">{{ __('Boutiques') }}</p>
            <p class="value">{{ $tenant->boutiques->count() }}{{ $tenant->boutiquesMax() ? ' / '.$tenant->boutiquesMax() : '' }}</p>
        </div>
        <div class="kpi-card">
            <p class="label">{{ __('Alertes actives') }}</p>
            <p class="value" style="color:{{ $nombreAlertes > 0 ? '#b91c1c' : '#1e4620' }};">{{ $nombreAlertes }}</p>
        </div>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card">
            <p class="label">{{ __("Chiffre d'affaires (ce mois)") }}</p>
            <p class="value">{{ number_format($chiffreAffairesDuMois, 0, ',', ' ') }} FCFA</p>
        </div>
        <div class="kpi-card">
            <p class="label">{{ __("Chiffre d'affaires (total)") }}</p>
            <p class="value">{{ number_format($chiffreAffairesTotal, 0, ',', ' ') }} FCFA</p>
        </div>
        <div class="kpi-card">
            <p class="label">{{ __('Marge (total)') }}</p>
            <p class="value">{{ number_format($margeTotale, 0, ',', ' ') }} FCFA</p>
        </div>
        <div class="kpi-card">
            <p class="label">{{ __('Dettes clients en cours') }}</p>
            <p class="value" style="color:{{ $dettesClients > 0 ? '#b91c1c' : '#1e4620' }};">{{ number_format($dettesClients, 0, ',', ' ') }} FCFA</p>
        </div>
        <div class="kpi-card">
            <p class="label">{{ __('Dépenses (ce mois)') }}</p>
            <p class="value">{{ number_format($depensesDuMois, 0, ',', ' ') }} FCFA</p>
        </div>
        <div class="kpi-card">
            <p class="label">{{ __('Versements de caisse (ce mois)') }}</p>
            <p class="value">{{ number_format($versementsDuMois, 0, ',', ' ') }} FCFA</p>
        </div>
        <div class="kpi-card">
            <p class="label">{{ __('Bons de commande') }}</p>
            <p class="value">{{ $nombreBonsCommande }}</p>
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
        <h2 style="margin-top:0;font-size:1.05rem;">{{ __('Ventes récentes') }}</h2>
        @if ($dernieresVentes->isEmpty())
            <p>{{ __('Aucune vente pour le moment.') }}</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Numéro') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Boutique') }}</th>
                        <th>{{ __('Client') }}</th>
                        <th>{{ __('Total') }}</th>
                        <th>{{ __('Solde dû') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($dernieresVentes as $vente)
                        <tr>
                            <td>{{ $vente->numero }}</td>
                            <td>{{ $vente->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $vente->boutique->nom }}</td>
                            <td>{{ $vente->client?->nom ?? __('Client de passage') }}</td>
                            <td>{{ number_format($vente->montantTotal(), 0, ',', ' ') }} FCFA</td>
                            <td>{{ $vente->montantDu() > 0 ? number_format($vente->montantDu(), 0, ',', ' ').' FCFA' : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p style="margin-top:1rem;"><a href="{{ route('admin.tenants.ventes', $tenant) }}">{{ __('Voir toutes les ventes') }} &rarr;</a></p>
        @endif
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
                <tr><th>{{ __('Nom') }}</th><th>{{ __('E-mail') }}</th><th>{{ __('Rôle') }}</th><th>{{ __('Dernière connexion') }}</th></tr>
            </thead>
            <tbody>
                @foreach ($tenant->users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->role->label() }}</td>
                        <td>
                            @if ($derniere = $dernieresConnexionsParUser->get($user->id))
                                {{ \Illuminate\Support\Carbon::parse($derniere)->format('d/m/Y H:i') }}
                            @else
                                <span style="color:#92400e;">{{ __('Jamais connecté') }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2 style="margin-top:0;font-size:1.05rem;">{{ __("Activité récente") }}</h2>
        @if ($dernieresActivites->isEmpty())
            <p>{{ __("Aucune activité enregistrée pour le moment.") }}</p>
        @else
            <table>
                <thead>
                    <tr><th>{{ __('Date') }}</th><th>{{ __('Utilisateur') }}</th><th>{{ __('Boutique') }}</th><th>{{ __('Action') }}</th></tr>
                </thead>
                <tbody>
                    @foreach ($dernieresActivites as $activite)
                        <tr>
                            <td>{{ $activite->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $activite->utilisateur_nom }}</td>
                            <td>{{ $activite->boutique?->nom ?? '—' }}</td>
                            <td>{{ $activite->description }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p style="margin-top:1rem;"><a href="{{ route('admin.tenants.journal', $tenant) }}">{{ __("Voir tout le journal d'activité") }} &rarr;</a></p>
        @endif
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
