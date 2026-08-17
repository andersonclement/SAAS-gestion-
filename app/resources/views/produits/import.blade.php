@extends('layouts.app')

@section('title', __('Importer un catalogue'))

@section('content')
    <h1>{{ __('Importer un catalogue') }}</h1>

    {{-- Le rapport d'erreurs passe par la session plutôt que par le sac
         d'erreurs de validation : il n'y a pas de champ de formulaire auquel
         rattacher « ligne 47 », et le patron doit pouvoir tout lire d'un coup
         pour corriger son fichier en une seule fois. --}}
    @if (session('erreurs'))
        <div class="card" style="border-left:4px solid #7a1f1f;">
            <h2 style="margin-top:0;font-size:1.05rem;color:#7a1f1f;">
                {{ __('Fichier refusé — aucun produit n\'a été créé') }}
            </h2>
            <p style="color:#56606b;font-size:.9rem;">
                {{ __('Corrigez les lignes ci-dessous dans votre tableur, puis renvoyez le fichier entier.') }}
            </p>
            <ul style="margin:0;padding-left:1.2rem;">
                @foreach (session('erreurs') as $erreur)
                    <li style="margin-bottom:.25rem;">{{ $erreur }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card" style="max-width:640px;">
        <h2 style="margin-top:0;font-size:1.05rem;">{{ __('1. Partir du modèle') }}</h2>
        <p style="color:#56606b;font-size:.9rem;">
            {{ __('Le modèle contient les colonnes attendues et deux exemples. Ouvrez-le dans Excel ou LibreOffice, remplacez les exemples par vos produits, puis enregistrez au format CSV.') }}
        </p>
        <p><a class="btn" href="{{ route('produits.import.modele') }}">{{ __('Télécharger le modèle') }}</a></p>
    </div>

    <div class="card" style="max-width:640px;">
        <h2 style="margin-top:0;font-size:1.05rem;">{{ __('2. Envoyer le fichier') }}</h2>

        <form method="POST" action="{{ route('produits.import.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="field">
                <label for="boutique_id">{{ __('Boutique où entre le stock') }}</label>
                <select id="boutique_id" name="boutique_id" required>
                    <option value="">— {{ __('Choisir') }} —</option>
                    @foreach ($boutiques as $boutique)
                        <option value="{{ $boutique->id }}" @selected((string) old('boutique_id') === (string) $boutique->id)>{{ $boutique->nom }}</option>
                    @endforeach
                </select>
                @error('boutique_id')<small style="color:#7a1f1f;">{{ $message }}</small>@enderror
            </div>

            <div class="field">
                <label for="fichier">{{ __('Fichier CSV') }}</label>
                <input id="fichier" type="file" name="fichier" accept=".csv,text/csv" required>
                @error('fichier')<small style="color:#7a1f1f;">{{ $message }}</small>@enderror
            </div>

            <button class="btn" type="submit">{{ __('Importer') }}</button>
        </form>
    </div>

    <div class="card" style="max-width:640px;">
        <h2 style="margin-top:0;font-size:1.05rem;">{{ __('Les colonnes') }}</h2>
        <p style="color:#56606b;font-size:.9rem;">
            {{ __("L'ordre des colonnes n'a pas d'importance, seuls leurs intitulés comptent. Les accents et les majuscules sont ignorés.") }}
        </p>
        <table>
            <thead>
                <tr><th>{{ __('Colonne') }}</th><th>{{ __('Valeurs') }}</th></tr>
            </thead>
            <tbody>
                <tr><td>nom</td><td>{{ __('Obligatoire.') }}</td></tr>
                <tr>
                    <td>type</td>
                    <td>{{ implode(', ', array_column(\App\Enums\TypeProduit::cases(), 'value')) }}</td>
                </tr>
                <tr>
                    <td>unite_mesure</td>
                    <td>{{ implode(', ', array_column(\App\Enums\UniteMesure::cases(), 'value')) }}</td>
                </tr>
                <tr><td>code_barres</td><td>{{ __('Facultatif, unique dans votre catalogue.') }}</td></tr>
                <tr><td>categorie</td><td>{{ __('Facultatif. Créée si elle n\'existe pas encore.') }}</td></tr>
                <tr><td>prix_achat</td><td>{{ __('Obligatoire, en FCFA, sans décimale ni espace.') }}</td></tr>
                <tr><td>prix_vente</td><td>{{ __('Prix au détail. Vide = le produit ne se vend qu\'en formats entiers.') }}</td></tr>
                <tr><td>stock_min</td><td>{{ __("Seuil sous lequel l'alerte se déclenche.") }}</td></tr>
                <tr><td>stock_max</td><td>{{ __('Doit être supérieur ou égal au minimum.') }}</td></tr>
                <tr><td>quantite_initiale</td><td>{{ __('Quantité physiquement présente, au moins 1.') }}</td></tr>
                <tr><td>numero_lot</td><td>{{ __('Obligatoire. À défaut de lot fournisseur, un repère de votre choix.') }}</td></tr>
                <tr><td>date_fabrication</td><td>{{ __('Facultatif, au format AAAA-MM-JJ.') }}</td></tr>
                <tr>
                    <td>date_peremption</td>
                    <td>{{ __('Format AAAA-MM-JJ. Obligatoire pour les phytosanitaires, à laisser vide pour ce qui ne périme pas.') }}</td>
                </tr>
            </tbody>
        </table>
        <p style="color:#56606b;font-size:.9rem;margin-bottom:0;">
            {{ __("Si une seule ligne est refusée, rien n'est importé : vous corrigez le fichier et le renvoyez entier, sans risque de doublon.") }}
        </p>
    </div>
@endsection
