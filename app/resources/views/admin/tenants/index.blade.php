@extends('layouts.admin')

@section('title', __('Clients'))

@section('content')
    <h1 style="margin-top:0;">{{ __('Clients (tenants)') }}</h1>

    <div class="card">
        <form method="GET" style="display:flex;gap:.75rem;align-items:end;max-width:420px;">
            <div class="field" style="margin-bottom:0;flex:1;">
                <label for="q">{{ __('Rechercher') }}</label>
                <input id="q" type="text" name="q" value="{{ $recherche }}" placeholder="{{ __('Nom ou e-mail') }}">
            </div>
            <button class="btn" type="submit">{{ __('Rechercher') }}</button>
        </form>
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
                            <td><a href="{{ route('admin.tenants.show', $tenant) }}">{{ __('Voir') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
