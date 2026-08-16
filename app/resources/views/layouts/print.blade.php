<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — {{ config('app.name') }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, "Segoe UI", Roboto, sans-serif; background: #f4f5f7; color: #1f2328; }
        .print-toolbar { background: #14532d; color: #fff; padding: .75rem 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        .print-toolbar a { color: #fff; text-decoration: none; opacity: .9; }
        .print-toolbar a:hover { opacity: 1; text-decoration: underline; }
        .btn { display: inline-block; background: #14532d; color: #fff; padding: .5rem 1rem; border-radius: 6px; text-decoration: none; border: none; cursor: pointer; font-size: .95rem; }
        .facture { max-width: 480px; margin: 1.5rem auto; padding: 1.5rem; background: #fff; border: 1px solid #e2e4e8; border-radius: 8px; }
        .facture h1 { font-size: 1.15rem; margin: 0 0 .1rem; }
        .facture .entete { text-align: center; margin-bottom: 1rem; border-bottom: 2px solid #14532d; padding-bottom: 1rem; }
        .facture .entete .boutique-nom { font-size: 1.3rem; font-weight: 700; color: #14532d; }
        .facture .meta { display: flex; justify-content: space-between; font-size: .85rem; margin-bottom: 1rem; }
        table.lignes { width: 100%; border-collapse: collapse; font-size: .85rem; margin-bottom: 1rem; }
        table.lignes th, table.lignes td { text-align: left; padding: .35rem .25rem; border-bottom: 1px dashed #ccc; }
        table.lignes th:last-child, table.lignes td:last-child { text-align: right; }
        .totaux p { display: flex; justify-content: space-between; margin: .25rem 0; font-size: .9rem; }
        .totaux .total { font-weight: 700; font-size: 1.05rem; border-top: 2px solid #14532d; padding-top: .5rem; margin-top: .5rem; }
        .statut-paiement { margin-top: 1rem; padding: .6rem .8rem; border-radius: 6px; font-size: .9rem; text-align: center; font-weight: 600; }
        .statut-paiement.paye { background: #e6f4ea; color: #1e4620; }
        .statut-paiement.du { background: #fdecea; color: #7a1f1f; }
        .facture footer { margin-top: 1.5rem; text-align: center; font-size: .8rem; color: #6b7280; }
        @media print {
            .print-toolbar { display: none; }
            body { background: #fff; }
            .facture { border: none; margin: 0; max-width: 100%; }
        }
    </style>
    <script src="{{ asset('js/app.js') }}" defer></script>
</head>
<body>
    <div class="print-toolbar">
        <div>{{ config('app.name') }}</div>
        <div>
            <button class="btn" type="button" data-print>{{ __('Télécharger / Imprimer') }}</button>
        </div>
    </div>
    @yield('content')
</body>
</html>
