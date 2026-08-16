@extends('layouts.app')

@section('title', $produit->nom)

@section('content')
    <h1>{{ $produit->nom }}</h1>

    @can('update', $produit)
        <p><a class="btn" href="{{ route('produits.edit', $produit) }}">{{ __('Modifier') }}</a></p>
    @endcan

    <div class="card">
        <p><strong>{{ __('Type') }} :</strong> {{ $produit->type->label() }}</p>
        <p><strong>{{ __('Catégorie') }} :</strong> {{ $produit->categorie?->nom ?? '—' }}</p>
        <p><strong>{{ __('Unité de mesure') }} :</strong> {{ $produit->unite_mesure->label() }}</p>
        <p><strong>{{ __('Code-barres') }} :</strong> {{ $produit->code_barres ?? '—' }}</p>
        <p><strong>{{ __("Prix d'achat") }} :</strong> {{ number_format($produit->prix_achat, 0, ',', ' ') }} FCFA</p>
        <p><strong>{{ __('Prix de vente') }} :</strong> {{ number_format($produit->prix_vente, 0, ',', ' ') }} FCFA</p>
        <p><strong>{{ __('Stock minimum') }} :</strong> {{ $produit->stock_min }}</p>
        <p><strong>{{ __('Stock maximum') }} :</strong> {{ $produit->stock_max ?: '—' }}</p>
        @if ($produit->type->tracabiliteObligatoire())
            <p style="color:#7a1f1f;"><strong>⚠ {{ __('Traçabilité par lot obligatoire') }}</strong> ({{ __('produit phytosanitaire') }}) — {{ __("suivie lors des réceptions d'achat.") }}</p>
        @endif
    </div>
    <div class="card">
        <h2 style="margin-top:0;">{{ __('Formats de vente') }}</h2>
        <p style="color:#555;font-size:.9rem;margin-top:0;">
            {{ __('Le stock reste toujours compté en :unite. Un format ne fait que regrouper plusieurs unités pour la vente — il ne crée pas de stock séparé.', ['unite' => $produit->unite_mesure->value]) }}
        </p>

        @if ($produit->conditionnements->where('actif', true)->isEmpty())
            <p>{{ __('Aucun format défini : le produit se vend à l\'unité, à :prix FCFA.', ['prix' => number_format($produit->prix_vente, 0, ',', ' ')]) }}</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Format') }}</th>
                        <th>{{ __('Contenu') }}</th>
                        <th>{{ __('Prix') }}</th>
                        <th>{{ __('Soit à l\'unité') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($produit->conditionnements->where('actif', true) as $conditionnement)
                        <tr>
                            <td>
                                {{ $conditionnement->libelle }}
                                @if ($conditionnement->par_defaut)
                                    <span class="badge" style="background:#e6f4ea;color:#1e4620;">{{ __('par défaut') }}</span>
                                @endif
                            </td>
                            <td>{{ $conditionnement->facteur }} {{ $produit->unite_mesure->value }}</td>
                            <td>{{ number_format($conditionnement->prix_vente, 0, ',', ' ') }} FCFA</td>
                            <td>{{ number_format($conditionnement->prixUnitaire(), 0, ',', ' ') }} FCFA</td>
                            <td>
                                @can('delete', $conditionnement)
                                    <form method="POST" action="{{ route('conditionnements.destroy', $conditionnement) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" style="background:none;border:none;color:#7a1f1f;cursor:pointer;padding:0;font:inherit;">{{ __('Retirer') }}</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @can('create', [App\Models\Conditionnement::class, $produit])
            <form method="POST" action="{{ route('conditionnements.store', $produit) }}" style="margin-top:1rem;">
                @csrf
                <div class="field">
                    <label for="libelle">{{ __('Nom du format') }}</label>
                    <input id="libelle" name="libelle" required placeholder="{{ __('Sac de 50 kg') }}" value="{{ old('libelle') }}">
                </div>
                <div class="field">
                    <label for="facteur">{{ __('Contenu en :unite', ['unite' => $produit->unite_mesure->value]) }}</label>
                    <input id="facteur" type="number" min="1" name="facteur" required value="{{ old('facteur', 1) }}">
                </div>
                <div class="field">
                    <label for="prix_vente">{{ __('Prix du format (FCFA)') }}</label>
                    <input id="prix_vente" type="number" min="0" name="prix_vente" required value="{{ old('prix_vente') }}">
                    <small style="color:#555;">{{ __('Doit être un multiple du contenu, pour que le prix à l\'unité tombe juste.') }}</small>
                </div>
                <div class="field">
                    <label><input type="checkbox" name="par_defaut" value="1" @checked(old('par_defaut'))> {{ __('Format proposé par défaut à la caisse') }}</label>
                </div>
                <button class="btn" type="submit">{{ __('Ajouter ce format') }}</button>
            </form>
        @endcan
    </div>
@endsection
