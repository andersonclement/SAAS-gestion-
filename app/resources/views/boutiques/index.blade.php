@extends('layouts.app')

@section('title', __('Boutiques'))

@section('content')
    <h1>{{ __('Boutiques') }}</h1>

    <div class="card">
        @if ($boutiques->isEmpty())
            <p>{{ __('Aucune boutique pour le moment.') }}</p>
        @else
            <table>
                <thead>
                    <tr><th>{{ __('Nom') }}</th><th>{{ __('Adresse') }}</th><th>{{ __('Téléphone') }}</th><th>{{ __('Statut') }}</th><th>{{ __('Utilisateurs') }}</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($boutiques as $boutique)
                        <tr>
                            <td>{{ $boutique->nom }}</td>
                            <td>{{ $boutique->adresse ?? '—' }}</td>
                            <td>{{ $boutique->telephone ?? '—' }}</td>
                            <td><span class="badge">{{ $boutique->actif ? __('Active') : __('Inactive') }}</span></td>
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
