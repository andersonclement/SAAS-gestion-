@extends('layouts.app')

@section('title', __('Alertes'))

@section('content')
    <h1>{{ __('Alertes') }}</h1>

    <div class="card">
        <h2 style="margin-top:0;">{{ __('Rupture de stock') }} ({{ $ruptures->count() }})</h2>
        @if ($ruptures->isEmpty())
            <p>{{ __('Aucune rupture de stock.') }}</p>
        @else
            <table>
                <thead><tr><th>{{ __('Boutique') }}</th><th>{{ __('Produit') }}</th></tr></thead>
                <tbody>
                    @foreach ($ruptures as $entree)
                        <tr>
                            <td>{{ $entree['boutique']->nom }}</td>
                            <td>{{ $entree['produit']->nom }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card">
        <h2 style="margin-top:0;">{{ __('Stock au minimum') }} ({{ $stockFaible->count() }})</h2>
        @if ($stockFaible->isEmpty())
            <p>{{ __('Aucun produit au niveau de son stock minimum.') }}</p>
        @else
            <table>
                <thead><tr><th>{{ __('Boutique') }}</th><th>{{ __('Produit') }}</th><th>{{ __('Quantité') }}</th><th>{{ __('Minimum') }}</th></tr></thead>
                <tbody>
                    @foreach ($stockFaible as $entree)
                        <tr>
                            <td>{{ $entree['boutique']->nom }}</td>
                            <td>{{ $entree['produit']->nom }}</td>
                            <td>{{ $entree['quantite'] }}</td>
                            <td>{{ $entree['produit']->stock_min }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card">
        <h2 style="margin-top:0;">{{ __('Surstock') }} ({{ $surstock->count() }})</h2>
        <p style="color:#56606b;font-size:.9rem;margin-top:-.5rem;">{{ __('Quantité au-dessus du stock maximum fixé pour le produit.') }}</p>
        @if ($surstock->isEmpty())
            <p>{{ __('Aucun produit au-dessus de son maximum.') }}</p>
        @else
            <table>
                <thead><tr><th>{{ __('Boutique') }}</th><th>{{ __('Produit') }}</th><th>{{ __('Quantité') }}</th><th>{{ __('Maximum') }}</th></tr></thead>
                <tbody>
                    @foreach ($surstock as $entree)
                        <tr>
                            <td>{{ $entree['boutique']->nom }}</td>
                            <td>{{ $entree['produit']->nom }}</td>
                            <td>{{ $entree['quantite'] }}</td>
                            <td>{{ $entree['produit']->stock_max }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card">
        <h2 style="margin-top:0;">{{ __('Péremption dépassée') }} ({{ $perimes->count() }})</h2>
        @if ($perimes->isEmpty())
            <p>{{ __('Aucun lot périmé en stock.') }}</p>
        @else
            <table>
                <thead><tr><th>{{ __('Boutique') }}</th><th>{{ __('Produit') }}</th><th>{{ __('Lot') }}</th><th>{{ __('Péremption') }}</th><th>{{ __('Quantité') }}</th></tr></thead>
                <tbody>
                    @foreach ($perimes as $stock)
                        <tr>
                            <td>{{ $stock->boutique->nom }}</td>
                            <td>{{ $stock->produit->nom }}</td>
                            <td>{{ $stock->lot->numero_lot }}</td>
                            <td>{{ $stock->lot->date_peremption->format('d/m/Y') }}</td>
                            <td>{{ $stock->quantite }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card">
        <h2 style="margin-top:0;">{{ __('Péremption proche (30 jours)') }} ({{ $peremptionProche->count() }})</h2>
        @if ($peremptionProche->isEmpty())
            <p>{{ __('Aucun lot bientôt périmé.') }}</p>
        @else
            <table>
                <thead><tr><th>{{ __('Boutique') }}</th><th>{{ __('Produit') }}</th><th>{{ __('Lot') }}</th><th>{{ __('Péremption') }}</th><th>{{ __('Quantité') }}</th></tr></thead>
                <tbody>
                    @foreach ($peremptionProche as $stock)
                        <tr>
                            <td>{{ $stock->boutique->nom }}</td>
                            <td>{{ $stock->produit->nom }}</td>
                            <td>{{ $stock->lot->numero_lot }}</td>
                            <td>{{ $stock->lot->date_peremption->format('d/m/Y') }}</td>
                            <td>{{ $stock->quantite }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card">
        <h2 style="margin-top:0;">{{ __('Créances en retard') }} ({{ $creancesEnRetard->count() }})</h2>
        @if ($creancesEnRetard->isEmpty())
            <p>{{ __('Aucune créance en retard.') }}</p>
        @else
            <table>
                <thead><tr><th>{{ __('Numéro') }}</th><th>{{ __('Boutique') }}</th><th>{{ __('Client') }}</th><th>{{ __('Échéance') }}</th><th>{{ __('Montant dû') }}</th><th></th></tr></thead>
                <tbody>
                    @foreach ($creancesEnRetard as $vente)
                        <tr>
                            <td>{{ $vente->numero }}</td>
                            <td>{{ $vente->boutique->nom }}</td>
                            <td>{{ $vente->client?->nom ?? '—' }}</td>
                            <td>{{ $vente->date_echeance->format('d/m/Y') }}</td>
                            <td>{{ number_format($vente->montantDu(), 0, ',', ' ') }} FCFA</td>
                            <td><a href="{{ route('ventes.show', $vente) }}">{{ __('Voir') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    <div class="card">
        <h2 style="margin-top:0;">{{ __('Écarts de stock à la synchronisation') }} ({{ $ecartsSynchronisation->count() }})</h2>
        <p style="color:#555;font-size:.9rem;margin-top:0;">
            {{ __("Ces ventes ont été encaissées sans réseau. Au moment de la synchronisation, le stock enregistré ne suffisait plus à couvrir la quantité vendue : le stock théorique dépasse donc le stock réel de la quantité manquante.") }}
        </p>
        @if ($ecartsSynchronisation->isEmpty())
            <p>{{ __('Aucun écart à traiter.') }}</p>
        @else
            <table>
                <thead><tr><th>{{ __('Vente') }}</th><th>{{ __('Boutique') }}</th><th>{{ __('Produit') }}</th><th>{{ __('Vendu') }}</th><th>{{ __('Servi') }}</th><th>{{ __('Manquant') }}</th><th></th></tr></thead>
                <tbody>
                    @foreach ($ecartsSynchronisation as $ecart)
                        <tr>
                            <td><a href="{{ route('ventes.show', $ecart->vente) }}">{{ $ecart->vente->numero }}</a></td>
                            <td>{{ $ecart->boutique->nom }}</td>
                            <td>{{ $ecart->produit->nom }}</td>
                            <td>{{ $ecart->quantite_demandee }}</td>
                            <td>{{ $ecart->quantite_allouee }}</td>
                            <td style="color:#c0392b;font-weight:700;">{{ $ecart->quantite_manquante }}</td>
                            <td>
                                @can('resoudre', $ecart)
                                    <form method="POST" action="{{ route('ecarts-synchronisation.resoudre', $ecart) }}">
                                        @csrf
                                        <button class="btn" type="submit" style="background:#555;">{{ __('Marquer traité') }}</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
