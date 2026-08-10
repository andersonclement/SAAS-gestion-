@extends('layouts.app')

@section('title', __('Tableau de bord'))

@section('content')
    <h1>
        @if (auth()->user()->isPatron())
            {{ __('Vue consolidée') }} — {{ auth()->user()->tenant->nom }}
        @else
            {{ $boutiques->first()?->nom ?? __('Ma boutique') }}
        @endif
    </h1>

    <div class="card">
        <p style="margin:0;color:#555;">{{ auth()->user()->isPatron() ? __('Nombre de boutiques gérées') : __('Nombre de boutiques assignée') }}</p>
        <p style="font-size:2rem;margin:.2rem 0 0;font-weight:700;">{{ $nombreBoutiques }}</p>
    </div>

    <div class="card">
        <h2 style="margin-top:0;">{{ __('Boutiques') }}</h2>
        @if ($boutiques->isEmpty())
            <p>{{ __('Aucune boutique pour le moment.') }}</p>
        @else
            <table>
                <thead>
                    <tr><th>{{ __('Nom') }}</th><th>{{ __('Adresse') }}</th><th>{{ __('Utilisateurs') }}</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($boutiques as $boutique)
                        <tr>
                            <td>{{ $boutique->nom }}</td>
                            <td>{{ $boutique->adresse ?? '—' }}</td>
                            <td>{{ $boutique->utilisateurs_count }}</td>
                            <td><a href="{{ route('boutiques.show', $boutique) }}">{{ __('Voir') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @can('create', App\Models\Boutique::class)
            <p style="margin-top:1rem;"><a class="btn" href="{{ route('boutiques.create') }}">+ {{ __('Nouvelle boutique') }}</a></p>
        @endcan
    </div>
@endsection
