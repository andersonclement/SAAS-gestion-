@extends('layouts.app')

@section('title', __('Transferts de stock'))

@section('content')
    <h1>{{ __('Transferts de stock') }}</h1>

    <div class="card">
        @if ($transferts->isEmpty())
            <p>{{ __('Aucun transfert pour le moment.') }}</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Produit') }}</th>
                        <th>{{ __('Lot') }}</th>
                        <th>{{ __('De') }}</th>
                        <th>{{ __('Vers') }}</th>
                        <th>{{ __('Quantité') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($transferts as $transfert)
                        <tr>
                            <td>{{ $transfert->produit->nom }}</td>
                            <td>{{ $transfert->lot->numero_lot }}</td>
                            <td>{{ $transfert->boutiqueSource->nom }}</td>
                            <td>{{ $transfert->boutiqueDestination->nom }}</td>
                            <td>{{ $transfert->quantite }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <p style="margin-top:1rem;"><a class="btn" href="{{ route('stock.transferts.create') }}">+ {{ __('Nouveau transfert') }}</a></p>
    </div>
@endsection
