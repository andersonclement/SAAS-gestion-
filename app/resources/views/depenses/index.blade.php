@extends('layouts.app')

@section('title', __('Dépenses'))

@section('content')
    <h1>{{ __('Dépenses') }}</h1>

    <div class="card">
        @if ($depenses->isEmpty())
            <p>{{ __('Aucune dépense pour le moment.') }}</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Boutique') }}</th>
                        <th>{{ __('Catégorie') }}</th>
                        <th>{{ __('Description') }}</th>
                        <th>{{ __('Montant') }}</th>
                        <th>{{ __('Enregistrée par') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($depenses as $depense)
                        <tr>
                            <td>{{ $depense->date->format('d/m/Y') }}</td>
                            <td>{{ $depense->boutique->nom }}</td>
                            <td>{{ $depense->categorie->label() }}</td>
                            <td>{{ $depense->description ?? '—' }}</td>
                            <td>{{ number_format($depense->montant, 0, ',', ' ') }} FCFA</td>
                            <td>{{ $depense->creePar->name }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @can('create', App\Models\Depense::class)
            <p style="margin-top:1rem;"><a class="btn" href="{{ route('depenses.create') }}">+ {{ __('Nouvelle dépense') }}</a></p>
        @endcan
    </div>
@endsection
