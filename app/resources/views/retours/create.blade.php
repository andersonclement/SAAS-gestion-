@extends('layouts.app')

@section('title', __('Enregistrer un retour'))

@section('content')
    <h1>{{ __('Enregistrer un retour') }}</h1>

    <div class="card" style="max-width:480px;">
        <p><strong>{{ __('Produit') }} :</strong> {{ $ligneVente->produit->nom }}</p>
        <p><strong>{{ __('Lot') }} :</strong> {{ $ligneVente->lot->numero_lot }}</p>
        <p><strong>{{ __('Vendu') }} :</strong> {{ $ligneVente->quantite }} — <strong>{{ __('Retournable') }} :</strong> {{ $ligneVente->quantiteRetournable() }}</p>

        <form method="POST" action="{{ route('retours.store', $ligneVente) }}">
            @csrf

            <div class="field">
                <label for="quantite">{{ __('Quantité retournée') }}</label>
                <input id="quantite" type="number" min="1" max="{{ $ligneVente->quantiteRetournable() }}" name="quantite" value="1" required>
            </div>

            <div class="field">
                <label for="motif">{{ __('Motif') }}</label>
                <select id="motif" name="motif" required>
                    @foreach (\App\Enums\MotifRetour::cases() as $motif)
                        <option value="{{ $motif->value }}" @selected(old('motif') === $motif->value)>{{ $motif->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label><input type="checkbox" name="remis_en_stock" value="1" @checked(old('remis_en_stock'))> {{ __('Remettre en stock (produit revendable)') }}</label>
            </div>

            <div class="field">
                <label for="mode_remboursement">{{ __('Mode de remboursement') }}</label>
                <select id="mode_remboursement" name="mode_remboursement" required>
                    @foreach (\App\Enums\ModeRemboursement::cases() as $mode)
                        <option value="{{ $mode->value }}" @selected(old('mode_remboursement') === $mode->value)>{{ $mode->label() }}</option>
                    @endforeach
                </select>
            </div>

            <button class="btn" type="submit">{{ __('Enregistrer le retour') }}</button>
        </form>
    </div>
@endsection
