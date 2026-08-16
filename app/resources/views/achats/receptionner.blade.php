@extends('layouts.app')

@section('title', __('Réceptionner') . ' — ' . $bonCommande->numero)

@section('content')
    <h1>{{ __('Réceptionner') }} — {{ $bonCommande->numero }}</h1>
    <p style="color:#555;">{{ __('Renseignez le numéro de lot et les dates pour chaque ligne. Le stock de la boutique sera mis à jour automatiquement.') }}</p>

    <div class="card">
        <form method="POST" action="{{ route('achats.receptionner.store', $bonCommande) }}">
            @csrf

            @foreach ($bonCommande->lignes as $ligne)
                <fieldset style="border:1px solid #e2e4e8;border-radius:8px;padding:1rem;margin-bottom:1rem;">
                    <legend style="font-weight:600;">
                        {{ $ligne->produit->nom }} — {{ $ligne->quantite }} {{ $ligne->produit->unite_mesure->label() }}
                        @if ($ligne->produit->type->tracabiliteObligatoire())
                            <span class="badge" title="{{ __('Traçabilité par lot obligatoire') }}">⚠ {{ __('traçabilité') }}</span>
                        @endif
                    </legend>

                    <div class="field">
                        <label for="numero_lot_{{ $ligne->id }}">{{ __('Numéro de lot') }}</label>
                        <input id="numero_lot_{{ $ligne->id }}" type="text" name="lignes[{{ $ligne->id }}][numero_lot]" value="{{ old("lignes.{$ligne->id}.numero_lot") }}" required>
                    </div>

                    <div class="field">
                        <label for="date_fabrication_{{ $ligne->id }}">{{ __('Date de fabrication') }}</label>
                        <input id="date_fabrication_{{ $ligne->id }}" type="date" name="lignes[{{ $ligne->id }}][date_fabrication]" value="{{ old("lignes.{$ligne->id}.date_fabrication") }}">
                    </div>

                    <div class="field">
                        <label for="date_peremption_{{ $ligne->id }}">
                            {{ __('Date de péremption') }}
                            @if ($ligne->produit->type->tracabiliteObligatoire()) * @endif
                        </label>
                        <input id="date_peremption_{{ $ligne->id }}" type="date" name="lignes[{{ $ligne->id }}][date_peremption]" value="{{ old("lignes.{$ligne->id}.date_peremption") }}" @required($ligne->produit->type->tracabiliteObligatoire())>
                    </div>
                </fieldset>
            @endforeach

            <button class="btn" type="submit">{{ __('Confirmer la réception') }}</button>
        </form>
    </div>
@endsection
