@extends('layouts.app')

@section('title', $vente->numero)

@section('content')
    <h1>{{ __('Ticket de caisse') }} — {{ $vente->numero }}</h1>

    <div class="card">
        <p><strong>{{ __('Date') }} :</strong> {{ $vente->created_at->format('d/m/Y H:i') }}</p>
        <p><strong>{{ __('Boutique') }} :</strong> {{ $vente->boutique->nom }}</p>
        <p><strong>{{ __('Client') }} :</strong> {{ $vente->client?->nom ?? __('Client de passage') }}</p>
        <p><strong>{{ __('Vendeur') }} :</strong> {{ $vente->vendeur->name }}</p>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>{{ __('Produit') }}</th>
                    <th>{{ __('Lot') }}</th>
                    <th>{{ __('Quantité') }}</th>
                    <th>{{ __('Prix unitaire') }}</th>
                    <th>{{ __('Sous-total') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($vente->lignes as $ligne)
                    <tr>
                        <td>{{ $ligne->produit->nom }}</td>
                        <td>{{ $ligne->lot->numero_lot }}</td>
                        <td>{{ $ligne->quantite }}</td>
                        <td>{{ number_format($ligne->prix_unitaire, 0, ',', ' ') }} FCFA</td>
                        <td>{{ number_format($ligne->sousTotal(), 0, ',', ' ') }} FCFA</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p style="text-align:right;font-weight:700;margin-top:1rem;">
            {{ __('Total') }} : {{ number_format($vente->montantTotal(), 0, ',', ' ') }} FCFA
        </p>

        <h2 style="font-size:1.1rem;">{{ __('Paiements') }}</h2>
        @foreach ($vente->paiements as $paiement)
            <p>{{ $paiement->mode->label() }} — {{ number_format($paiement->montant, 0, ',', ' ') }} FCFA</p>
        @endforeach
    </div>
@endsection
