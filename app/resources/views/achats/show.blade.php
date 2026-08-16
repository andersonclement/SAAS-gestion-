@extends('layouts.app')

@section('title', $bonCommande->numero)

@section('content')
    <h1>{{ $bonCommande->numero }}</h1>

    <div class="card">
        <p><strong>{{ __('Boutique') }} :</strong> {{ $bonCommande->boutique->nom }}</p>
        <p><strong>{{ __('Fournisseur') }} :</strong> {{ $bonCommande->fournisseur->nom }}</p>
        <p><strong>{{ __('Créé par') }} :</strong> {{ $bonCommande->creePar->name }}</p>
        <p><strong>{{ __('Statut') }} :</strong> <span class="badge">{{ $bonCommande->statut->label() }}</span></p>
        @if ($bonCommande->estRecu())
            <p><strong>{{ __('Reçu le') }} :</strong> {{ $bonCommande->recu_le->format('d/m/Y H:i') }}</p>
        @endif
    </div>

    <div class="card">
        <h2 style="margin-top:0;">{{ __('Lignes de commande') }}</h2>
        <table>
            <thead>
                <tr>
                    <th>{{ __('Produit') }}</th>
                    <th>{{ __('Quantité') }}</th>
                    <th>{{ __("Prix unitaire") }}</th>
                    <th>{{ __('Sous-total') }}</th>
                    <th>{{ __('Lot') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($bonCommande->lignes as $ligne)
                    <tr>
                        <td>{{ $ligne->produit->nom }}</td>
                        <td>{{ $ligne->quantite }}</td>
                        <td>{{ number_format($ligne->prix_unitaire, 0, ',', ' ') }} FCFA</td>
                        <td>{{ number_format($ligne->sousTotal(), 0, ',', ' ') }} FCFA</td>
                        <td>{{ $ligne->lot?->numero_lot ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p style="text-align:right;font-weight:700;margin-top:1rem;">
            {{ __('Total') }} : {{ number_format($bonCommande->montantTotal(), 0, ',', ' ') }} FCFA
        </p>

        @can('receptionner', $bonCommande)
            <p><a class="btn" href="{{ route('achats.receptionner.create', $bonCommande) }}">{{ __('Réceptionner la marchandise') }}</a></p>
        @endcan
    </div>
@endsection
