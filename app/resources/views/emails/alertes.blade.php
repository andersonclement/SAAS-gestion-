<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Alertes de stock') }}</title>
</head>
{{-- Les styles sont en ligne : les clients de messagerie ignorent
     largement les feuilles de style externes et les balises <style>. --}}
<body style="margin:0;padding:0;background:#f4f5f7;font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif;color:#1f2328;">
<div style="max-width:640px;margin:0 auto;padding:16px;">

    <div style="background:#14532d;color:#ffffff;padding:20px;border-radius:8px 8px 0 0;">
        <p style="margin:0;font-size:18px;font-weight:700;">{{ config('app.name') }}</p>
        <p style="margin:4px 0 0;font-size:14px;color:#d7e6dc;">{{ __('Alertes de stock') }} — {{ $perimetre }}</p>
    </div>

    <div style="background:#ffffff;padding:20px;border-radius:0 0 8px 8px;">
        <p style="margin:0 0 16px;font-size:15px;line-height:1.5;">
            {{ __('Bonjour :nom,', ['nom' => $destinataire->name]) }}<br>
            {{ trans_choice('{1} :nombre point demande votre attention aujourd’hui.|[2,*] :nombre points demandent votre attention aujourd’hui.', $total, ['nombre' => $total]) }}
        </p>

        @if ($alertes['ruptures']->isNotEmpty())
            <h2 style="font-size:15px;margin:20px 0 6px;color:#7a1f1f;">{{ __('Ruptures de stock') }} ({{ $alertes['ruptures']->count() }})</h2>
            <ul style="margin:0;padding-left:20px;font-size:14px;line-height:1.6;">
                @foreach ($alertes['ruptures']->take(20) as $rupture)
                    <li>{{ $rupture['produit']?->nom ?? __('Produit supprimé') }} — {{ $rupture['boutique']?->nom }}</li>
                @endforeach
            </ul>
            @if ($alertes['ruptures']->count() > 20)
                <p style="font-size:13px;color:#56606b;margin:4px 0 0;">{{ __('… et :nombre autre(s).', ['nombre' => $alertes['ruptures']->count() - 20]) }}</p>
            @endif
        @endif

        @if ($alertes['stockFaible']->isNotEmpty())
            <h2 style="font-size:15px;margin:20px 0 6px;color:#8a5a00;">{{ __('Stocks faibles') }} ({{ $alertes['stockFaible']->count() }})</h2>
            <ul style="margin:0;padding-left:20px;font-size:14px;line-height:1.6;">
                @foreach ($alertes['stockFaible']->take(20) as $faible)
                    <li>
                        {{ $faible['produit']?->nom ?? __('Produit supprimé') }} — {{ $faible['boutique']?->nom }}
                        ({{ __('reste :quantite', ['quantite' => $faible['quantite']]) }})
                    </li>
                @endforeach
            </ul>
            @if ($alertes['stockFaible']->count() > 20)
                <p style="font-size:13px;color:#56606b;margin:4px 0 0;">{{ __('… et :nombre autre(s).', ['nombre' => $alertes['stockFaible']->count() - 20]) }}</p>
            @endif
        @endif

        @if ($alertes['perimes']->isNotEmpty())
            <h2 style="font-size:15px;margin:20px 0 6px;color:#7a1f1f;">{{ __('Lots périmés') }} ({{ $alertes['perimes']->count() }})</h2>
            <ul style="margin:0;padding-left:20px;font-size:14px;line-height:1.6;">
                @foreach ($alertes['perimes']->take(20) as $stock)
                    <li>
                        {{ $stock->produit?->nom }} — {{ __('lot') }} {{ $stock->lot?->numero_lot }}
                        ({{ $stock->lot?->date_peremption?->format('d/m/Y') }})
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($alertes['peremptionProche']->isNotEmpty())
            <h2 style="font-size:15px;margin:20px 0 6px;color:#8a5a00;">{{ __('Péremptions proches') }} ({{ $alertes['peremptionProche']->count() }})</h2>
            <ul style="margin:0;padding-left:20px;font-size:14px;line-height:1.6;">
                @foreach ($alertes['peremptionProche']->take(20) as $stock)
                    <li>
                        {{ $stock->produit?->nom }} — {{ __('lot') }} {{ $stock->lot?->numero_lot }}
                        ({{ $stock->lot?->date_peremption?->format('d/m/Y') }})
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($alertes['creancesEnRetard']->isNotEmpty())
            <h2 style="font-size:15px;margin:20px 0 6px;color:#7a1f1f;">{{ __('Créances en retard') }} ({{ $alertes['creancesEnRetard']->count() }})</h2>
            <ul style="margin:0;padding-left:20px;font-size:14px;line-height:1.6;">
                @foreach ($alertes['creancesEnRetard']->take(20) as $vente)
                    <li>
                        {{ $vente->client?->nom ?? __('Client de passage') }} —
                        {{ number_format($vente->montantDu(), 0, ',', ' ') }} FCFA
                        ({{ __('échéance') }} {{ $vente->date_echeance?->format('d/m/Y') }})
                    </li>
                @endforeach
            </ul>
        @endif

        <p style="margin:24px 0 0;">
            <a href="{{ route('alertes.index') }}"
               style="display:inline-block;background:#14532d;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:6px;font-weight:600;font-size:15px;">
                {{ __('Ouvrir le centre d’alertes') }}
            </a>
        </p>
    </div>

    <p style="font-size:12px;color:#6b7280;text-align:center;margin:16px 0 0;line-height:1.5;">
        {{ __('Vous recevez ce message parce que les alertes par e-mail sont activées sur votre compte.') }}<br>
        {{ __('Un responsable peut les désactiver depuis la gestion des utilisateurs.') }}
    </p>

</div>
</body>
</html>
