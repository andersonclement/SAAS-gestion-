@extends('layouts.app')

@section('title', __('Nouveau versement'))

@section('content')
    <h1>{{ __('Nouveau versement') }}</h1>
    <p style="color:#555;margin-top:-.5rem;">{{ __('Enregistrez ici l\'argent que vous venez de récupérer physiquement auprès d\'un gérant ou vendeur.') }}</p>

    <div class="card" style="max-width:480px;">
        <form method="POST" action="{{ route('versements.store') }}">
            @csrf

            <div class="field">
                <label for="boutique_id">{{ __('Boutique') }}</label>
                <select id="boutique_id" name="boutique_id" required>
                    <option value="">— {{ __('Choisir') }} —</option>
                    @foreach ($boutiques as $boutique)
                        <option value="{{ $boutique->id }}" @selected((string) old('boutique_id') === (string) $boutique->id)>{{ $boutique->nom }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="remis_par_id">{{ __('Remis par') }}</label>
                <select id="remis_par_id" name="remis_par_id" required>
                    <option value="">— {{ __('Choisir une boutique d\'abord') }} —</option>
                    @foreach ($boutiques as $boutique)
                        @foreach ($boutique->utilisateurs as $membre)
                            <option value="{{ $membre->id }}" data-boutique="{{ $boutique->id }}" @selected((string) old('remis_par_id') === (string) $membre->id)>{{ $membre->name }} ({{ $membre->role->label() }})</option>
                        @endforeach
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="montant">{{ __('Montant reçu (FCFA)') }}</label>
                <input id="montant" type="number" min="1" name="montant" value="{{ old('montant') }}" required>
            </div>

            <div class="field">
                <label for="date">{{ __('Date') }}</label>
                <input id="date" type="date" name="date" value="{{ old('date', now()->toDateString()) }}" required>
            </div>

            <div class="field">
                <label for="description">{{ __('Description (optionnel)') }}</label>
                <input id="description" type="text" name="description" value="{{ old('description') }}">
            </div>

            <button class="btn" type="submit">{{ __('Enregistrer le versement') }}</button>
        </form>
    </div>

    <script nonce="{{ $cspNonce }}">
        (function () {
            const boutiqueSelect = document.getElementById('boutique_id');
            const remisParSelect = document.getElementById('remis_par_id');
            const toutesLesOptions = Array.from(remisParSelect.options);

            function filtrerOptions() {
                remisParSelect.innerHTML = '';
                remisParSelect.appendChild(toutesLesOptions[0].cloneNode(true));
                toutesLesOptions.slice(1).forEach(function (option) {
                    if (option.dataset.boutique === boutiqueSelect.value) {
                        remisParSelect.appendChild(option.cloneNode(true));
                    }
                });
            }

            boutiqueSelect.addEventListener('change', filtrerOptions);
            filtrerOptions();
        })();
    </script>
@endsection
