@extends('layouts.app')

@section('title', __('Prévisions de réapprovisionnement'))

@section('content')
    <h1>{{ __('Prévisions de réapprovisionnement') }}</h1>

    <div class="card">
        <p style="margin-top:0;color:#555;">
            {{ __("La demande est estimée sur les douze dernières semaines, les plus récentes comptant davantage, puis corrigée par la saisonnalité du mois quand une année d'historique est disponible.") }}
        </p>

        <form method="GET" action="{{ route('previsions.index') }}">
            @if ($boutiques->count() > 1)
                <div class="field">
                    <label for="boutique_id">{{ __('Boutique') }}</label>
                    <select id="boutique_id" name="boutique_id" data-auto-submit>
                        @foreach ($boutiques as $boutique)
                            <option value="{{ $boutique->id }}" @selected($boutique->id === $boutiqueId)>{{ $boutique->nom }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="field">
                <label for="delai">{{ __('Délai de livraison du fournisseur (jours)') }}</label>
                <input id="delai" type="number" min="0" max="90" name="delai" value="{{ $delai }}">
            </div>

            <div class="field">
                <label for="couverture">{{ __('Couverture souhaitée après livraison (jours)') }}</label>
                <input id="couverture" type="number" min="1" max="365" name="couverture" value="{{ $couverture }}">
            </div>

            <button class="btn" type="submit">{{ __('Recalculer') }}</button>
            <a class="btn" style="background:#555;"
               href="{{ route('previsions.export', ['boutique_id' => $boutiqueId, 'delai' => $delai, 'couverture' => $couverture]) }}">
                {{ __('Exporter la liste de commande') }}
            </a>
        </form>
    </div>

    @php
        $aCommander = $lignes->where('suggestion', '>', 0);
    @endphp

    <div class="card">
        <h2 style="margin-top:0;">{{ __('À commander') }} ({{ $aCommander->count() }})</h2>

        @if ($aCommander->isEmpty())
            <p>{{ __('Rien à commander sur cet horizon : les stocks couvrent la demande prévue.') }}</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Produit') }}</th>
                        <th>{{ __('Stock') }}</th>
                        <th>{{ __('Demande / jour') }}</th>
                        <th>{{ __('Rupture prévue') }}</th>
                        <th>{{ __('Commander avant') }}</th>
                        <th>{{ __('Quantité suggérée') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($aCommander as $ligne)
                        <tr>
                            <td>
                                <a href="{{ route('produits.show', $ligne['produit']) }}">{{ $ligne['produit']->nom }}</a>
                                @unless ($ligne['fiable'])
                                    {{-- Deux ou trois semaines de ventes ne font pas une tendance :
                                         le chiffre est affiché, mais signalé pour ce qu'il vaut. --}}
                                    <span class="badge" style="background:#fef3c7;color:#78350f;">{{ __('historique court') }}</span>
                                @endunless
                                @if ($ligne['saisonnalite_disponible'] && $ligne['coefficient_saisonnier'] != 1.0)
                                    <span class="badge" style="background:#e0f2fe;color:#075985;">
                                        {{ __('saison ×:coefficient', ['coefficient' => number_format($ligne['coefficient_saisonnier'], 2, ',', ' ')]) }}
                                    </span>
                                @endif
                            </td>
                            <td>{{ $ligne['stock'] }} {{ $ligne['produit']->unite_mesure->value }}</td>
                            <td>{{ number_format($ligne['demande_journaliere'], 2, ',', ' ') }}</td>
                            <td>
                                @if ($ligne['date_rupture'])
                                    {{ $ligne['date_rupture']->format('d/m/Y') }}
                                    <small style="color:#555;">({{ trans_choice('{0}aujourd\'hui|{1}dans 1 jour|[2,*]dans :count jours', $ligne['jours_avant_rupture']) }})</small>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $ligne['commander_avant']?->format('d/m/Y') ?? '—' }}</td>
                            <td style="font-weight:700;">
                                {{ $ligne['suggestion'] }} {{ $ligne['produit']->unite_mesure->value }}
                                @if ($ligne['conditionnement'] && $ligne['conditionnement']->facteur > 1)
                                    <br><small style="font-weight:400;color:#555;">
                                        {{ __(':nombre × :format', [
                                            'nombre' => intdiv($ligne['suggestion'], $ligne['conditionnement']->facteur),
                                            'format' => $ligne['conditionnement']->libelle,
                                        ]) }}
                                    </small>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card">
        <h2 style="margin-top:0;">{{ __('Stocks suffisants') }} ({{ $lignes->count() - $aCommander->count() }})</h2>

        @php $suffisants = $lignes->where('suggestion', '<=', 0); @endphp

        @if ($suffisants->isEmpty())
            <p>{{ __('Aucun produit dans cette situation.') }}</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Produit') }}</th>
                        <th>{{ __('Stock') }}</th>
                        <th>{{ __('Demande / jour') }}</th>
                        <th>{{ __('Rupture prévue') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($suffisants as $ligne)
                        <tr>
                            <td>{{ $ligne['produit']->nom }}</td>
                            <td>{{ $ligne['stock'] }} {{ $ligne['produit']->unite_mesure->value }}</td>
                            <td>{{ number_format($ligne['demande_journaliere'], 2, ',', ' ') }}</td>
                            <td>{{ $ligne['date_rupture']?->format('d/m/Y') ?? __('pas de vente récente') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
