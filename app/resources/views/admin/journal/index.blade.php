@extends('layouts.admin')

@section('title', __('Journal global'))

@section('content')
    <h1 style="margin-top:0;">{{ __('Journal global') }}</h1>
    <p style="color:#555;">{{ __("Activité de tous les clients de la plateforme, la plus récente en premier.") }}</p>

    <div class="card">
        <form method="GET" style="display:flex;gap:.75rem;align-items:end;flex-wrap:wrap;margin-bottom:1rem;">
            <div class="field" style="margin-bottom:0;">
                <label for="tenant_id">{{ __('Client') }}</label>
                <select id="tenant_id" name="tenant_id" data-auto-submit>
                    <option value="">{{ __('Tous') }}</option>
                    @foreach ($tenants as $t)
                        <option value="{{ $t->id }}" @selected((string) $tenantId === (string) $t->id)>{{ $t->nom }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="margin-bottom:0;">
                <label for="action">{{ __('Action') }}</label>
                <select id="action" name="action" data-auto-submit>
                    <option value="">{{ __('Toutes les actions') }}</option>
                    @foreach ($actions as $a)
                        <option value="{{ $a }}" @selected($action === $a)>{{ $a }}</option>
                    @endforeach
                </select>
            </div>
        </form>

        @if ($activites->isEmpty())
            <p>{{ __("Aucune activité enregistrée pour le moment.") }}</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Client') }}</th>
                        <th>{{ __('Utilisateur') }}</th>
                        <th>{{ __('Boutique') }}</th>
                        <th>{{ __('Action') }}</th>
                        <th>{{ __('Détail') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($activites as $activite)
                        <tr>
                            <td>{{ $activite->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                @if ($activite->tenant)
                                    <a href="{{ route('admin.tenants.show', $activite->tenant) }}">{{ $activite->tenant->nom }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $activite->utilisateur_nom }}</td>
                            <td>{{ $activite->boutique?->nom ?? '—' }}</td>
                            <td><code class="code-pill">{{ $activite->action }}</code></td>
                            <td>{{ $activite->description }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="margin-top:1rem;">{{ $activites->links() }}</div>
        @endif
    </div>
@endsection
