@extends('layouts.app')

@section('title', __('Comparatif entre boutiques'))

@section('content')
    <h1>{{ __('Comparatif entre boutiques') }}</h1>
    <p style="color:#555;margin-top:-.5rem;">{{ __('Statistiques du mois en cours, classées par chiffre d\'affaires.') }}</p>

    <div class="card">
        @if ($statistiques->isEmpty())
            <p>{{ __('Aucune boutique pour le moment.') }}</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Rang') }}</th>
                        <th>{{ __('Boutique') }}</th>
                        <th>{{ __("Chiffre d'affaires") }}</th>
                        <th>{{ __('Marge') }}</th>
                        <th>{{ __('Quantité vendue') }}</th>
                        <th>{{ __('Quantité en stock') }}</th>
                        <th>{{ __('Rotation de stock') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($statistiques as $index => $stat)
                        <tr>
                            <td>#{{ $index + 1 }}</td>
                            <td>{{ $stat['boutique']->nom }}</td>
                            <td>{{ number_format($stat['chiffre_affaires'], 0, ',', ' ') }} FCFA</td>
                            <td>{{ number_format($stat['marge'], 0, ',', ' ') }} FCFA</td>
                            <td>{{ $stat['quantite_vendue'] }}</td>
                            <td>{{ $stat['quantite_en_stock'] }}</td>
                            <td>{{ $stat['rotation_stock'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
