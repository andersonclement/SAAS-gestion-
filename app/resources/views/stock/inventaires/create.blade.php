@extends('layouts.app')

@section('title', __('Nouvel inventaire'))

@section('content')
    <h1>{{ __('Nouvel inventaire') }}</h1>

    @if ($boutiques->count() > 1)
        <div class="card" style="max-width:420px;">
            <form method="GET" action="{{ route('stock.inventaires.create') }}">
                <div class="field">
                    <label for="boutique_id">{{ __('Boutique') }}</label>
                    <select id="boutique_id" name="boutique_id" onchange="this.form.submit()">
                        @foreach ($boutiques as $boutique)
                            <option value="{{ $boutique->id }}" @selected($boutiqueId === $boutique->id)>{{ $boutique->nom }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>
    @endif

    <div class="card">
        @if ($stocks->isEmpty())
            <p>{{ __('Aucun stock à inventorier pour cette boutique.') }}</p>
        @else
            <form method="POST" action="{{ route('stock.inventaires.store') }}">
                @csrf
                <input type="hidden" name="boutique_id" value="{{ $boutiqueId }}">

                <table>
                    <thead>
                        <tr>
                            <th>{{ __('Produit') }}</th>
                            <th>{{ __('Lot') }}</th>
                            <th>{{ __('Quantité théorique') }}</th>
                            <th>{{ __('Quantité physique constatée') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($stocks as $stock)
                            <tr>
                                <td>{{ $stock->produit->nom }}</td>
                                <td>{{ $stock->lot->numero_lot }}</td>
                                <td>{{ $stock->quantite }}</td>
                                <td>
                                    <input type="number" min="0" style="width:100px;"
                                        name="lignes[{{ $stock->id }}][quantite_physique]"
                                        value="{{ old("lignes.{$stock->id}.quantite_physique", $stock->quantite) }}" required>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <button class="btn" type="submit" style="margin-top:1rem;">{{ __('Valider l\'inventaire') }}</button>
            </form>
        @endif
    </div>
@endsection
