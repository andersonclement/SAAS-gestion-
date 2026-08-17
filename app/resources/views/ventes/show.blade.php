@extends('layouts.app')

@section('title', $vente->numero)

@section('content')
    <h1>{{ __('Ticket de caisse') }} — {{ $vente->numero }}</h1>

    <p>
        <a class="btn" href="{{ route('ventes.facture', $vente) }}" target="_blank" rel="noopener">{{ __('Télécharger / Imprimer la facture') }}</a>
    </p>

    <div class="card">
        <p><strong>{{ __('Date') }} :</strong> {{ $vente->created_at->format('d/m/Y H:i') }}</p>
        <p><strong>{{ __('Boutique') }} :</strong> {{ $vente->boutique->nom }}</p>
        <p><strong>{{ __('Client') }} :</strong> {{ $vente->client?->nom ?? __('Client de passage') }}</p>
        <p><strong>{{ __('Vendeur') }} :</strong> {{ $vente->vendeur->name }}</p>
        @if ($vente->estACredit())
            <p><strong>{{ __("Date d'échéance") }} :</strong> {{ $vente->date_echeance->format('d/m/Y') }}
                @if ($vente->estEnRetard())
                    <span class="badge" style="background:#fdecea;color:#611a15;">{{ __('En retard') }}</span>
                @endif
            </p>
        @endif
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>{{ __('Produit') }}</th>
                    <th>{{ __('Lot') }}</th>
                    <th>{{ __('Quantité') }}</th>
                    <th>{{ __('Prix unitaire') }}</th>
                    <th>{{ __('Sous-total') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($vente->lignes as $ligne)
                    <tr>
                        <td>{{ $ligne->produit->nom }}</td>
                        <td>{{ $ligne->lot->numero_lot }}</td>
                        <td>{{ $ligne->quantiteLisible() }}</td>
                        <td>{{ number_format($ligne->prix_unitaire, 0, ',', ' ') }} FCFA</td>
                        <td>{{ number_format($ligne->sousTotal(), 0, ',', ' ') }} FCFA</td>
                        <td>
                            @can('create', App\Models\Retour::class)
                                @if ($ligne->quantiteRetournable() > 0)
                                    <a href="{{ route('retours.create', $ligne) }}">{{ __('Retourner') }}</a>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p style="text-align:right;font-weight:700;margin-top:1rem;">
            {{ __('Total') }} : {{ number_format($vente->montantTotal(), 0, ',', ' ') }} FCFA
        </p>

        <h2 style="font-size:1.1rem;">{{ __('Paiements') }}</h2>
        @foreach ($vente->paiements as $paiement)
            <p>{{ $paiement->mode->label() }} — {{ number_format($paiement->montant, 0, ',', ' ') }} FCFA</p>
        @endforeach

        @if ($vente->montantDu() > 0)
            <p style="font-weight:700;color:#7a1f1f;">{{ __('Solde dû') }} : {{ number_format($vente->montantDu(), 0, ',', ' ') }} FCFA</p>

            @if (! auth()->user()->isComptable())
                <form method="POST" action="{{ route('ventes.reglements.store', $vente) }}" style="display:flex;gap:.5rem;align-items:end;max-width:420px;">
                    @csrf
                    <div class="field" style="margin-bottom:0;">
                        <label for="reglement_mode">{{ __('Mode') }}</label>
                        <select id="reglement_mode" name="mode" required>
                            @foreach (\App\Enums\ModePaiement::cases() as $mode)
                                <option value="{{ $mode->value }}">{{ $mode->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field" style="margin-bottom:0;">
                        <label for="reglement_montant">{{ __('Montant (FCFA)') }}</label>
                        <input id="reglement_montant" type="number" min="1" max="{{ $vente->montantDu() }}" name="montant" value="{{ $vente->montantDu() }}" required>
                    </div>
                    <button class="btn" type="submit">{{ __('Enregistrer le règlement') }}</button>
                </form>
            @endif
        @endif
    </div>
@endsection
