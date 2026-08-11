@extends('layouts.app')

@section('title', __('Nouvelle vente'))

@section('content')
    <h1>{{ __('Nouvelle vente') }}</h1>

    <div class="card" style="max-width:720px;">
        <form method="POST" action="{{ route('ventes.store') }}">
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
                <label for="client_id">{{ __('Client (optionnel)') }}</label>
                <select id="client_id" name="client_id">
                    <option value="">— {{ __('Client de passage') }} —</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected((string) old('client_id') === (string) $client->id)>{{ $client->nom }}</option>
                    @endforeach
                </select>
            </div>

            <h2 style="font-size:1.1rem;">{{ __('Articles') }}</h2>
            <table id="lignes-table">
                <thead>
                    <tr>
                        <th>{{ __('Produit') }}</th>
                        <th>{{ __('Quantité') }}</th>
                        <th>{{ __('Sous-total') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="lignes-body"></tbody>
            </table>

            <p><button type="button" id="ajouter-ligne" class="btn" style="background:#555;">+ {{ __('Ajouter un article') }}</button></p>

            <p style="text-align:right;font-weight:700;">{{ __('Total') }} : <span id="total-affiche">0</span> FCFA</p>

            <div class="field">
                <label for="mode_paiement">{{ __('Mode de paiement') }}</label>
                <select id="mode_paiement" name="mode_paiement" required>
                    <option value="">— {{ __('Choisir') }} —</option>
                    @foreach (\App\Enums\ModePaiement::cases() as $mode)
                        <option value="{{ $mode->value }}" @selected(old('mode_paiement') === $mode->value)>{{ $mode->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label><input type="checkbox" id="vente-a-credit" @checked(old('montant_paye') !== null)> {{ __('Vente à crédit (paiement partiel)') }}</label>
            </div>

            <div id="champs-credit" style="display:none;">
                <div class="field">
                    <label for="montant_paye">{{ __('Montant payé maintenant (FCFA)') }}</label>
                    <input id="montant_paye" type="number" min="0" name="montant_paye" value="{{ old('montant_paye', 0) }}">
                </div>
                <div class="field">
                    <label for="date_echeance">{{ __("Date d'échéance") }}</label>
                    <input id="date_echeance" type="date" name="date_echeance" value="{{ old('date_echeance') }}">
                </div>
                <p style="color:#555;font-size:.85rem;">{{ __('Un client doit être sélectionné et disposer d\'un plafond de crédit suffisant.') }}</p>
            </div>

            <button class="btn" type="submit">{{ __('Enregistrer la vente') }}</button>
        </form>
    </div>

    <template id="ligne-template">
        <tr>
            <td>
                <select name="lignes[__INDEX__][produit_id]" class="produit-select" required>
                    <option value="">— {{ __('Choisir') }} —</option>
                    @foreach ($produits as $produit)
                        <option value="{{ $produit->id }}" data-prix="{{ $produit->prix_vente }}">{{ $produit->nom }} ({{ number_format($produit->prix_vente, 0, ',', ' ') }} FCFA)</option>
                    @endforeach
                </select>
            </td>
            <td><input type="number" min="1" class="quantite-input" name="lignes[__INDEX__][quantite]" value="1" required></td>
            <td class="sous-total-affiche">0</td>
            <td><button type="button" class="supprimer-ligne" style="background:none;border:none;color:#7a1f1f;cursor:pointer;">✕</button></td>
        </tr>
    </template>

    <script>
        (function () {
            const body = document.getElementById('lignes-body');
            const template = document.getElementById('ligne-template');
            const totalAffiche = document.getElementById('total-affiche');
            let index = 0;

            function recalculerLigne(tr) {
                const select = tr.querySelector('.produit-select');
                const quantite = parseInt(tr.querySelector('.quantite-input').value, 10) || 0;
                const prix = parseInt(select.selectedOptions[0]?.dataset.prix || '0', 10);
                tr.querySelector('.sous-total-affiche').textContent = (quantite * prix).toLocaleString('fr-FR');
                recalculerTotal();
            }

            function recalculerTotal() {
                let total = 0;
                body.querySelectorAll('tr').forEach(function (tr) {
                    const select = tr.querySelector('.produit-select');
                    const quantite = parseInt(tr.querySelector('.quantite-input').value, 10) || 0;
                    const prix = parseInt(select.selectedOptions[0]?.dataset.prix || '0', 10);
                    total += quantite * prix;
                });
                totalAffiche.textContent = total.toLocaleString('fr-FR');
            }

            function ajouterLigne() {
                const html = template.innerHTML.replaceAll('__INDEX__', index);
                const wrapper = document.createElement('template');
                wrapper.innerHTML = html.trim();
                const tr = wrapper.content.firstElementChild;
                body.appendChild(tr);
                tr.addEventListener('change', function () { recalculerLigne(tr); });
                tr.addEventListener('input', function () { recalculerLigne(tr); });
                index++;
            }

            document.getElementById('ajouter-ligne').addEventListener('click', ajouterLigne);

            body.addEventListener('click', function (event) {
                if (event.target.classList.contains('supprimer-ligne')) {
                    event.target.closest('tr').remove();
                    recalculerTotal();
                }
            });

            ajouterLigne();

            const caseACredit = document.getElementById('vente-a-credit');
            const champsCredit = document.getElementById('champs-credit');
            function basculerChampsCredit() {
                champsCredit.style.display = caseACredit.checked ? 'block' : 'none';
            }
            caseACredit.addEventListener('change', basculerChampsCredit);
            basculerChampsCredit();
        })();
    </script>
@endsection
