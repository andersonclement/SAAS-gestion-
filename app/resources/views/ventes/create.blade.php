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
                        <th>{{ __('Format') }}</th>
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
            <td>
                {{-- Formats de vente. « Détail » vend à l'unité de base au prix
                     catalogue ; les autres options portent leur propre prix et
                     multiplient la quantité par leur contenu. --}}
                <select name="lignes[__INDEX__][conditionnement_id]" class="conditionnement-select">
                    <option value="" data-facteur="1" data-prix="">{{ __('Détail') }}</option>
                    @foreach ($produits as $produit)
                        @foreach ($produit->conditionnements as $conditionnement)
                            <option value="{{ $conditionnement->id }}"
                                    data-produit="{{ $produit->id }}"
                                    data-facteur="{{ $conditionnement->facteur }}"
                                    data-prix="{{ $conditionnement->prix_vente }}"
                                    @selected($conditionnement->par_defaut)>
                                {{ $conditionnement->libelle }}
                            </option>
                        @endforeach
                    @endforeach
                </select>
            </td>
            <td><input type="number" min="1" class="quantite-input" name="lignes[__INDEX__][quantite]" value="1" required></td>
            <td class="sous-total-affiche">0</td>
            <td><button type="button" class="supprimer-ligne" style="background:none;border:none;color:#7a1f1f;cursor:pointer;">✕</button></td>
        </tr>
    </template>

    <script nonce="{{ $cspNonce }}">
        (function () {
            const body = document.getElementById('lignes-body');
            const template = document.getElementById('ligne-template');
            const totalAffiche = document.getElementById('total-affiche');
            let index = 0;

            // Les formats sont tous rendus dans le même <select> : on masque ceux
            // qui n'appartiennent pas au produit choisi, pour n'avoir qu'une
            // seule liste à maintenir côté serveur.
            function filtrerFormats(tr) {
                const produitId = tr.querySelector('.produit-select').value;
                const formats = tr.querySelector('.conditionnement-select');
                let selectionValide = false;

                Array.from(formats.options).forEach(function (option) {
                    const correspond = ! option.value || option.dataset.produit === produitId;
                    option.hidden = ! correspond;
                    if (correspond && option.selected) {
                        selectionValide = true;
                    }
                });

                if (! selectionValide) {
                    const defaut = Array.from(formats.options).find(function (option) {
                        return option.dataset.produit === produitId && option.defaultSelected;
                    });
                    formats.value = defaut ? defaut.value : '';
                }
            }

            function prixEtFacteur(tr) {
                const produit = tr.querySelector('.produit-select').selectedOptions[0];
                const format = tr.querySelector('.conditionnement-select').selectedOptions[0];

                if (format && format.value) {
                    return {
                        prix: parseInt(format.dataset.prix || '0', 10),
                        facteur: parseInt(format.dataset.facteur || '1', 10),
                    };
                }

                return { prix: parseInt(produit?.dataset.prix || '0', 10), facteur: 1 };
            }

            function sousTotalDe(tr) {
                const quantite = parseInt(tr.querySelector('.quantite-input').value, 10) || 0;

                return quantite * prixEtFacteur(tr).prix;
            }

            function recalculerLigne(tr) {
                filtrerFormats(tr);
                tr.querySelector('.sous-total-affiche').textContent = sousTotalDe(tr).toLocaleString('fr-FR');
                recalculerTotal();
            }

            function recalculerTotal() {
                let total = 0;
                body.querySelectorAll('tr').forEach(function (tr) {
                    total += sousTotalDe(tr);
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
            const montantPaye = document.getElementById('montant_paye');
            const dateEcheance = document.getElementById('date_echeance');
            function basculerChampsCredit() {
                champsCredit.style.display = caseACredit.checked ? 'block' : 'none';
                // Désactivés (et donc absents de l'envoi du formulaire) tant que la
                // vente n'est pas à crédit, pour que le paiement soit considéré
                // comptant par défaut (voir StoreVenteRequest::withValidator).
                montantPaye.disabled = ! caseACredit.checked;
                dateEcheance.disabled = ! caseACredit.checked;
            }
            caseACredit.addEventListener('change', basculerChampsCredit);
            basculerChampsCredit();
        })();
    </script>
    {{-- Caisse hors-ligne (§5) : si le réseau est tombé, la vente est mise en
         file sur l'appareil au lieu d'être perdue, puis remontée au retour du
         réseau. Le reste du formulaire est inchangé : le vendeur travaille de
         la même façon, connecté ou non. --}}
    <script nonce="{{ $cspNonce }}">
        // `hors-ligne.js` est chargé en differé (defer) : il s'exécute après
        // l'analyse du document, donc après ce bloc en ligne. On attend donc
        // DOMContentLoaded, qui suit les scripts différés, sans quoi
        // window.CaisseHorsLigne n'existerait pas encore ici.
        document.addEventListener('DOMContentLoaded', function () {
            if (! window.CaisseHorsLigne) {
                return;
            }

            const formulaire = document.querySelector('form[action="{{ route('ventes.store') }}"]');
            const body = document.getElementById('lignes-body');
            let prixCatalogue = {};
            let stockCatalogue = {};

            // L'instantané sert à deux choses hors réseau : facturer au bon prix
            // (promotions comprises) et prévenir le vendeur quand il dépasse le
            // stock connu — sans l'en empêcher, car c'est le rayon qui fait foi.
            window.CaisseHorsLigne.rafraichirCatalogue()
                .then(function (donnees) {
                    return donnees || window.CaisseHorsLigne.catalogue();
                })
                .then(function (donnees) {
                    if (! donnees || ! donnees.produits) {
                        return;
                    }

                    donnees.produits.forEach(function (produit) {
                        prixCatalogue[produit.id] = produit.prix;
                        stockCatalogue[produit.id] = produit.stock;
                    });
                });

            function prixDe(select) {
                const id = select.value;

                if (prixCatalogue[id] !== undefined) {
                    return prixCatalogue[id];
                }

                return parseInt(select.selectedOptions[0]?.dataset.prix || '0', 10);
            }

            function lignesSaisies() {
                const lignes = [];

                body.querySelectorAll('tr').forEach(function (tr) {
                    const select = tr.querySelector('.produit-select');
                    const quantite = parseInt(tr.querySelector('.quantite-input').value, 10) || 0;

                    if (select.value && quantite > 0) {
                        const format = tr.querySelector('.conditionnement-select');
                        const optionFormat = format.selectedOptions[0];

                        lignes.push({
                            produit_id: parseInt(select.value, 10),
                            conditionnement_id: format.value ? parseInt(format.value, 10) : null,
                            quantite: quantite,
                            facteur: optionFormat && format.value ? parseInt(optionFormat.dataset.facteur || '1', 10) : 1,
                            prix: format.value
                                ? parseInt(optionFormat.dataset.prix || '0', 10)
                                : prixDe(select),
                        });
                    }
                });

                return lignes;
            }

            formulaire.addEventListener('submit', function (evenement) {
                if (navigator.onLine) {
                    return;
                }

                evenement.preventDefault();

                const lignes = lignesSaisies();

                if (lignes.length === 0) {
                    window.alert("{{ __('Ajoutez au moins un article avant d\'enregistrer.') }}");

                    return;
                }

                const boutiqueId = parseInt(document.getElementById('boutique_id').value, 10);
                const modePaiement = document.getElementById('mode_paiement').value;

                if (! boutiqueId || ! modePaiement) {
                    window.alert("{{ __('Choisissez la boutique et le mode de paiement.') }}");

                    return;
                }

                const total = lignes.reduce(function (somme, ligne) {
                    return somme + (ligne.prix * ligne.quantite);
                }, 0);

                const aCredit = document.getElementById('vente-a-credit').checked;
                const clientId = document.getElementById('client_id').value;

                if (aCredit && ! clientId) {
                    window.alert("{{ __('Une vente à crédit exige un client identifié.') }}");

                    return;
                }

                const depassements = lignes.filter(function (ligne) {
                    return stockCatalogue[ligne.produit_id] !== undefined
                        && (ligne.quantite * ligne.facteur) > stockCatalogue[ligne.produit_id];
                });

                if (depassements.length > 0
                    && ! window.confirm("{{ __('La quantité saisie dépasse le stock connu de cet appareil. Enregistrer quand même ?') }}")) {
                    return;
                }

                window.CaisseHorsLigne.enregistrer({
                    boutique_id: boutiqueId,
                    client_id: clientId ? parseInt(clientId, 10) : null,
                    mode_paiement: modePaiement,
                    montant_paye: aCredit
                        ? (parseInt(document.getElementById('montant_paye').value, 10) || 0)
                        : total,
                    date_echeance: aCredit ? (document.getElementById('date_echeance').value || null) : null,
                    lignes: lignes.map(function (ligne) {
                        return {
                            produit_id: ligne.produit_id,
                            conditionnement_id: ligne.conditionnement_id,
                            quantite: ligne.quantite,
                        };
                    }),
                }).then(function () {
                    formulaire.reset();
                    body.innerHTML = '';
                    document.getElementById('ajouter-ligne').click();
                    document.getElementById('total-affiche').textContent = '0';
                    window.alert("{{ __('Vente enregistrée sur cet appareil. Elle sera envoyée dès le retour du réseau.') }}");
                });
            });
        });
    </script>
@endsection
