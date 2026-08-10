@extends('layouts.app')

@section('title', __('Catalogue produits'))

@section('content')
    <h1>{{ __('Catalogue produits') }}</h1>

    <div class="card">
        @if ($produits->isEmpty())
            <p>{{ __('Aucun produit pour le moment.') }}</p>
        @else
            <table>
                <thead>
                    <tr><th>{{ __('Nom') }}</th><th>{{ __('Type') }}</th><th>{{ __('Catégorie') }}</th><th>{{ __('Unité') }}</th><th>{{ __('Prix vente') }}</th><th>{{ __('Seuil alerte') }}</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($produits as $produit)
                        <tr>
                            <td>{{ $produit->nom }}</td>
                            <td>
                                <span class="badge">{{ $produit->type->label() }}</span>
                                @if ($produit->type->tracabiliteObligatoire())
                                    <span class="badge" title="{{ __('Traçabilité par lot obligatoire') }}">⚠ {{ __('traçabilité') }}</span>
                                @endif
                            </td>
                            <td>{{ $produit->categorie?->nom ?? '—' }}</td>
                            <td>{{ $produit->unite_mesure->label() }}</td>
                            <td>{{ number_format($produit->prix_vente, 0, ',', ' ') }} FCFA</td>
                            <td>{{ $produit->seuil_alerte }}</td>
                            <td><a href="{{ route('produits.show', $produit) }}">{{ __('Voir') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div style="margin-top:1rem;">{{ $produits->links() }}</div>
        @endif

        @can('create', App\Models\Produit::class)
            <p style="margin-top:1rem;"><a class="btn" href="{{ route('produits.create') }}">+ {{ __('Nouveau produit') }}</a></p>
        @endcan
    </div>
@endsection
