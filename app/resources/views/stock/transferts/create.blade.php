@extends('layouts.app')

@section('title', __('Nouveau transfert de stock'))

@section('content')
    <h1>{{ __('Nouveau transfert de stock') }}</h1>

    <div class="card" style="max-width:520px;">
        @if ($stocksDisponibles->isEmpty())
            <p>{{ __('Aucun stock disponible à transférer.') }}</p>
        @else
            <form method="POST" action="{{ route('stock.transferts.store') }}">
                @csrf

                <div class="field">
                    <label for="stock_boutique_id">{{ __('Produit / lot à transférer') }}</label>
                    <select id="stock_boutique_id" name="stock_boutique_id" required>
                        <option value="">— {{ __('Choisir') }} —</option>
                        @foreach ($stocksDisponibles as $stock)
                            <option value="{{ $stock->id }}" @selected((string) old('stock_boutique_id') === (string) $stock->id)>
                                {{ $stock->boutique->nom }} — {{ $stock->produit->nom }} — {{ $stock->lot->numero_lot }} ({{ $stock->quantite }} {{ __('disponible') }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="boutique_destination_id">{{ __('Boutique de destination') }}</label>
                    <select id="boutique_destination_id" name="boutique_destination_id" required>
                        <option value="">— {{ __('Choisir') }} —</option>
                        @foreach ($boutiques as $boutique)
                            <option value="{{ $boutique->id }}" @selected((string) old('boutique_destination_id') === (string) $boutique->id)>{{ $boutique->nom }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="quantite">{{ __('Quantité à transférer') }}</label>
                    <input id="quantite" type="number" min="1" name="quantite" value="{{ old('quantite') }}" required>
                </div>

                <button class="btn" type="submit">{{ __('Transférer') }}</button>
            </form>
        @endif
    </div>
@endsection
