@extends('layouts.app')

@section('title', $boutique->nom)

@section('content')
    <h1>{{ $boutique->nom }}</h1>

    <div class="card">
        <p><strong>Adresse :</strong> {{ $boutique->adresse ?? '—' }}</p>
        <p><strong>Téléphone :</strong> {{ $boutique->telephone ?? '—' }}</p>
        <p><strong>Statut :</strong> <span class="badge">{{ $boutique->actif ? 'Active' : 'Inactive' }}</span></p>
    </div>

    <div class="card">
        <h2 style="margin-top:0;">Équipe de la boutique</h2>
        @if ($boutique->utilisateurs->isEmpty())
            <p>Aucun utilisateur assigné à cette boutique.</p>
        @else
            <table>
                <thead><tr><th>Nom</th><th>E-mail</th><th>Rôle</th></tr></thead>
                <tbody>
                    @foreach ($boutique->utilisateurs as $u)
                        <tr>
                            <td>{{ $u->name }}</td>
                            <td>{{ $u->email }}</td>
                            <td>{{ $u->role->label() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
