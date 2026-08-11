@extends('layouts.app')

@section('title', __('Trésorerie'))

@section('content')
    <h1>{{ __('Trésorerie') }}</h1>

    <h2 style="font-size:1.1rem;">{{ __('Caisse du jour') }}</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.5rem;">
        <div class="card" style="margin-bottom:0;">
            <p style="margin:0;color:#555;">{{ __('Encaissements du jour') }}</p>
            <p style="font-size:1.5rem;margin:.2rem 0 0;font-weight:700;">{{ number_format($encaissementsDuJour, 0, ',', ' ') }} FCFA</p>
        </div>
        <div class="card" style="margin-bottom:0;">
            <p style="margin:0;color:#555;">{{ __('Dépenses du jour') }}</p>
            <p style="font-size:1.5rem;margin:.2rem 0 0;font-weight:700;">{{ number_format($depensesDuJour, 0, ',', ' ') }} FCFA</p>
        </div>
        <div class="card" style="margin-bottom:0;">
            <p style="margin:0;color:#555;">{{ __('Versements du jour') }}</p>
            <p style="font-size:1.5rem;margin:.2rem 0 0;font-weight:700;">{{ number_format($versementsDuJour, 0, ',', ' ') }} FCFA</p>
        </div>
        <div class="card" style="margin-bottom:0;">
            <p style="margin:0;color:#555;">{{ __('Solde de caisse du jour') }}</p>
            <p style="font-size:1.5rem;margin:.2rem 0 0;font-weight:700;">{{ number_format($caisseDuJour, 0, ',', ' ') }} FCFA</p>
        </div>
    </div>

    <h2 style="font-size:1.1rem;">{{ __('Bénéfice net (ce mois)') }}</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.5rem;">
        <div class="card" style="margin-bottom:0;">
            <p style="margin:0;color:#555;">{{ __('Ventes du mois') }}</p>
            <p style="font-size:1.5rem;margin:.2rem 0 0;font-weight:700;">{{ number_format($ventesDuMois, 0, ',', ' ') }} FCFA</p>
        </div>
        <div class="card" style="margin-bottom:0;">
            <p style="margin:0;color:#555;">{{ __('Achats du mois') }}</p>
            <p style="font-size:1.5rem;margin:.2rem 0 0;font-weight:700;">{{ number_format($achatsDuMois, 0, ',', ' ') }} FCFA</p>
        </div>
        <div class="card" style="margin-bottom:0;">
            <p style="margin:0;color:#555;">{{ __('Dépenses du mois') }}</p>
            <p style="font-size:1.5rem;margin:.2rem 0 0;font-weight:700;">{{ number_format($depensesDuMois, 0, ',', ' ') }} FCFA</p>
        </div>
        <div class="card" style="margin-bottom:0;">
            <p style="margin:0;color:#555;">{{ __('Bénéfice net') }}</p>
            <p style="font-size:1.5rem;margin:.2rem 0 0;font-weight:700;color:{{ $beneficeNetDuMois >= 0 ? '#1e4620' : '#7a1f1f' }};">{{ number_format($beneficeNetDuMois, 0, ',', ' ') }} FCFA</p>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-top:0;">{{ __('Dépenses récentes') }}</h2>
        @if ($depensesRecentes->isEmpty())
            <p>{{ __('Aucune dépense pour le moment.') }}</p>
        @else
            <table>
                <thead>
                    <tr><th>{{ __('Date') }}</th><th>{{ __('Boutique') }}</th><th>{{ __('Catégorie') }}</th><th>{{ __('Montant') }}</th></tr>
                </thead>
                <tbody>
                    @foreach ($depensesRecentes as $depense)
                        <tr>
                            <td>{{ $depense->date->format('d/m/Y') }}</td>
                            <td>{{ $depense->boutique->nom }}</td>
                            <td>{{ $depense->categorie->label() }}</td>
                            <td>{{ number_format($depense->montant, 0, ',', ' ') }} FCFA</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @can('create', App\Models\Depense::class)
            <p style="margin-top:1rem;">
                <a class="btn" href="{{ route('depenses.create') }}">+ {{ __('Nouvelle dépense') }}</a>
                <a href="{{ route('depenses.index') }}" style="margin-left:1rem;">{{ __('Toutes les dépenses') }}</a>
            </p>
        @endcan
    </div>

    <div class="card">
        <h2 style="margin-top:0;">{{ __('Versements de caisse récents') }}</h2>
        <p style="color:#555;font-size:.9rem;margin-top:-.5rem;">{{ __("Argent physiquement collecté par le patron auprès d'un gérant ou vendeur.") }}</p>
        @if ($versementsRecents->isEmpty())
            <p>{{ __('Aucun versement pour le moment.') }}</p>
        @else
            <table>
                <thead>
                    <tr><th>{{ __('Date') }}</th><th>{{ __('Boutique') }}</th><th>{{ __('Remis par') }}</th><th>{{ __('Montant') }}</th></tr>
                </thead>
                <tbody>
                    @foreach ($versementsRecents as $versement)
                        <tr>
                            <td>{{ $versement->date->format('d/m/Y') }}</td>
                            <td>{{ $versement->boutique->nom }}</td>
                            <td>{{ $versement->remis_par_nom }}</td>
                            <td>{{ number_format($versement->montant, 0, ',', ' ') }} FCFA</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @can('create', App\Models\VersementCaisse::class)
            <p style="margin-top:1rem;">
                <a class="btn" href="{{ route('versements.create') }}">+ {{ __('Nouveau versement') }}</a>
                <a href="{{ route('versements.index') }}" style="margin-left:1rem;">{{ __('Tous les versements') }}</a>
            </p>
        @endcan
    </div>
@endsection
