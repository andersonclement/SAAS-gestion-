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
                    {{-- Un vendeur n'a qu'une boutique : lui demander de la
                         choisir dans une liste d'un seul élément ajoute un
                         geste à chaque vente, et le formulaire refuse de
                         partir sans autre explication qu'une bulle du
                         navigateur — juste après un scan, on ne voit pas
                         pourquoi. --}}
                    @if ($boutiques->count() > 1)
                        <option value="">— {{ __('Choisir') }} —</option>
                    @endif
                    @foreach ($boutiques as $boutique)
                        <option value="{{ $boutique->id }}"
                                @selected($boutiques->count() === 1 || (string) old('boutique_id') === (string) $boutique->id)>
                            {{ $boutique->nom }}
                        </option>
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

            {{-- Scan du code-barres. Un lecteur USB se comporte comme un
                 clavier : il tape les chiffres puis Entrée. Le champ n'a pas
                 de « name » — il ne part pas avec le formulaire, il ne sert
                 qu'à remplir le tableau ci-dessous. --}}
            <div class="field">
                <label for="scan">{{ __('Code-barres') }}</label>
                <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                    <input id="scan" type="text" inputmode="numeric" autocomplete="off" autofocus
                           style="flex:1;min-width:12rem;"
                           placeholder="{{ __('Scannez, ou tapez le code puis Entrée') }}">
                    <button type="button" id="scan-camera" class="btn" style="background:#555;" hidden>
                        {{ __('Caméra') }}
                    </button>
                </div>
                <small id="scan-message" style="color:#56606b;"></small>
                <video id="scan-video" playsinline muted hidden
                       style="width:100%;max-width:320px;margin-top:.5rem;border-radius:4px;background:#000;"></video>
            </div>

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
                        <option value="{{ $produit->id }}"
                                data-prix="{{ $produit->prix_vente }}"
                                data-code-barres="{{ $produit->code_barres }}"
                                data-detaillable="{{ $produit->estDetaillable() ? '1' : '0' }}">
                            {{ $produit->nom }}
                            @if ($produit->estDetaillable())
                                ({{ number_format($produit->prix_vente, 0, ',', ' ') }} FCFA / {{ $produit->unite_mesure->value }})
                            @else
                                ({{ __('formats uniquement') }})
                            @endif
                        </option>
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
                const produit = tr.querySelector('.produit-select').selectedOptions[0];
                const produitId = tr.querySelector('.produit-select').value;
                const formats = tr.querySelector('.conditionnement-select');
                // Un produit sans prix au détail ne s'ouvre pas au comptoir :
                // l'option « Détail » n'est même pas proposée. Le serveur refuse
                // de toute façon la ligne — ceci évite au vendeur de le
                // découvrir après avoir tout saisi.
                const detaillable = ! produit || produit.dataset.detaillable !== '0';
                let selectionValide = false;

                Array.from(formats.options).forEach(function (option) {
                    const correspond = option.value
                        ? option.dataset.produit === produitId
                        : detaillable;
                    option.hidden = ! correspond;
                    option.disabled = ! correspond;
                    if (correspond && option.selected) {
                        selectionValide = true;
                    }
                });

                if (! selectionValide) {
                    const disponibles = Array.from(formats.options).filter(function (option) {
                        return ! option.disabled;
                    });
                    const defaut = disponibles.find(function (option) {
                        return option.defaultSelected;
                    });
                    formats.value = (defaut || disponibles[0] || { value: '' }).value;
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

            // ── Scan du code-barres ────────────────────────────────────────
            const champScan = document.getElementById('scan');
            const messageScan = document.getElementById('scan-message');
            const codesBarres = {};

            // L'index se construit sur les options déjà rendues : le catalogue
            // n'est donc écrit qu'une fois dans la page.
            function indexer(source) {
                source.forEach(function (entree) {
                    if (entree.code_barres) {
                        codesBarres[String(entree.code_barres)] = String(entree.id);
                    }
                });
            }

            indexer(Array.from(template.content.querySelectorAll('.produit-select option'))
                .filter(function (option) { return option.value; })
                .map(function (option) {
                    return { id: option.value, code_barres: option.dataset.codeBarres };
                }));

            function annoncer(texte, erreur) {
                messageScan.textContent = texte;
                messageScan.style.color = erreur ? '#7a1f1f' : '#1f5f3a';
            }

            // Une ligne déjà ouverte sur ce produit, au format « Détail » :
            // scanner deux fois le même article doit passer la quantité à 2,
            // pas ouvrir une seconde ligne à côté de la première.
            function ligneExistante(produitId) {
                return Array.from(body.querySelectorAll('tr')).find(function (tr) {
                    return tr.querySelector('.produit-select').value === produitId
                        && ! tr.querySelector('.conditionnement-select').value;
                });
            }

            function scanner(code) {
                const produitId = codesBarres[String(code).trim()];

                if (! produitId) {
                    annoncer("{{ __('Code inconnu :') }} " + code, true);

                    return;
                }

                let tr = ligneExistante(produitId);

                if (tr) {
                    const quantite = tr.querySelector('.quantite-input');
                    quantite.value = (parseInt(quantite.value, 10) || 0) + 1;
                } else {
                    // Une première ligne vide est ouverte au chargement : on la
                    // reprend plutôt que d'en empiler une seconde par-dessus.
                    tr = Array.from(body.querySelectorAll('tr')).find(function (ligne) {
                        return ! ligne.querySelector('.produit-select').value;
                    });

                    if (! tr) {
                        ajouterLigne();
                        tr = body.lastElementChild;
                    }

                    const select = tr.querySelector('.produit-select');
                    select.value = produitId;

                    // L'instantané hors-ligne peut connaître un produit que
                    // cette page, mise en cache plus tôt, ne propose pas encore.
                    // Affecter une valeur absente vide silencieusement le
                    // <select> : mieux vaut le dire que vendre au hasard.
                    if (select.value !== produitId) {
                        annoncer("{{ __('Produit absent de cette page. Rechargez la caisse.') }}", true);

                        return;
                    }
                }

                recalculerLigne(tr);

                // Le libellé de l'option porte le prix sur une seconde ligne :
                // on n'annonce que le nom, pour que le vendeur vérifie d'un
                // coup d'œil qu'il a scanné le bon article.
                const libelle = tr.querySelector('.produit-select').selectedOptions[0].textContent.trim().split('\n')[0];
                annoncer(libelle.trim(), false);
            }

            champScan.addEventListener('keydown', function (evenement) {
                if (evenement.key !== 'Enter') {
                    return;
                }

                // Sans cela, la touche Entrée du lecteur enverrait la vente
                // dès le premier article scanné.
                evenement.preventDefault();

                if (champScan.value.trim() !== '') {
                    scanner(champScan.value);
                    champScan.value = '';
                }
            });

            // Lecture par la caméra, là où le navigateur sait décoder lui-même.
            // Pas de bibliothèque tierce : la politique de sécurité de contenu
            // interdit tout script externe, et embarquer un décodeur alourdirait
            // une page qui doit rester utilisable hors réseau.
            const boutonCamera = document.getElementById('scan-camera');
            const video = document.getElementById('scan-video');
            let flux = null;

            function arreterCamera() {
                if (flux) {
                    flux.getTracks().forEach(function (piste) { piste.stop(); });
                    flux = null;
                }

                video.hidden = true;
                boutonCamera.textContent = "{{ __('Caméra') }}";
            }

            if ('BarcodeDetector' in window && navigator.mediaDevices) {
                boutonCamera.hidden = false;

                boutonCamera.addEventListener('click', function () {
                    if (flux) {
                        arreterCamera();

                        return;
                    }

                    const detecteur = new window.BarcodeDetector({
                        formats: ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128'],
                    });

                    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
                        .then(function (obtenu) {
                            flux = obtenu;
                            video.srcObject = flux;
                            video.hidden = false;
                            boutonCamera.textContent = "{{ __('Arrêter') }}";

                            return video.play();
                        })
                        .then(function () {
                            function lire() {
                                if (! flux) {
                                    return;
                                }

                                detecteur.detect(video)
                                    .then(function (codes) {
                                        if (codes.length > 0) {
                                            scanner(codes[0].rawValue);
                                            // La caméra s'arrête dès qu'un code
                                            // est lu : la laisser tourner vide
                                            // la batterie du téléphone.
                                            arreterCamera();

                                            return;
                                        }

                                        window.requestAnimationFrame(lire);
                                    })
                                    .catch(function () { window.requestAnimationFrame(lire); });
                            }

                            lire();
                        })
                        .catch(function () {
                            arreterCamera();
                            annoncer("{{ __('Caméra indisponible. Utilisez le lecteur ou la liste.') }}", true);
                        });
                });
            }

            // La caisse hors-ligne rafraîchit l'index avec son instantané : le
            // scan doit tenir même quand la page vient du cache.
            window.CaisseScan = { indexer: indexer };

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

                    // Un produit ajouté au catalogue depuis la mise en cache de
                    // cette page ne figure pas dans ses options : l'instantané
                    // remet l'index du scan à jour.
                    if (window.CaisseScan) {
                        window.CaisseScan.indexer(donnees.produits);
                    }
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
