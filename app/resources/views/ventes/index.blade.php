@extends('layouts.app')

@section('title', __('Ventes'))

@section('content')
    <h1>{{ __('Ventes') }}</h1>

    <div class="card">
        @if ($ventes->isEmpty())
            <p>{{ __('Aucune vente pour le moment.') }}</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Numéro') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Boutique') }}</th>
                        <th>{{ __('Client') }}</th>
                        <th>{{ __('Vendeur') }}</th>
                        <th>{{ __('Total') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ventes as $vente)
                        <tr>
                            <td>{{ $vente->numero }}</td>
                            <td>{{ $vente->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $vente->boutique->nom }}</td>
                            <td>{{ $vente->client?->nom ?? __('Client de passage') }}</td>
                            <td>{{ $vente->vendeur->name }}</td>
                            <td>{{ number_format($vente->montantTotal(), 0, ',', ' ') }} FCFA</td>
                            <td><a href="{{ route('ventes.show', $vente) }}">{{ __('Voir') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @can('create', App\Models\Vente::class)
            <p style="margin-top:1rem;">
                <a class="btn" href="{{ route('ventes.create') }}">+ {{ __('Nouvelle vente') }}</a>
                <a href="{{ route('clients.index') }}" style="margin-left:1rem;">{{ __('Clients') }}</a>
            </p>
        @endcan
    </div>
@endsection
