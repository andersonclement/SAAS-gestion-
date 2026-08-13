<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — {{ __('Gestion de stock pour boutiques d\'intrants agricoles') }}</title>
    <meta name="description" content="{{ __('Suivez votre stock, vos ventes et votre trésorerie sur plusieurs boutiques, depuis un téléphone. Traçabilité des lots et des dates de péremption.') }}">
    <link rel="stylesheet" href="{{ asset('css/accueil.css') }}">
</head>
<body>

<header class="entete">
    <div class="bandeau">
        <a class="marque" href="{{ route('accueil') }}">{{ config('app.name') }}</a>
        <nav class="entete-actions">
            @include('partials.locale-switcher')
            <a class="lien-connexion" href="{{ route('login') }}">{{ __('Se connecter') }}</a>
        </nav>
    </div>

    <div class="heros">
        <h1>{{ __('Votre stock sous contrôle, boutique par boutique.') }}</h1>
        <p class="accroche">
            {{ __("Gérez les intrants agricoles et produits phytosanitaires de toutes vos boutiques depuis un seul compte : stock en temps réel, ventes, crédits clients et trésorerie — y compris depuis un téléphone.") }}
        </p>
        <p class="heros-actions">
            <a class="btn btn-principal" href="{{ route('register') }}">{{ __('Créer mon espace') }}</a>
            <a class="btn btn-secondaire" href="{{ route('login') }}">{{ __("J'ai déjà un compte") }}</a>
        </p>
        <p class="mention">{{ __("L'accès nécessite un code d'activation fourni par notre équipe.") }}</p>
    </div>
</header>

<main>
    <section class="section">
        <h2>{{ __('Ce que fait la plateforme') }}</h2>
        <div class="grille">
            <article class="bloc">
                <h3>{{ __('Stock multi-boutiques') }}</h3>
                <p>{{ __('Chaque boutique a son stock, ses ventes et sa caisse. Le patron voit le consolidé, le gérant ne voit que la sienne.') }}</p>
            </article>
            <article class="bloc">
                <h3>{{ __('Traçabilité des lots') }}</h3>
                <p>{{ __('Numéro de lot et date de péremption suivis à la vente comme à l’achat — indispensable pour les produits phytosanitaires.') }}</p>
            </article>
            <article class="bloc">
                <h3>{{ __('Ventes et factures') }}</h3>
                <p>{{ __('Encaissement comptant ou à crédit, facture imprimable adaptée aux mini-imprimantes de caisse.') }}</p>
            </article>
            <article class="bloc">
                <h3>{{ __('Crédits clients') }}</h3>
                <p>{{ __('Plafond par client, échéancier des dettes et alerte sur les retards de paiement.') }}</p>
            </article>
            <article class="bloc">
                <h3>{{ __('Trésorerie') }}</h3>
                <p>{{ __('Caisse du jour, dépenses, bénéfice net du mois, et suivi de l’argent récupéré auprès de chaque gérant.') }}</p>
            </article>
            <article class="bloc">
                <h3>{{ __('Alertes actives') }}</h3>
                <p>{{ __('Ruptures, stocks faibles, péremptions proches et créances en retard, réunies sur un seul écran.') }}</p>
            </article>
        </div>
    </section>

    <section class="section section-grise" id="tarifs">
        <h2>{{ __('Tarifs') }}</h2>
        <p class="sous-titre">{{ __('Sans engagement. Vous activez votre abonnement avec un code fourni par notre équipe.') }}</p>

        <div class="grille grille-tarifs">
            @foreach (\App\Enums\Plan::cases() as $plan)
                <article class="bloc bloc-tarif">
                    <h3>{{ $plan->label() }}</h3>
                    <p class="prix">
                        {{ number_format($plan->prixMensuel(), 0, ',', ' ') }}
                        <span>FCFA / {{ __('mois') }}</span>
                    </p>
                    <p class="detail">{{ $plan->description() }}</p>
                    <ul>
                        <li>{{ __('Boutiques, produits et ventes illimités') }}</li>
                        <li>{{ __('Comptes gérant, vendeur et comptable inclus') }}</li>
                        <li>{{ __('Traçabilité des lots et alertes') }}</li>
                        <li>{{ __('Interface française et anglaise') }}</li>
                    </ul>
                </article>
            @endforeach
        </div>
    </section>

    <section class="section section-appel">
        <h2>{{ __('Prêt à démarrer ?') }}</h2>
        <p>{{ __('Créez votre espace en quelques minutes avec votre code d’activation.') }}</p>
        <p>
            <a class="btn btn-principal" href="{{ route('register') }}">{{ __('Créer mon espace') }}</a>
        </p>
    </section>
</main>

<footer class="pied">
    <p>{{ config('app.name') }} — {{ __('Gestion de stock pour boutiques d’intrants agricoles') }}</p>
    <p><a href="{{ route('login') }}">{{ __('Se connecter') }}</a></p>
</footer>

</body>
</html>
