@extends('layouts.app')

@section('title', $produit->nom)

@section('content')
    <h1>{{ $produit->nom }}</h1>

    @can('update', $produit)
        <p><a class="btn" href="{{ route('produits.edit', $produit) }}">{{ __('Modifier') }}</a></p>
    @endcan

    <div class="card">
        <p><strong>{{ __('Type') }} :</strong> {{ $produit->type->label() }}</p>
        <p><strong>{{ __('Catégorie') }} :</strong> {{ $produit->categorie?->nom ?? '—' }}</p>
        <p><strong>{{ __('Unité de mesure') }} :</strong> {{ $produit->unite_mesure->label() }}</p>
        <p><strong>{{ __('Code-barres') }} :</strong> {{ $produit->code_barres ?? '—' }}</p>
        <p><strong>{{ __("Prix d'achat") }} :</strong> {{ number_format($produit->prix_achat, 0, ',', ' ') }} FCFA</p>
        <p><strong>{{ __('Prix de vente') }} :</strong> {{ number_format($produit->prix_vente, 0, ',', ' ') }} FCFA</p>
        <p><strong>{{ __('Stock minimum') }} :</strong> {{ $produit->stock_min }}</p>
        <p><strong>{{ __('Stock maximum') }} :</strong> {{ $produit->stock_max ?: '—' }}</p>
        @if ($produit->type->tracabiliteObligatoire())
            <p style="color:#7a1f1f;"><strong>⚠ {{ __('Traçabilité par lot obligatoire') }}</strong> ({{ __('produit phytosanitaire') }}) — {{ __("suivie lors des réceptions d'achat.") }}</p>
        @endif
    </div>
@endsection
