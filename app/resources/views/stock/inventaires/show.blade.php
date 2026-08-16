@extends('layouts.app')

@section('title', __('Inventaire'))

@section('content')
    <h1>{{ __('Inventaire') }} — {{ $inventaire->boutique->nom }}</h1>
    <p style="color:#555;">{{ $inventaire->created_at->format('d/m/Y H:i') }} — {{ $inventaire->creePar->name }}</p>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>{{ __('Produit') }}</th>
                    <th>{{ __('Lot') }}</th>
                    <th>{{ __('Quantité théorique') }}</th>
                    <th>{{ __('Quantité physique') }}</th>
                    <th>{{ __('Écart') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($inventaire->lignes as $ligne)
                    <tr>
                        <td>{{ $ligne->produit->nom }}</td>
                        <td>{{ $ligne->lot->numero_lot }}</td>
                        <td>{{ $ligne->quantite_theorique }}</td>
                        <td>{{ $ligne->quantite_physique }}</td>
                        <td style="color: {{ $ligne->ecart() === 0 ? 'inherit' : ($ligne->ecart() > 0 ? '#1e4620' : '#7a1f1f') }};">
                            {{ $ligne->ecart() > 0 ? '+' : '' }}{{ $ligne->ecart() }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
