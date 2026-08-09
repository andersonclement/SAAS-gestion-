@extends('layouts.app')

@section('title', 'Boutiques')

@section('content')
    <h1>Boutiques</h1>

    <div class="card">
        @if ($boutiques->isEmpty())
            <p>Aucune boutique pour le moment.</p>
        @else
            <table>
                <thead>
                    <tr><th>Nom</th><th>Adresse</th><th>Téléphone</th><th>Statut</th><th>Utilisateurs</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($boutiques as $boutique)
                        <tr>
                            <td>{{ $boutique->nom }}</td>
                            <td>{{ $boutique->adresse ?? '—' }}</td>
                            <td>{{ $boutique->telephone ?? '—' }}</td>
                            <td><span class="badge">{{ $boutique->actif ? 'Active' : 'Inactive' }}</span></td>
                            <td>{{ $boutique->utilisateurs_count }}</td>
                            <td><a href="{{ route('boutiques.show', $boutique) }}">Voir</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @can('create', App\Models\Boutique::class)
            <p style="margin-top:1rem;"><a class="btn" href="{{ route('boutiques.create') }}">+ Nouvelle boutique</a></p>
        @endcan
    </div>
@endsection
