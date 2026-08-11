@extends('layouts.app')

@section('title', __('Retours'))

@section('content')
    <h1>{{ __('Retours') }}</h1>

    <div class="card">
        @if ($retours->isEmpty())
            <p>{{ __('Aucun retour pour le moment.') }}</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Boutique') }}</th>
                        <th>{{ __('Produit') }}</th>
                        <th>{{ __('Quantité') }}</th>
                        <th>{{ __('Motif') }}</th>
                        <th>{{ __('Avoir') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($retours as $retour)
                        <tr>
                            <td>{{ $retour->created_at->format('d/m/Y') }}</td>
                            <td>{{ $retour->boutique->nom }}</td>
                            <td>{{ $retour->produit->nom }}</td>
                            <td>{{ $retour->quantite }}</td>
                            <td>{{ $retour->motif->label() }}</td>
                            <td>{{ number_format($retour->montant_avoir, 0, ',', ' ') }} FCFA</td>
                            <td><a href="{{ route('retours.show', $retour) }}">{{ __('Voir') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
