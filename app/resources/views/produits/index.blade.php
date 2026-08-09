@extends('layouts.app')

@section('title', 'Catalogue produits')

@section('content')
    <h1>Catalogue produits</h1>

    <div class="card">
        @if ($produits->isEmpty())
            <p>Aucun produit pour le moment.</p>
        @else
            <table>
                <thead>
                    <tr><th>Nom</th><th>Type</th><th>Catégorie</th><th>Unité</th><th>Prix vente</th><th>Seuil alerte</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($produits as $produit)
                        <tr>
                            <td>{{ $produit->nom }}</td>
                            <td>
                                <span class="badge">{{ $produit->type->label() }}</span>
                                @if ($produit->type->tracabiliteObligatoire())
                                    <span class="badge" title="Traçabilité par lot obligatoire">⚠ traçabilité</span>
                                @endif
                            </td>
                            <td>{{ $produit->categorie?->nom ?? '—' }}</td>
                            <td>{{ $produit->unite_mesure->label() }}</td>
                            <td>{{ number_format($produit->prix_vente, 0, ',', ' ') }} FCFA</td>
                            <td>{{ $produit->seuil_alerte }}</td>
                            <td><a href="{{ route('produits.show', $produit) }}">Voir</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div style="margin-top:1rem;">{{ $produits->links() }}</div>
        @endif

        @can('create', App\Models\Produit::class)
            <p style="margin-top:1rem;"><a class="btn" href="{{ route('produits.create') }}">+ Nouveau produit</a></p>
        @endcan
    </div>
@endsection
