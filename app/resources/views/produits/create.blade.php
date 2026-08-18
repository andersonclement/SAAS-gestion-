@extends('layouts.app')

@section('title', __('Nouveau produit'))

@section('content')
    <h1>{{ __('Nouveau produit') }}</h1>

    <div class="card" style="max-width:520px;">
        <form method="POST" action="{{ route('produits.store') }}">
            @csrf

            <div class="field">
                <label for="nom">{{ __('Nom du produit') }}</label>
                <input id="nom" type="text" name="nom" value="{{ old('nom') }}" required autofocus>
            </div>

            <div class="field">
                <label for="type">{{ __('Type') }}</label>
                <select id="type" name="type" required>
                    <option value="">— {{ __('Choisir') }} —</option>
                    @foreach (\App\Enums\TypeProduit::cases() as $type)
                        <option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="categorie_id">{{ __('Catégorie') }}</label>
                <select id="categorie_id" name="categorie_id">
                    <option value="">— {{ __('Aucune') }} —</option>
                    @foreach ($categories as $categorie)
                        <option value="{{ $categorie->id }}" @selected((string) old('categorie_id') === (string) $categorie->id)>{{ $categorie->nom }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="unite_mesure">{{ __('Unité de mesure') }}</label>
                <select id="unite_mesure" name="unite_mesure" required>
                    <option value="">— {{ __('Choisir') }} —</option>
                    @foreach (\App\Enums\UniteMesure::cases() as $unite)
                        <option value="{{ $unite->value }}" @selected(old('unite_mesure') === $unite->value)>{{ $unite->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="code_barres">{{ __('Code-barres / QR code (optionnel)') }}</label>
                <input id="code_barres" type="text" name="code_barres" value="{{ old('code_barres') }}">
            </div>

            <div class="field">
                <label for="prix_achat">{{ __("Prix d'achat (FCFA)") }}</label>
                <input id="prix_achat" type="number" min="0" name="prix_achat" value="{{ old('prix_achat', 0) }}" required>
            </div>

            <div class="field">
                <label for="prix_vente">{{ __('Prix de vente au détail (FCFA par unité de mesure)') }}</label>
                <input id="prix_vente" type="number" min="0" name="prix_vente" value="{{ old('prix_vente') }}">
                <small style="color:#555;display:block;">
                    {{ __("Laisser vide si le produit ne s'ouvre pas au comptoir : déclarez alors ses formats ci-dessous. Un produit doit avoir au moins un prix pour être vendable.") }}
                </small>
                @error('prix_vente')<small style="color:#7a1f1f;display:block;">{{ $message }}</small>@enderror
            </div>

            <div class="field">
                <label for="stock_min">{{ __('Stock minimum') }}</label>
                <input id="stock_min" type="number" min="0" name="stock_min" value="{{ old('stock_min', 0) }}" required>
                <small style="color:#56606b;">{{ __("Une alerte s'affiche dès que le stock descend à ce niveau.") }}</small>
            </div>

            <div class="field">
                <label for="stock_max">{{ __('Stock maximum') }}</label>
                <input id="stock_max" type="number" min="1" name="stock_max" value="{{ old('stock_max') }}" required>
                <small style="color:#56606b;">{{ __('Quantité à ne pas dépasser en boutique ; au-delà, le produit est signalé en surstock.') }}</small>
            </div>


            <h2 style="font-size:1.05rem;margin:1.75rem 0 .25rem;">{{ __('Formats de vente') }}</h2>
            <p style="color:#56606b;font-size:.9rem;margin-top:0;">
                {{ __("Carton, sachet, sac… chaque format porte son propre prix, fixé ici une fois pour toutes. Le stock reste compté dans l'unité de mesure choisie plus haut : un format n'est qu'une façon de vendre, pas un stock à part.") }}
            </p>

            <div id="formats-body"></div>

            @error('conditionnements')<small style="color:#7a1f1f;display:block;">{{ $message }}</small>@enderror
            @foreach ($errors->get('conditionnements.*') as $messages)
                @foreach ($messages as $message)
                    <small style="color:#7a1f1f;display:block;">{{ $message }}</small>
                @endforeach
            @endforeach

            <p><button type="button" id="ajouter-format" class="btn" style="background:#555;">+ {{ __('Ajouter un format') }}</button></p>

            {{-- Chaque format est un bloc de champs empilables, et non une ligne
                 de table : au format téléphone une table déborde et pousse la
                 colonne du prix hors de l'écran — or c'est précisément le champ
                 qu'on vient saisir. --}}
            <template id="format-template">
                <div class="format-ligne" style="border:1px solid #e3e6ea;border-radius:6px;padding:.75rem;margin-bottom:.75rem;">
                    <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
                        <div class="field" style="flex:3 1 11rem;margin-bottom:0;">
                            <label for="format___INDEX___libelle">{{ __('Nom du format') }}</label>
                            <input id="format___INDEX___libelle" type="text" maxlength="60"
                                   name="conditionnements[__INDEX__][libelle]" placeholder="{{ __('Carton de 24') }}">
                        </div>
                        <div class="field" style="flex:1 1 7rem;margin-bottom:0;">
                            {{-- Nombre d'unités de base contenues : c'est lui qui
                                 fait le lien entre le format et le stock. --}}
                            <label for="format___INDEX___facteur">
                                {{ __('Contenu') }} <span class="unite-affichee" style="font-weight:400;color:#56606b;"></span>
                            </label>
                            <input id="format___INDEX___facteur" type="number" min="1"
                                   name="conditionnements[__INDEX__][facteur]" placeholder="24">
                        </div>
                        <div class="field" style="flex:1 1 8rem;margin-bottom:0;">
                            <label for="format___INDEX___prix">{{ __('Prix (FCFA)') }}</label>
                            <input id="format___INDEX___prix" type="number" min="0"
                                   name="conditionnements[__INDEX__][prix_vente]" placeholder="12000">
                        </div>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;margin-top:.6rem;">
                        <label style="font-size:.85rem;font-weight:400;display:flex;align-items:center;gap:.4rem;">
                            <input type="radio" name="conditionnement_par_defaut" value="__INDEX__"
                                   style="width:auto;min-height:0;margin:0;">
                            {{ __('Proposé par défaut à la caisse') }}
                        </label>
                        <button type="button" class="supprimer-format"
                                style="background:none;border:none;color:#7a1f1f;cursor:pointer;font-size:.85rem;">
                            ✕ {{ __('Retirer') }}
                        </button>
                    </div>
                </div>
            </template>

            <h2 style="font-size:1.05rem;margin:1.75rem 0 .25rem;">{{ __('Stock initial') }}</h2>
            <p style="color:#56606b;font-size:.9rem;margin-top:0;">
                {{ __('Le produit entre au catalogue avec sa première quantité en boutique et la date de péremption de ce lot.') }}
            </p>

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
                <label for="quantite_initiale">{{ __('Quantité en stock') }}</label>
                <input id="quantite_initiale" type="number" min="1" name="quantite_initiale" value="{{ old('quantite_initiale') }}" required>
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
                <input id="date_peremption" type="date" name="date_peremption" value="{{ old('date_peremption') }}">
                <small style="color:#56606b;">
                    {{ __("Une alerte est levée à l'approche de cette date. Obligatoire pour les produits phytosanitaires ; à laisser vide pour un produit qui ne périme pas (outillage, matériel).") }}
                </small>
            </div>

            <button class="btn" type="submit">{{ __('Créer le produit') }}</button>
        </form>
    </div>

    <div class="card" style="max-width:520px;">
        <h2 style="margin-top:0;font-size:1.1rem;">{{ __('Ajouter une catégorie') }}</h2>
        <form method="POST" action="{{ route('categories.store') }}" style="display:flex;gap:.5rem;align-items:end;">
            @csrf
            <div class="field" style="flex:1;margin-bottom:0;">
                <label for="categorie_nom">{{ __('Nom') }}</label>
                <input id="categorie_nom" type="text" name="nom">
            </div>
            <button class="btn" type="submit">{{ __('Ajouter') }}</button>
        </form>
    </div>

    <script nonce="{{ $cspNonce }}">
        (function () {
            const body = document.getElementById('formats-body');
            const template = document.getElementById('format-template');
            const unite = document.getElementById('unite_mesure');
            let index = 0;

            // Le contenu s'exprime dans l'unité de base : « 24 sachets »,
            // « 50 kg ». L'afficher évite la confusion la plus coûteuse — saisir
            // un carton de 24 quand le stock se compte en kilos.
            function rafraichirUnite() {
                const libelle = unite.value || '';
                body.querySelectorAll('.unite-affichee').forEach(function (span) {
                    span.textContent = libelle === '' ? '' : '(' + libelle + ')';
                });
            }

            function ajouterLigne(valeurs) {
                const html = template.innerHTML.replaceAll('__INDEX__', index);
                const wrapper = document.createElement('template');
                wrapper.innerHTML = html.trim();
                const ligne = wrapper.content.firstElementChild;

                if (valeurs) {
                    ['libelle', 'facteur', 'prix_vente'].forEach(function (champ) {
                        const input = ligne.querySelector('[name$="[' + champ + ']"]');
                        if (input && valeurs[champ] !== undefined && valeurs[champ] !== null) {
                            input.value = valeurs[champ];
                        }
                    });
                    if (valeurs.par_defaut) {
                        ligne.querySelector('input[type=radio]').checked = true;
                    }
                }

                body.appendChild(ligne);
                index++;
                rafraichirUnite();
            }

            document.getElementById('ajouter-format').addEventListener('click', function () { ajouterLigne(null); });

            body.addEventListener('click', function (evenement) {
                if (evenement.target.closest('.supprimer-format')) {
                    evenement.target.closest('.format-ligne').remove();
                }
            });

            unite.addEventListener('change', rafraichirUnite);

            // Après un refus de validation, on redonne au patron ce qu'il avait
            // saisi : lui faire retaper ses formats serait la meilleure façon de
            // le décourager d'en déclarer.
            const anciens = @json(collect(old('conditionnements', []))->values());
            const ancienDefaut = @json(old('conditionnement_par_defaut'));

            if (anciens.length > 0) {
                anciens.forEach(function (format, rang) {
                    ajouterLigne({
                        libelle: format.libelle,
                        facteur: format.facteur,
                        prix_vente: format.prix_vente,
                        par_defaut: String(ancienDefaut) === String(rang),
                    });
                });
            } else {
                ajouterLigne(null);
            }
        })();
    </script>
@endsection
