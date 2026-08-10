@extends('layouts.app')

@section('title', __('Tableau de bord'))

@section('content')
    <h1>
        @if (auth()->user()->isPatron())
            {{ __('Vue consolidée') }} — {{ auth()->user()->tenant->nom }}
        @else
            {{ $boutiques->first()?->nom ?? __('Ma boutique') }}
        @endif
    </h1>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.5rem;">
        <div class="card" style="margin-bottom:0;">
            <p style="margin:0;color:#555;">{{ __("Chiffre d'affaires (ce mois)") }}</p>
            <p style="font-size:1.6rem;margin:.2rem 0 0;font-weight:700;">{{ number_format($chiffreAffairesDuMois, 0, ',', ' ') }} FCFA</p>
        </div>
        <div class="card" style="margin-bottom:0;">
            <p style="margin:0;color:#555;">{{ __("Chiffre d'affaires (total)") }}</p>
            <p style="font-size:1.6rem;margin:.2rem 0 0;font-weight:700;">{{ number_format($chiffreAffairesTotal, 0, ',', ' ') }} FCFA</p>
        </div>
        <div class="card" style="margin-bottom:0;">
            <p style="margin:0;color:#555;">{{ __('Marge (total)') }}</p>
            <p style="font-size:1.6rem;margin:.2rem 0 0;font-weight:700;">{{ number_format($margeTotale, 0, ',', ' ') }} FCFA</p>
        </div>
        <div class="card" style="margin-bottom:0;">
            <p style="margin:0;color:#555;">{{ auth()->user()->isPatron() ? __('Nombre de boutiques gérées') : __('Nombre de boutiques assignée') }}</p>
            <p style="font-size:1.6rem;margin:.2rem 0 0;font-weight:700;">{{ $nombreBoutiques }}</p>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-top:0;">{{ __('Meilleures ventes') }}</h2>
        @if ($meilleuresVentes->isEmpty())
            <p>{{ __('Aucune vente enregistrée pour le moment.') }}</p>
        @else
            <table>
                <thead>
                    <tr><th>{{ __('Produit') }}</th><th>{{ __('Quantité vendue') }}</th><th>{{ __("Chiffre d'affaires") }}</th></tr>
                </thead>
                <tbody>
                    @foreach ($meilleuresVentes as $ligne)
                        <tr>
                            <td>{{ $ligne->produit->nom }}</td>
                            <td>{{ $ligne->quantite_vendue }}</td>
                            <td>{{ number_format($ligne->chiffre_affaires, 0, ',', ' ') }} FCFA</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card">
        <h2 style="margin-top:0;">{{ __('Stock dormant') }}</h2>
        <p style="color:#555;font-size:.9rem;margin-top:-.5rem;">{{ __('Produits en stock sans vente depuis 60 jours ou plus.') }}</p>
        @if ($stockDormant->isEmpty())
            <p>{{ __('Aucun produit dormant.') }}</p>
        @else
            <table>
                <thead>
                    <tr><th>{{ __('Produit') }}</th><th>{{ __('Quantité en stock') }}</th><th>{{ __('Dernière vente') }}</th></tr>
                </thead>
                <tbody>
                    @foreach ($stockDormant as $entree)
                        <tr>
                            <td>{{ $entree['produit']->nom }}</td>
                            <td>{{ $entree['quantite'] }}</td>
                            <td>{{ $entree['derniere_vente'] ? \Illuminate\Support\Carbon::parse($entree['derniere_vente'])->format('d/m/Y') : __('Jamais vendu') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card">
        <h2 style="margin-top:0;">{{ __('Boutiques') }}</h2>
        @if ($boutiques->isEmpty())
            <p>{{ __('Aucune boutique pour le moment.') }}</p>
        @else
            <table>
                <thead>
                    <tr><th>{{ __('Nom') }}</th><th>{{ __('Adresse') }}</th><th>{{ __('Utilisateurs') }}</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($boutiques as $boutique)
                        <tr>
                            <td>{{ $boutique->nom }}</td>
                            <td>{{ $boutique->adresse ?? '—' }}</td>
                            <td>{{ $boutique->utilisateurs_count }}</td>
                            <td><a href="{{ route('boutiques.show', $boutique) }}">{{ __('Voir') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @can('create', App\Models\Boutique::class)
            <p style="margin-top:1rem;"><a class="btn" href="{{ route('boutiques.create') }}">+ {{ __('Nouvelle boutique') }}</a></p>
        @endcan
    </div>
@endsection
