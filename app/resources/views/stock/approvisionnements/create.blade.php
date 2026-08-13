@extends('layouts.app')

@section('title', __('Entrée de stock'))

@section('content')
    <p><a href="{{ route('stock.index') }}">&larr; {{ __('Stock') }}</a></p>
    <h1 style="margin-top:0;">{{ __('Entrée de stock') }}</h1>
    <p style="color:#56606b;">
        {{ __("Enregistrez une quantité reçue en boutique. L'opération ne peut pas être modifiée ensuite : un écart constaté se corrige par un inventaire.") }}
    </p>

    @if ($produits->isEmpty())
        <div class="card">
            <p>{{ __('Aucun produit au catalogue. Créez-en un : son stock initial sera enregistré dans la foulée.') }}</p>
            @can('create', App\Models\Produit::class)
                <p><a class="btn" href="{{ route('produits.create') }}">+ {{ __('Nouveau produit') }}</a></p>
            @endcan
        </div>
    @else
        <div class="card" style="max-width:520px;">
            <form method="POST" action="{{ route('stock.approvisionnements.store') }}">
                @csrf

                <div class="field">
                    <label for="boutique_id">{{ __('Boutique') }}</label>
                    <select id="boutique_id" name="boutique_id" required>
                        @if ($boutiques->count() !== 1)
                            <option value="">— {{ __('Choisir') }} —</option>
                        @endif
                        @foreach ($boutiques as $boutique)
                            <option value="{{ $boutique->id }}" @selected((string) old('boutique_id', $boutiques->count() === 1 ? $boutiques->first()->id : '') === (string) $boutique->id)>
                                {{ $boutique->nom }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="produit_id">{{ __('Produit') }}</label>
                    <select id="produit_id" name="produit_id" required autofocus>
                        <option value="">— {{ __('Choisir') }} —</option>
                        @foreach ($produits as $produit)
                            <option value="{{ $produit->id }}" @selected((string) old('produit_id') === (string) $produit->id)>
                                {{ $produit->nom }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="quantite">{{ __('Quantité reçue') }}</label>
                    <input id="quantite" type="number" min="1" name="quantite" value="{{ old('quantite') }}" required>
                </div>

                <div class="field">
                    <label for="numero_lot">{{ __('Numéro de lot') }}</label>
                    <input id="numero_lot" type="text" name="numero_lot" value="{{ old('numero_lot') }}" required>
                </div>

                <div class="field">
                    <label for="date_fabrication">{{ __('Date de fabrication (optionnelle)') }}</label>
                    <input id="date_fabrication" type="date" name="date_fabrication" value="{{ old('date_fabrication') }}">
                </div>

                <div class="field">
                    <label for="date_peremption">{{ __('Date de péremption') }}</label>
                    <input id="date_peremption" type="date" name="date_peremption" value="{{ old('date_peremption') }}" required>
                </div>

                <button class="btn" type="submit">{{ __('Enregistrer le stock') }}</button>
            </form>
        </div>
    @endif
@endsection
