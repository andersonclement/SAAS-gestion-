@extends('layouts.print')

@section('title', __('Facture') . ' ' . $vente->numero)

@section('content')
    <div class="facture">
        <div class="entete">
            <div class="boutique-nom">{{ $vente->boutique->nom }}</div>
            @if ($vente->boutique->adresse)
                <div>{{ $vente->boutique->adresse }}</div>
            @endif
            @if ($vente->boutique->telephone)
                <div>{{ $vente->boutique->telephone }}</div>
            @endif
        </div>

        <h1>{{ __('Facture') }} — {{ $vente->numero }}</h1>
        <div class="meta">
            <div>
                <div><strong>{{ __('Date') }} :</strong> {{ $vente->created_at->format('d/m/Y H:i') }}</div>
                <div><strong>{{ __('Client') }} :</strong> {{ $vente->client?->nom ?? __('Client de passage') }}</div>
            </div>
            <div style="text-align:right;">
                <div><strong>{{ __('Vendu par') }} :</strong> {{ $vente->vendeur->name }}</div>
                @if ($vente->estACredit())
                    <div><strong>{{ __("Échéance") }} :</strong> {{ $vente->date_echeance->format('d/m/Y') }}</div>
                @endif
            </div>
        </div>

        <table class="lignes">
            <thead>
                <tr>
                    <th>{{ __('Produit') }}</th>
                    <th>{{ __('Lot') }}</th>
                    <th>{{ __('Qté') }}</th>
                    <th>{{ __('P.U.') }}</th>
                    <th>{{ __('Sous-total') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($vente->lignes as $ligne)
                    <tr>
                        <td>{{ $ligne->produit->nom }}</td>
                        <td>{{ $ligne->lot->numero_lot }}</td>
                        <td>{{ $ligne->quantite }}</td>
                        <td>{{ number_format($ligne->prix_unitaire, 0, ',', ' ') }}</td>
                        <td>{{ number_format($ligne->sousTotal(), 0, ',', ' ') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totaux">
            <p class="total"><span>{{ __('Total') }}</span> <span>{{ number_format($vente->montantTotal(), 0, ',', ' ') }} FCFA</span></p>
            @foreach ($vente->paiements as $paiement)
                <p><span>{{ __('Payé') }} ({{ $paiement->mode->label() }})</span> <span>{{ number_format($paiement->montant, 0, ',', ' ') }} FCFA</span></p>
            @endforeach
        </div>

        @if ($vente->montantDu() > 0)
            <div class="statut-paiement du">
                {{ __('Reste à payer') }} : {{ number_format($vente->montantDu(), 0, ',', ' ') }} FCFA
                @if ($vente->estACredit())
                    <br>{{ __("échéance le") }} {{ $vente->date_echeance->format('d/m/Y') }}
                @endif
            </div>
        @else
            <div class="statut-paiement paye">{{ __('Payé comptant') }}</div>
        @endif

        <footer>{{ __('Merci de votre confiance.') }}</footer>
    </div>
@endsection
