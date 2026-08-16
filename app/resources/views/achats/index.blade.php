@extends('layouts.app')

@section('title', __('Achats'))

@section('content')
    <h1>{{ __('Achats') }}</h1>

    <div class="card">
        @if ($bonsCommande->isEmpty())
            <p>{{ __('Aucun bon de commande pour le moment.') }}</p>
        @else
            <table>
                <thead>
                    <tr><th>{{ __('Numéro') }}</th><th>{{ __('Boutique') }}</th><th>{{ __('Fournisseur') }}</th><th>{{ __('Statut') }}</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($bonsCommande as $bonCommande)
                        <tr>
                            <td>{{ $bonCommande->numero }}</td>
                            <td>{{ $bonCommande->boutique->nom }}</td>
                            <td>{{ $bonCommande->fournisseur->nom }}</td>
                            <td><span class="badge">{{ $bonCommande->statut->label() }}</span></td>
                            <td><a href="{{ route('achats.show', $bonCommande) }}">{{ __('Voir') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @can('create', App\Models\BonCommande::class)
            <p style="margin-top:1rem;">
                <a class="btn" href="{{ route('achats.create') }}">+ {{ __('Nouveau bon de commande') }}</a>
                <a href="{{ route('fournisseurs.index') }}" style="margin-left:1rem;">{{ __('Fournisseurs') }}</a>
            </p>
        @endcan
    </div>
@endsection
