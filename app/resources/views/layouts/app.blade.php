<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('Tableau de bord')) — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    {{-- Application installable et utilisable sans réseau (§5). --}}
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="theme-color" content="#1e4620">
    <link rel="apple-touch-icon" href="{{ asset('icones/icone-192.png') }}">
    <script src="{{ asset('js/app.js') }}" defer></script>
    @auth
        <script src="{{ asset('js/hors-ligne.js') }}" defer></script>
    @endauth
</head>
<body>
@auth
    <div class="topbar">
        {{-- <details> : menu repliable sans JavaScript, donc compatible avec
             la politique de sécurité de contenu. Déployé d'office au-delà du
             téléphone (voir app.css). --}}
        <details class="nav-repliable">
            <summary>{{ __('Menu') }}</summary>
            <nav class="nav-liens">
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
                @can('viewAny', App\Models\CodeAcces::class)
                    <a href="{{ route('codes-acces.index') }}">{{ __("Codes d'accès") }}</a>
                @endcan
                @if (auth()->user()->isGerant())
                    <a href="{{ route('acces-privilegie.create') }}">
                        {{ __("Code d'accès") }}
                        @if ($accesPrivilegie ?? null)
                            <span class="badge" style="background:#e6f4ea;color:#1e4620;">{{ __('ouvert') }}</span>
                        @endif
                    </a>
                @endif
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
            </nav>
        </details>

        <div class="topbar-compte">
            @if (auth()->user()->isPatron())
                <form method="POST" action="{{ route('boutique-contexte.update') }}">
                    @csrf
                    <select name="boutique_id" data-auto-submit style="padding:.4rem;border-radius:6px;border:none;max-width:100%;">
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
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn" type="submit" style="background:transparent;color:#fff;text-decoration:underline;padding:.4rem 0;min-height:0;">{{ __('Déconnexion') }}</button>
            </form>
        </div>
    </div>

    @if ($accesPrivilegie ?? null)
        <div class="status" style="border-radius:0;margin-bottom:0;text-align:center;">
            {{ __("Accès :portee ouvert avec le code :code jusqu'au :echeance.", [
                'portee' => $accesPrivilegie->portee->label(),
                'code' => $accesPrivilegie->code,
                'echeance' => $accesPrivilegie->expire_le->format('d/m/Y H:i'),
            ]) }}
            <form method="POST" action="{{ route('acces-privilegie.destroy') }}" style="display:inline">
                @csrf
                @method('DELETE')
                <button type="submit" style="background:none;border:none;color:#1e4620;text-decoration:underline;cursor:pointer;padding:0;font:inherit;">{{ __('Refermer') }}</button>
            </form>
        </div>
    @endif

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
    <div class="topbar" style="display:flex;justify-content:space-between;align-items:center;">
        <div><a href="{{ route('accueil') }}"><strong>{{ config('app.name') }}</strong></a></div>
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

<footer class="pied-page">
    @include('partials.service-client')
</footer>

@auth
    <script nonce="{{ $cspNonce }}">
        // Le service worker n'est utile qu'à un utilisateur connecté : il met en
        // cache des écrans de travail. Il est enregistré après le chargement pour
        // ne pas concurrencer l'affichage de la page.
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/sw.js').catch(function () {
                    // Contexte non sécurisé (http://) ou navigateur sans support :
                    // l'application reste pleinement fonctionnelle en ligne.
                });
            });
        }
    </script>
@endauth
</body>
</html>
