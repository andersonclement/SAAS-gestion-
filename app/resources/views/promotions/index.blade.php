@extends('layouts.app')

@section('title', __('Promotions'))

@section('content')
    <h1>{{ __('Promotions') }}</h1>

    <div class="card">
        @if ($promotions->isEmpty())
            <p>{{ __('Aucune promotion pour le moment.') }}</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Nom') }}</th>
                        <th>{{ __('Remise') }}</th>
                        <th>{{ __('Portée') }}</th>
                        <th>{{ __('Période') }}</th>
                        <th>{{ __('Statut') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($promotions as $promotion)
                        <tr>
                            <td>{{ $promotion->nom }}</td>
                            <td>{{ $promotion->type->value === 'pourcentage' ? $promotion->valeur.' %' : number_format($promotion->valeur, 0, ',', ' ').' FCFA' }}</td>
                            <td>
                                {{ $promotion->portee->label() }}
                                @if ($promotion->produit) — {{ $promotion->produit->nom }} @endif
                                @if ($promotion->categorie) — {{ $promotion->categorie->nom }} @endif
                                @if ($promotion->client) — {{ $promotion->client->nom }} @endif
                            </td>
                            <td>{{ $promotion->date_debut->format('d/m/Y') }} → {{ $promotion->date_fin->format('d/m/Y') }}</td>
                            <td>
                                @if ($promotion->estActive())
                                    <span class="badge" style="background:#e6f4ea;color:#1e4620;">{{ __('Active') }}</span>
                                @else
                                    <span class="badge">{{ __('Inactive') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @can('create', App\Models\Promotion::class)
            <p style="margin-top:1rem;"><a class="btn" href="{{ route('promotions.create') }}">+ {{ __('Nouvelle promotion') }}</a></p>
        @endcan
    </div>
@endsection
