@extends('layouts.admin')

@section('title', __('Connexions'))

@section('content')
    <h1 style="margin-top:0;">{{ __('Connexions') }}</h1>
    <p style="color:#555;">{{ __("Toutes les tentatives de connexion à la plateforme (patrons, gérants, vendeurs, comptables, superadmins), réussies ou non.") }}</p>

    <div class="card">
        <form method="GET" class="filtres">
            <div class="field" style="margin-bottom:0;flex:1;">
                <label for="q">{{ __('Rechercher') }}</label>
                <input id="q" type="text" name="q" value="{{ $recherche }}" placeholder="{{ __('E-mail') }}">
            </div>
            <input type="hidden" name="statut" value="{{ $statut }}">
            <button class="btn" type="submit">{{ __('Rechercher') }}</button>
        </form>

        <div>
            <a href="{{ route('admin.connexions.index', ['q' => $recherche]) }}" style="margin-right:1rem;font-weight:{{ $statut ? 400 : 700 }};">{{ __('Toutes') }}</a>
            <a href="{{ route('admin.connexions.index', ['q' => $recherche, 'statut' => 'reussie']) }}" style="margin-right:1rem;font-weight:{{ $statut === 'reussie' ? 700 : 400 }};">{{ __('Réussies') }}</a>
            <a href="{{ route('admin.connexions.index', ['q' => $recherche, 'statut' => 'echouee']) }}" style="font-weight:{{ $statut === 'echouee' ? 700 : 400 }};">{{ __('Échouées') }}</a>
        </div>
    </div>

    <div class="card">
        @if ($tentatives->isEmpty())
            <p>{{ __('Aucune tentative de connexion enregistrée.') }}</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('E-mail') }}</th>
                        <th>{{ __('Rôle') }}</th>
                        <th>{{ __('Client') }}</th>
                        <th>{{ __('Résultat') }}</th>
                        <th>{{ __('Adresse IP') }}</th>
                        <th>{{ __('Appareil') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tentatives as $tentative)
                        <tr>
                            <td>{{ $tentative->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $tentative->email }}</td>
                            <td>{{ $tentative->role === 'admin' ? __('Superadmin') : __('Tenant') }}</td>
                            <td>
                                @if ($tentative->tenant)
                                    <a href="{{ route('admin.tenants.show', $tentative->tenant) }}">{{ $tentative->tenant->nom }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if ($tentative->reussie)
                                    <span class="badge" style="background:#e6f4ea;color:#1e4620;">{{ __('Réussie') }}</span>
                                @else
                                    <span class="badge" style="background:#fdecea;color:#7a1f1f;">{{ __('Échouée') }}</span>
                                @endif
                            </td>
                            <td>{{ $tentative->adresse_ip ?? '—' }}</td>
                            <td style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $tentative->user_agent }}">{{ $tentative->user_agent ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="margin-top:1rem;">{{ $tentatives->links() }}</div>
        @endif
    </div>
@endsection
