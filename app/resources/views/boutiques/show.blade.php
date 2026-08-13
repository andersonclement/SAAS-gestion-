@extends('layouts.app')

@section('title', $boutique->nom)

@section('content')
    <h1>{{ $boutique->nom }}</h1>

    @can('update', $boutique)
        <p><a class="btn" href="{{ route('boutiques.edit', $boutique) }}">{{ __('Modifier') }}</a></p>
    @endcan

    <div class="card">
        <p><strong>{{ __('Adresse') }} :</strong> {{ $boutique->adresse ?? '—' }}</p>
        <p><strong>{{ __('Téléphone') }} :</strong> {{ $boutique->telephone ?? '—' }}</p>
        <p><strong>{{ __('Statut') }} :</strong> <span class="badge">{{ $boutique->actif ? __('Active') : __('Inactive') }}</span></p>
    </div>

    <div class="card">
        <h2 style="margin-top:0;">{{ __('Équipe de la boutique') }}</h2>
        @if ($boutique->utilisateurs->isEmpty())
            <p>{{ __('Aucun utilisateur assigné à cette boutique.') }}</p>
        @else
            <table>
                <thead><tr><th>{{ __('Nom') }}</th><th>{{ __('E-mail') }}</th><th>{{ __('Rôle') }}</th></tr></thead>
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
