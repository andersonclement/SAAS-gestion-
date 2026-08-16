@extends('layouts.app')

@section('title', __("Journal d'activité"))

@section('content')
    <h1>{{ __("Journal d'activité") }}</h1>

    <div class="card">
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
                        <th>{{ __("Code d'accès") }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($activites as $activite)
                        <tr>
                            <td>{{ $activite->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $activite->utilisateur_nom }}</td>
                            <td>{{ $activite->boutique?->nom ?? '—' }}</td>
                            <td>{{ $activite->description }}</td>
                            <td>
                                @if ($activite->codeAcces)
                                    @can('view', $activite->codeAcces)
                                        <a href="{{ route('codes-acces.show', $activite->codeAcces) }}"><code>{{ $activite->codeAcces->code }}</code></a>
                                    @else
                                        <code>{{ $activite->codeAcces->code }}</code>
                                    @endcan
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div style="margin-top:1rem;">{{ $activites->links() }}</div>
        @endif
    </div>
@endsection
