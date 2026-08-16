/*
 * Service worker — mode dégradé hors-ligne (§5 du cahier des charges).
 *
 * Objectif : que la caisse reste utilisable quand le réseau tombe, sans jamais
 * servir de données périmées à un utilisateur qui, lui, est en ligne.
 *
 * Trois règles :
 *  - Les ressources statiques (CSS, JS, icônes) sont servies depuis le cache et
 *    rafraîchies en arrière-plan.
 *  - Les pages sont servies par le réseau, et le cache ne prend le relais que
 *    si le réseau échoue. Une page affichée en ligne est donc toujours à jour.
 *  - Rien de ce qui touche à la synchronisation ni aucune écriture (POST) n'est
 *    mis en cache.
 *
 * Confidentialité : les pages mises en cache sont celles d'un utilisateur
 * authentifié. Le cache est donc entièrement purgé à la déconnexion, sur
 * message de la page (voir public/js/hors-ligne.js).
 */

const VERSION = 'v1';
const CACHE_STATIQUE = `statique-${VERSION}`;
const CACHE_PAGES = `pages-${VERSION}`;

const RESSOURCES_SOCLE = [
    '/css/app.css',
    '/js/app.js',
    '/js/hors-ligne.js',
    '/manifest.webmanifest',
];

// Seules ces pages sont conservées pour un usage hors-ligne. Tout mettre en
// cache exposerait inutilement des données commerciales sur l'appareil.
const PAGES_HORS_LIGNE = ['/ventes/create'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_STATIQUE)
            .then((cache) => cache.addAll(RESSOURCES_SOCLE))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((noms) => Promise.all(
                noms.filter((nom) => ! nom.endsWith(VERSION)).map((nom) => caches.delete(nom))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('message', (event) => {
    if (event.data === 'vider-cache') {
        event.waitUntil(caches.keys().then((noms) => Promise.all(noms.map((nom) => caches.delete(nom)))));
    }
});

self.addEventListener('fetch', (event) => {
    const requete = event.request;

    if (requete.method !== 'GET') {
        return;
    }

    const url = new URL(requete.url);

    if (url.origin !== self.location.origin || url.pathname.startsWith('/sync/')) {
        return;
    }

    if (requete.mode === 'navigate') {
        event.respondWith(reseauPuisCache(requete, url));

        return;
    }

    if (RESSOURCES_SOCLE.includes(url.pathname)) {
        event.respondWith(cachePuisReseau(requete));
    }
});

/**
 * Pages : le réseau fait foi ; le cache n'intervient qu'en cas de coupure.
 */
async function reseauPuisCache(requete, url) {
    try {
        const reponse = await fetch(requete);

        if (reponse.ok && PAGES_HORS_LIGNE.includes(url.pathname)) {
            const cache = await caches.open(CACHE_PAGES);
            cache.put(requete, reponse.clone());
        }

        return reponse;
    } catch (erreur) {
        const enCache = await caches.match(requete);

        if (enCache) {
            return enCache;
        }

        // Pas de version en cache : on redirige vers la caisse, seul écran
        // conçu pour fonctionner sans réseau.
        const caisse = await caches.match('/ventes/create');

        if (caisse) {
            return caisse;
        }

        return new Response(
            '<!doctype html><meta charset="utf-8"><title>Hors ligne</title>'
            + '<p style="font-family:system-ui;padding:2rem">Cette page n\'est pas disponible hors ligne.</p>',
            { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
        );
    }
}

/**
 * Ressources statiques : servies immédiatement depuis le cache, rafraîchies en
 * arrière-plan pour la visite suivante.
 */
async function cachePuisReseau(requete) {
    const cache = await caches.open(CACHE_STATIQUE);
    const enCache = await cache.match(requete);

    const reseau = fetch(requete)
        .then((reponse) => {
            if (reponse.ok) {
                cache.put(requete, reponse.clone());
            }

            return reponse;
        })
        .catch(() => enCache);

    return enCache || reseau;
}
