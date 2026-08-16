@extends('layouts.app')

@section('title', __('Versements de caisse'))

@section('content')
    <h1>{{ __('Versements de caisse') }}</h1>

    <div class="card">
        @if ($versements->isEmpty())
            <p>{{ __('Aucun versement pour le moment.') }}</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Boutique') }}</th>
                        <th>{{ __('Remis par') }}</th>
                        <th>{{ __('Montant') }}</th>
                        <th>{{ __('Enregistré par') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($versements as $versement)
                        <tr>
                            <td>{{ $versement->date->format('d/m/Y') }}</td>
                            <td>{{ $versement->boutique->nom }}</td>
                            <td>{{ $versement->remis_par_nom }}</td>
                            <td>{{ number_format($versement->montant, 0, ',', ' ') }} FCFA</td>
                            <td>{{ $versement->creePar->name }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @can('create', App\Models\VersementCaisse::class)
            <p style="margin-top:1rem;"><a class="btn" href="{{ route('versements.create') }}">+ {{ __('Nouveau versement') }}</a></p>
        @endcan
    </div>
@endsection
