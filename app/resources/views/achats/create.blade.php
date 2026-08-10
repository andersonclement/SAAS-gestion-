@extends('layouts.app')

@section('title', __('Nouveau bon de commande'))

@section('content')
    <h1>{{ __('Nouveau bon de commande') }}</h1>

    <div class="card" style="max-width:720px;">
        <form method="POST" action="{{ route('achats.store') }}">
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
                <label for="fournisseur_id">{{ __('Fournisseur') }}</label>
                <select id="fournisseur_id" name="fournisseur_id" required>
                    <option value="">— {{ __('Choisir') }} —</option>
                    @foreach ($fournisseurs as $fournisseur)
                        <option value="{{ $fournisseur->id }}" @selected((string) old('fournisseur_id') === (string) $fournisseur->id)>{{ $fournisseur->nom }}</option>
                    @endforeach
                </select>
            </div>

            <h2 style="font-size:1.1rem;">{{ __('Lignes de commande') }}</h2>
            <table id="lignes-table">
                <thead>
                    <tr>
                        <th>{{ __('Produit') }}</th>
                        <th>{{ __('Quantité') }}</th>
                        <th>{{ __("Prix unitaire (FCFA)") }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="lignes-body"></tbody>
            </table>

            <p><button type="button" id="ajouter-ligne" class="btn" style="background:#555;">+ {{ __('Ajouter une ligne') }}</button></p>

            <button class="btn" type="submit">{{ __('Créer le bon de commande') }}</button>
        </form>
    </div>

    <template id="ligne-template">
        <tr>
            <td>
                <select name="lignes[__INDEX__][produit_id]" required>
                    <option value="">— {{ __('Choisir') }} —</option>
                    @foreach ($produits as $produit)
                        <option value="{{ $produit->id }}">{{ $produit->nom }}</option>
                    @endforeach
                </select>
            </td>
            <td><input type="number" min="1" name="lignes[__INDEX__][quantite]" required></td>
            <td><input type="number" min="0" name="lignes[__INDEX__][prix_unitaire]" required></td>
            <td><button type="button" class="supprimer-ligne" style="background:none;border:none;color:#7a1f1f;cursor:pointer;">✕</button></td>
        </tr>
    </template>

    <script>
        (function () {
            const body = document.getElementById('lignes-body');
            const template = document.getElementById('ligne-template');
            let index = 0;

            function ajouterLigne() {
                const html = template.innerHTML.replaceAll('__INDEX__', index);
                const tr = document.createElement('template');
                tr.innerHTML = html.trim();
                body.appendChild(tr.content.firstElementChild);
                index++;
            }

            document.getElementById('ajouter-ligne').addEventListener('click', ajouterLigne);

            body.addEventListener('click', function (event) {
                if (event.target.classList.contains('supprimer-ligne')) {
                    event.target.closest('tr').remove();
                }
            });

            ajouterLigne();
        })();
    </script>
@endsection
