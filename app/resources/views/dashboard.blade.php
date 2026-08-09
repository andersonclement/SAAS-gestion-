@extends('layouts.app')

@section('title', 'Tableau de bord')

@section('content')
    <h1>
        @if (auth()->user()->isPatron())
            Vue consolidée — {{ auth()->user()->tenant->nom }}
        @else
            {{ $boutiques->first()?->nom ?? 'Ma boutique' }}
        @endif
    </h1>

    <div class="card">
        <p style="margin:0;color:#555;">Nombre de boutiques {{ auth()->user()->isPatron() ? 'gérées' : 'assignée' }}</p>
        <p style="font-size:2rem;margin:.2rem 0 0;font-weight:700;">{{ $nombreBoutiques }}</p>
    </div>

    <div class="card">
        <h2 style="margin-top:0;">Boutiques</h2>
        @if ($boutiques->isEmpty())
            <p>Aucune boutique pour le moment.</p>
        @else
            <table>
                <thead>
                    <tr><th>Nom</th><th>Adresse</th><th>Utilisateurs</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($boutiques as $boutique)
                        <tr>
                            <td>{{ $boutique->nom }}</td>
                            <td>{{ $boutique->adresse ?? '—' }}</td>
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
