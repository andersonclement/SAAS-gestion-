@extends('layouts.admin')

@section('title', __('Clients'))

@section('content')
    <h1 style="margin-top:0;">{{ __('Clients (tenants)') }}</h1>

    <p><a class="btn btn-muted" href="{{ route('admin.tenants.export') }}">{{ __('Exporter en CSV') }}</a></p>

    <div class="card">
        <form method="GET" class="filtres">
            <div class="field" style="margin-bottom:0;flex:1;">
                <label for="q">{{ __('Rechercher') }}</label>
                <input id="q" type="text" name="q" value="{{ $recherche }}" placeholder="{{ __('Nom ou e-mail') }}">
            </div>
            <input type="hidden" name="statut" value="{{ $statut }}">
            <button class="btn" type="submit">{{ __('Rechercher') }}</button>
        </form>

        <div>
            <a href="{{ route('admin.tenants.index', ['q' => $recherche]) }}" style="margin-right:1rem;font-weight:{{ $statut ? 400 : 700 }};">{{ __('Tous') }}</a>
            <a href="{{ route('admin.tenants.index', ['q' => $recherche, 'statut' => 'actif']) }}" style="margin-right:1rem;font-weight:{{ $statut === 'actif' ? 700 : 400 }};">{{ __('Abonnements actifs') }}</a>
            <a href="{{ route('admin.tenants.index', ['q' => $recherche, 'statut' => 'expire']) }}" style="margin-right:1rem;font-weight:{{ $statut === 'expire' ? 700 : 400 }};">{{ __('Abonnements expirés') }}</a>
            <a href="{{ route('admin.tenants.index', ['q' => $recherche, 'statut' => 'expire_bientot']) }}" style="margin-right:1rem;font-weight:{{ $statut === 'expire_bientot' ? 700 : 400 }};">{{ __('Expirent sous 7 jours') }}</a>
            <a href="{{ route('admin.tenants.index', ['q' => $recherche, 'statut' => 'suspendu']) }}" style="font-weight:{{ $statut === 'suspendu' ? 700 : 400 }};">{{ __('Clients suspendus') }}</a>
        </div>
    </div>

    <div class="card">
        @if ($tenants->isEmpty())
            <p>{{ __('Aucun client trouvé.') }}</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Nom') }}</th>
                        <th>{{ __('E-mail') }}</th>
                        <th>{{ __('Formule') }}</th>
                        <th>{{ __('Boutiques') }}</th>
                        <th>{{ __('Équipe') }}</th>
                        <th>{{ __('Abonnement') }}</th>
                        <th>{{ __('Compte') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tenants as $tenant)
                        <tr>
                            <td>{{ $tenant->nom }}</td>
                            <td>{{ $tenant->email_contact }}</td>
                            <td>{{ $tenant->plan?->label() ?? __('Aucun') }}</td>
                            <td>{{ $tenant->boutiques_count }}{{ $tenant->boutiquesMax() ? ' / '.$tenant->boutiquesMax() : '' }}</td>
                            <td>{{ $tenant->users_count }}</td>
                            <td>
                                @if ($tenant->abonnementActif())
                                    <span class="badge" style="background:#e6f4ea;color:#1e4620;">{{ $tenant->abonnement_expire_le->format('d/m/Y') }}</span>
                                @else
                                    <span class="badge" style="background:#fdecea;color:#7a1f1f;">{{ __('Expiré') }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($tenant->actif)
                                    <span class="badge" style="background:#e6f4ea;color:#1e4620;">{{ __('Actif') }}</span>
                                @else
                                    <span class="badge" style="background:#fdecea;color:#7a1f1f;">{{ __('Suspendu') }}</span>
                                @endif
                            </td>
                            <td><a href="{{ route('admin.tenants.show', $tenant) }}">{{ __('Voir') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
