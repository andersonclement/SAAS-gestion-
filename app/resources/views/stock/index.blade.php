@extends('layouts.app')

@section('title', __('Stock'))

@section('content')
    <h1>{{ __('Stock') }}</h1>

    <div class="card">
        @if ($stocks->isEmpty())
            <p>{{ __('Aucun stock enregistré pour le moment.') }}</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Boutique') }}</th>
                        <th>{{ __('Produit') }}</th>
                        <th>{{ __('Lot') }}</th>
                        <th>{{ __('Péremption') }}</th>
                        <th>{{ __('Quantité') }}</th>
                        <th>{{ __('Alertes') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($stocks as $stock)
                        <tr>
                            <td>{{ $stock->boutique->nom }}</td>
                            <td>{{ $stock->produit->nom }}</td>
                            <td>{{ $stock->lot->numero_lot }}</td>
                            <td>{{ $stock->lot->date_peremption?->format('d/m/Y') ?? '—' }}</td>
                            <td>{{ $stock->quantite }} {{ $stock->produit->unite_mesure->label() }}</td>
                            <td>
                                @if ($stock->quantite === 0)
                                    <span class="badge" style="background:#fdecea;color:#611a15;">{{ __('Rupture de stock') }}</span>
                                @elseif ($stock->quantite <= $stock->produit->seuil_alerte)
                                    <span class="badge" style="background:#fff3cd;color:#7a5b00;">{{ __('Stock faible') }}</span>
                                @endif
                                @if ($stock->lot->estPerime())
                                    <span class="badge" style="background:#fdecea;color:#611a15;">{{ __('Périmé') }}</span>
                                @elseif ($stock->lot->peremptionProche())
                                    <span class="badge" style="background:#fff3cd;color:#7a5b00;">{{ __('Péremption proche') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <p style="margin-top:1rem;">
            <a class="btn" href="{{ route('stock.transferts.create') }}">{{ __('Transférer du stock') }}</a>
            <a class="btn" style="background:#555;margin-left:.5rem;" href="{{ route('stock.inventaires.create') }}">{{ __('Faire un inventaire') }}</a>
            <a href="{{ route('stock.transferts.index') }}" style="margin-left:1rem;">{{ __('Historique des transferts') }}</a>
            <a href="{{ route('stock.inventaires.index') }}" style="margin-left:1rem;">{{ __('Historique des inventaires') }}</a>
        </p>
    </div>
@endsection
