@extends('layouts.app')

@section('title', __('Avoir'))

@section('content')
    <h1>{{ __('Avoir') }} — {{ $retour->produit->nom }}</h1>

    <div class="card">
        <p><strong>{{ __('Date') }} :</strong> {{ $retour->created_at->format('d/m/Y H:i') }}</p>
        <p><strong>{{ __('Boutique') }} :</strong> {{ $retour->boutique->nom }}</p>
        <p><strong>{{ __('Vente d\'origine') }} :</strong> <a href="{{ route('ventes.show', $retour->ligneVente->vente) }}">{{ $retour->ligneVente->vente->numero }}</a></p>
        <p><strong>{{ __('Produit') }} :</strong> {{ $retour->produit->nom }}</p>
        <p><strong>{{ __('Lot') }} :</strong> {{ $retour->lot->numero_lot }}</p>
        <p><strong>{{ __('Quantité') }} :</strong> {{ $retour->quantite }}</p>
        <p><strong>{{ __('Motif') }} :</strong> {{ $retour->motif->label() }}</p>
        <p><strong>{{ __('Remis en stock') }} :</strong> {{ $retour->remis_en_stock ? __('Oui') : __('Non') }}</p>
        <p><strong>{{ __('Mode de remboursement') }} :</strong> {{ $retour->mode_remboursement->label() }}</p>
        <p><strong>{{ __('Enregistré par') }} :</strong> {{ $retour->creePar->name }}</p>
        <p style="font-size:1.3rem;font-weight:700;">{{ __('Montant de l\'avoir') }} : {{ number_format($retour->montant_avoir, 0, ',', ' ') }} FCFA</p>
    </div>
@endsection
