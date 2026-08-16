/*
 * Caisse hors-ligne (§5 du cahier des charges).
 *
 * Le vendeur doit pouvoir encaisser quand le réseau tombe. Ce fichier tient
 * trois rôles :
 *
 *  1. Conserver un instantané du catalogue (produits, prix, stock) pour que la
 *     caisse sache quoi vendre sans serveur.
 *  2. Mettre les ventes encaissées hors réseau dans une file locale plutôt que
 *     de les perdre.
 *  3. Vider cette file dès que le réseau revient, en garantissant qu'une vente
 *     ne puisse pas être enregistrée deux fois.
 *
 * L'idempotence repose sur un UUID attribué à la vente au moment de
 * l'encaissement, avant tout contact avec le serveur : si la réponse se perd,
 * le renvoi est sans effet. Une vente ne quitte la file qu'une fois l'accusé de
 * réception du serveur reçu — jamais avant.
 */
(function () {
    'use strict';

    const BASE = 'caisse-hors-ligne';
    const VERSION_BASE = 1;
    const MAGASIN_FILE = 'file-ventes';
    const MAGASIN_CATALOGUE = 'catalogue';

    /* ------------------------------------------------------------------ */
    /* IndexedDB — enveloppe minimale en promesses                        */
    /* ------------------------------------------------------------------ */

    function ouvrirBase() {
        return new Promise((resoudre, rejeter) => {
            const requete = indexedDB.open(BASE, VERSION_BASE);

            requete.onupgradeneeded = function () {
                const db = requete.result;

                if (! db.objectStoreNames.contains(MAGASIN_FILE)) {
                    db.createObjectStore(MAGASIN_FILE, { keyPath: 'uuid_client' });
                }

                if (! db.objectStoreNames.contains(MAGASIN_CATALOGUE)) {
                    db.createObjectStore(MAGASIN_CATALOGUE, { keyPath: 'cle' });
                }
            };

            requete.onsuccess = () => resoudre(requete.result);
            requete.onerror = () => rejeter(requete.error);
        });
    }

    function transaction(magasin, mode, operation) {
        return ouvrirBase().then((db) => new Promise((resoudre, rejeter) => {
            const tx = db.transaction(magasin, mode);
            const requete = operation(tx.objectStore(magasin));

            tx.oncomplete = () => resoudre(requete ? requete.result : undefined);
            tx.onerror = () => rejeter(tx.error);
            tx.onabort = () => rejeter(tx.error);
        }));
    }

    const file = {
        ajouter: (vente) => transaction(MAGASIN_FILE, 'readwrite', (s) => s.put(vente)),
        toutes: () => transaction(MAGASIN_FILE, 'readonly', (s) => s.getAll()),
        retirer: (uuid) => transaction(MAGASIN_FILE, 'readwrite', (s) => s.delete(uuid)),
    };

    const catalogue = {
        enregistrer: (donnees) => transaction(MAGASIN_CATALOGUE, 'readwrite', (s) => s.put({ cle: 'courant', donnees })),
        lire: () => transaction(MAGASIN_CATALOGUE, 'readonly', (s) => s.get('courant'))
            .then((entree) => (entree ? entree.donnees : null)),
    };

    /* ------------------------------------------------------------------ */
    /* Outils                                                             */
    /* ------------------------------------------------------------------ */

    function uuid() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }

        // Repli pour les navigateurs anciens et les contextes non sécurisés.
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (caractere) {
            const aleatoire = (window.crypto ? window.crypto.getRandomValues(new Uint8Array(1))[0] : Math.random() * 256) & 15;
            const valeur = caractere === 'x' ? aleatoire : ((aleatoire & 0x3) | 0x8);

            return valeur.toString(16);
        });
    }

    /**
     * Jeton CSRF lu dans le cookie plutôt que dans la page : la caisse peut
     * avoir été servie depuis le cache du service worker, avec un jeton
     * périmé, alors que le cookie de session, lui, est à jour.
     */
    function jetonCsrf() {
        const cookie = document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='));

        return cookie ? decodeURIComponent(cookie.split('=')[1]) : null;
    }

    /* ------------------------------------------------------------------ */
    /* Bandeau d'état                                                     */
    /* ------------------------------------------------------------------ */

    let bandeau = null;

    function afficherEtat(texte, couleur) {
        if (! bandeau) {
            bandeau = document.createElement('div');
            bandeau.id = 'bandeau-hors-ligne';
            bandeau.setAttribute('role', 'status');
            bandeau.style.cssText = 'position:fixed;left:0;right:0;bottom:0;z-index:9999;'
                + 'padding:.6rem 1rem;text-align:center;font-size:.9rem;color:#fff;';
            document.body.appendChild(bandeau);
        }

        if (! texte) {
            bandeau.style.display = 'none';

            return;
        }

        bandeau.style.display = 'block';
        bandeau.style.background = couleur;
        bandeau.textContent = texte;
    }

    function rafraichirBandeau() {
        return file.toutes().then((ventes) => {
            const enAttente = ventes.length;

            if (! navigator.onLine) {
                afficherEtat(
                    enAttente > 0
                        ? `Hors ligne — ${enAttente} vente(s) en attente d'envoi`
                        : 'Hors ligne — les ventes seront enregistrées sur cet appareil',
                    '#b45309'
                );

                return;
            }

            afficherEtat(enAttente > 0 ? `Envoi de ${enAttente} vente(s) en attente…` : '', '#1e4620');
        });
    }

    /* ------------------------------------------------------------------ */
    /* Synchronisation                                                    */
    /* ------------------------------------------------------------------ */

    let synchronisationEnCours = false;

    function synchroniser() {
        if (synchronisationEnCours || ! navigator.onLine) {
            return Promise.resolve();
        }

        synchronisationEnCours = true;

        return file.toutes()
            .then((ventes) => {
                if (ventes.length === 0) {
                    return null;
                }

                // Envoi par tranches : la file peut couvrir plusieurs jours de
                // coupure, et le serveur borne la taille d'un lot.
                const lot = ventes.slice(0, 50);

                return fetch('/sync/ventes', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': jetonCsrf(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ ventes: lot }),
                }).then((reponse) => {
                    if (! reponse.ok) {
                        throw new Error(`Synchronisation refusée (${reponse.status})`);
                    }

                    return reponse.json();
                });
            })
            .then((donnees) => {
                if (! donnees) {
                    return null;
                }

                // Une vente ne sort de la file que si le serveur confirme
                // l'avoir enregistrée — ou l'avoir déjà. Une vente refusée y
                // reste, visible, plutôt que de disparaître en silence.
                const traitees = donnees.resultats
                    .filter((r) => r.statut === 'enregistree' || r.statut === 'deja_enregistree')
                    .map((r) => file.retirer(r.uuid_client));

                return Promise.all(traitees).then(() => donnees);
            })
            .then((donnees) => {
                const ecarts = donnees
                    ? donnees.resultats.filter((r) => r.ecarts && r.ecarts.length > 0).length
                    : 0;

                if (ecarts > 0) {
                    afficherEtat(
                        `${ecarts} vente(s) synchronisée(s) avec un écart de stock — voir les alertes`,
                        '#b45309'
                    );

                    return;
                }

                return rafraichirBandeau();
            })
            .catch(() => rafraichirBandeau())
            .finally(() => {
                synchronisationEnCours = false;
            });
    }

    /* ------------------------------------------------------------------ */
    /* API publique                                                       */
    /* ------------------------------------------------------------------ */

    window.CaisseHorsLigne = {
        /**
         * Met la vente dans la file locale et tente aussitôt de l'envoyer.
         */
        enregistrer: function (vente) {
            vente.uuid_client = uuid();
            vente.encaissee_le = new Date().toISOString();

            return file.ajouter(vente)
                .then(() => rafraichirBandeau())
                .then(() => synchroniser())
                .then(() => vente.uuid_client);
        },

        rafraichirCatalogue: function () {
            if (! navigator.onLine) {
                return Promise.resolve(null);
            }

            return fetch('/sync/catalogue', {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            })
                .then((reponse) => (reponse.ok ? reponse.json() : null))
                .then((donnees) => {
                    if (! donnees || ! donnees.disponible) {
                        return null;
                    }

                    return catalogue.enregistrer(donnees).then(() => donnees);
                })
                .catch(() => null);
        },

        catalogue: () => catalogue.lire(),
        enAttente: () => file.toutes(),
        synchroniser,
        rafraichirBandeau,
    };

    /* ------------------------------------------------------------------ */
    /* Amorçage                                                           */
    /* ------------------------------------------------------------------ */

    window.addEventListener('online', () => {
        rafraichirBandeau();
        synchroniser();
    });

    window.addEventListener('offline', rafraichirBandeau);

    document.addEventListener('DOMContentLoaded', function () {
        rafraichirBandeau();
        synchroniser();

        // Le cache du service worker contient des pages d'un utilisateur
        // authentifié : il est purgé dès qu'il quitte sa session.
        document.querySelectorAll('form[action$="/logout"]').forEach(function (formulaire) {
            formulaire.addEventListener('submit', function () {
                if (navigator.serviceWorker && navigator.serviceWorker.controller) {
                    navigator.serviceWorker.controller.postMessage('vider-cache');
                }
            });
        });
    });
}());
