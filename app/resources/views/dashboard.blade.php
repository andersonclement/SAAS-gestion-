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

    {{-- Ce qui demande une décision passe avant les indicateurs. Le gérant
         ouvre son tableau de bord pour savoir quoi faire aujourd'hui. --}}
    <div class="card" style="border-left:4px solid {{ $notifications->isEmpty() ? '#1e4620' : $notifications->first()->importance->couleur() }};">
        <div style="display:flex;justify-content:space-between;align-items:baseline;gap:1rem;flex-wrap:wrap;">
            <h2 style="margin-top:0;">
                {{ __('À traiter') }}
                @if ($nombreNotificationsOuvertes > 0)
                    <span class="badge" style="background:#c0392b;color:#fff;">{{ $nombreNotificationsOuvertes }}</span>
                @endif
            </h2>
            <a href="{{ route('notifications.index') }}">{{ __('Toutes les notifications') }}</a>
        </div>

        @if ($notifications->isEmpty())
            <p style="margin:0;">{{ __('Rien à signaler sur votre périmètre. Stocks, péremptions et créances sont en ordre.') }}</p>
        @else
            <table>
                <tbody>
                    @foreach ($notifications as $notification)
                        <tr>
                            <td style="width:1%;white-space:nowrap;">
                                <span class="badge" style="background:{{ $notification->importance->couleur() }};color:#fff;">
                                    {{ $notification->type->label() }}
                                </span>
                            </td>
                            <td>
                                <strong>{{ $notification->titre }}</strong>
                                @if ($notification->estRappel())
                                    <span class="badge" style="background:#7a1f1f;color:#fff;">
                                        {{ trans_choice('{1}rappelé 1 fois|[2,*]rappelé :count fois', $notification->nombre_rappels) }}
                                    </span>
                                @endif
                                @if (! $notification->estLue())
                                    <span class="badge" style="background:#fef3c7;color:#78350f;">{{ __('nouveau') }}</span>
                                @endif
                                <br><small style="color:#555;">{{ $notification->message }}</small>
                            </td>
                            @if (auth()->user()->isPatron() || auth()->user()->isComptable())
                                {{-- Le patron voit plusieurs boutiques : sans cette
                                     colonne, il ne saurait pas où agir. --}}
                                <td style="white-space:nowrap;">{{ $notification->boutique?->nom ?? __('Compte') }}</td>
                            @endif
                            <td style="width:1%;">
                                <form method="POST" action="{{ route('notifications.lire', $notification) }}">
                                    @csrf
                                    <button class="btn" type="submit" style="padding:.35rem .7rem;min-height:0;">{{ __('Traiter') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if ($nombreNotificationsOuvertes > $notifications->count())
                <p style="margin-bottom:0;">
                    <a href="{{ route('notifications.index') }}">
                        {{ __('+ :reste autre(s) à traiter', ['reste' => $nombreNotificationsOuvertes - $notifications->count()]) }}
                    </a>
                </p>
            @endif
        @endif
    </div>

    <div class="kpi-grid">
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
        <h2 style="margin-top:0;">{{ __("Évolution du chiffre d'affaires") }}</h2>
        <p style="color:#555;font-size:.9rem;margin-top:-.5rem;">{{ __('Sur les 12 derniers mois, en FCFA.') }}</p>
        @if (array_sum($evolutionMensuelle) <= 0)
            <p>{{ __('Aucune vente enregistrée sur cette période.') }}</p>
        @else
            {!! \App\Support\Graphique::courbe($evolutionMensuelle) !!}
        @endif
    </div>

    @if ($chiffreParBoutique !== [])
        <div class="card">
            <h2 style="margin-top:0;">{{ __("Chiffre d'affaires par boutique") }}</h2>
            <p style="color:#555;font-size:.9rem;margin-top:-.5rem;">{{ __('Depuis la création, en FCFA.') }}</p>
            {!! \App\Support\Graphique::barres($chiffreParBoutique) !!}
        </div>
    @endif

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
