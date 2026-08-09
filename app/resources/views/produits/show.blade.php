@extends('layouts.app')

@section('title', $produit->nom)

@section('content')
    <h1>{{ $produit->nom }}</h1>

    <div class="card">
        <p><strong>Type :</strong> {{ $produit->type->label() }}</p>
        <p><strong>Catégorie :</strong> {{ $produit->categorie?->nom ?? '—' }}</p>
        <p><strong>Unité de mesure :</strong> {{ $produit->unite_mesure->label() }}</p>
        <p><strong>Code-barres :</strong> {{ $produit->code_barres ?? '—' }}</p>
        <p><strong>Prix d'achat :</strong> {{ number_format($produit->prix_achat, 0, ',', ' ') }} FCFA</p>
        <p><strong>Prix de vente :</strong> {{ number_format($produit->prix_vente, 0, ',', ' ') }} FCFA</p>
        <p><strong>Seuil d'alerte :</strong> {{ $produit->seuil_alerte }}</p>
        @if ($produit->type->tracabiliteObligatoire())
            <p style="color:#7a1f1f;"><strong>⚠ Traçabilité par lot obligatoire</strong> (produit phytosanitaire) — suivie lors des réceptions d'achat.</p>
        @endif
    </div>
@endsection
