@extends('layouts.admin')

@section('title', __("Journal d'activité") . ' — ' . $tenant->nom)

@section('content')
    <p><a href="{{ route('admin.tenants.show', $tenant) }}">&larr; {{ $tenant->nom }}</a></p>
    <h1 style="margin-top:0;">{{ __("Journal d'activité") }} — {{ $tenant->nom }}</h1>

    <div class="card">
        <div style="margin-bottom:1rem;">
            <a href="{{ route('admin.tenants.journal', $tenant) }}" style="margin-right:1rem;font-weight:{{ $action ? 400 : 700 }};">{{ __('Toutes les actions') }}</a>
            @foreach ($actions as $a)
                <a href="{{ route('admin.tenants.journal', ['tenant' => $tenant, 'action' => $a]) }}" style="margin-right:1rem;font-weight:{{ $action === $a ? 700 : 400 }};">{{ $a }}</a>
            @endforeach
        </div>

        @if ($activites->isEmpty())
            <p>{{ __("Aucune activité enregistrée pour le moment.") }}</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
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
