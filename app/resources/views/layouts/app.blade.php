<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('Tableau de bord')) — {{ config('app.name') }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, "Segoe UI", Roboto, sans-serif; background: #f4f5f7; color: #1f2328; }
        .topbar { background: #14532d; color: #fff; padding: .75rem 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        .topbar a { color: #fff; text-decoration: none; margin-right: 1rem; opacity: .9; }
        .topbar a:hover { opacity: 1; text-decoration: underline; }
        .container { max-width: 960px; margin: 2rem auto; padding: 0 1.5rem; }
        .card { background: #fff; border: 1px solid #e2e4e8; border-radius: 8px; padding: 1.5rem; margin-bottom: 1.5rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: .6rem .5rem; border-bottom: 1px solid #eee; }
        .btn { display: inline-block; background: #14532d; color: #fff; padding: .5rem 1rem; border-radius: 6px; text-decoration: none; border: none; cursor: pointer; font-size: .95rem; }
        .btn:hover { background: #0f3f22; }
        .field { margin-bottom: 1rem; }
        .field label { display: block; font-weight: 600; margin-bottom: .3rem; font-size: .9rem; }
        .field input, .field select { width: 100%; padding: .5rem; border: 1px solid #ccc; border-radius: 6px; }
        .errors { background: #fdecea; color: #611a15; border: 1px solid #f5c6cb; padding: .75rem 1rem; border-radius: 6px; margin-bottom: 1rem; }
        .status { background: #e6f4ea; color: #1e4620; border: 1px solid #b7dfc0; padding: .75rem 1rem; border-radius: 6px; margin-bottom: 1rem; }
        .badge { display: inline-block; padding: .15rem .5rem; border-radius: 4px; background: #eef1f4; font-size: .8rem; }
        .lang-switch a { opacity: .7; }
        .lang-switch a.active { opacity: 1; font-weight: 700; text-decoration: underline; }
    </style>
    <script src="{{ asset('js/app.js') }}" defer></script>
</head>
<body>
@auth
    <div class="topbar">
        <div>
            <a href="{{ route('dashboard') }}">{{ __('Tableau de bord') }}</a>
            <a href="{{ route('boutiques.index') }}">{{ __('Boutiques') }}</a>
            <a href="{{ route('produits.index') }}">{{ __('Catalogue') }}</a>
            <a href="{{ route('ventes.index') }}">{{ __('Ventes') }}</a>
            <a href="{{ route('retours.index') }}">{{ __('Retours') }}</a>
            <a href="{{ route('promotions.index') }}">{{ __('Promotions') }}</a>
            <a href="{{ route('achats.index') }}">{{ __('Achats') }}</a>
            <a href="{{ route('stock.index') }}">{{ __('Stock') }}</a>
            <a href="{{ route('tresorerie.index') }}">{{ __('Trésorerie') }}</a>
            @if (auth()->user()->isPatron() || auth()->user()->isComptable())
                <a href="{{ route('comparatif.index') }}">{{ __('Comparatif') }}</a>
            @endif
            <a href="{{ route('alertes.index') }}">
                {{ __('Alertes') }}
                @if (($nombreAlertes ?? 0) > 0)
                    <span class="badge" style="background:#c0392b;color:#fff;">{{ $nombreAlertes }}</span>
                @endif
            </a>
            @can('viewAny', App\Models\User::class)
                <a href="{{ route('users.index') }}">{{ __('Équipe') }}</a>
            @endcan
            <a href="{{ route('journal.index') }}">{{ __('Journal') }}</a>
            <a href="{{ route('rapports.index') }}">{{ __('Rapports') }}</a>
            <a href="{{ route('abonnement.index') }}">
                {{ __('Abonnement') }}
                @if (! ($abonnementActif ?? true))
                    <span class="badge" style="background:#c0392b;color:#fff;">!</span>
                @elseif ($abonnementExpireBientot ?? false)
                    <span class="badge" style="background:#d97706;color:#fff;">!</span>
                @endif
            </a>
        </div>
        <div>
            @if (auth()->user()->isPatron())
                <form method="POST" action="{{ route('boutique-contexte.update') }}" style="display:inline-block;margin-right:1rem;">
                    @csrf
                    <select name="boutique_id" data-auto-submit style="padding:.3rem .5rem;border-radius:6px;border:none;">
                        <option value="">{{ __('Toutes les boutiques') }}</option>
                        @foreach ($boutiquesPourSelecteur ?? [] as $boutique)
                            <option value="{{ $boutique->id }}" @selected(($boutiqueContexteId ?? null) === $boutique->id)>
                                {{ __('Vue gérant') }} — {{ $boutique->nom }}
                            </option>
                        @endforeach
                    </select>
                </form>
            @endif
            @include('partials.locale-switcher')
            <span>{{ auth()->user()->name }} ({{ auth()->user()->role->label() }})</span>
            <form method="POST" action="{{ route('logout') }}" style="display:inline">
                @csrf
                <button class="btn" type="submit" style="background:transparent;color:#fff;text-decoration:underline;">{{ __('Déconnexion') }}</button>
            </form>
        </div>
    </div>
    @if (auth()->user()->isPatron() && ($boutiqueContexteId ?? null))
        <div class="status" style="border-radius:0;margin-bottom:0;text-align:center;">
            {{ __('Vous consultez la vue gérant.') }}
            <form method="POST" action="{{ route('boutique-contexte.update') }}" style="display:inline">
                @csrf
                <button type="submit" style="background:none;border:none;color:#1e4620;text-decoration:underline;cursor:pointer;padding:0;font:inherit;">{{ __('Revenir à la vue de toutes les boutiques') }}</button>
            </form>
        </div>
    @endif
@else
    <div class="topbar">
        <div><strong>{{ config('app.name') }}</strong></div>
        <div>@include('partials.locale-switcher')</div>
    </div>
@endauth

<div class="container">
    @if (session('status'))
        <div class="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="errors">
            <ul style="margin:0;padding-left:1.2rem;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</div>
</body>
</html>
