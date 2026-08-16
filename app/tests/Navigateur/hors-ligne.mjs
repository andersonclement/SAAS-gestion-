/**
 * Test de bout en bout du mode hors-ligne (§5), dans un vrai navigateur.
 *
 * La suite PHPUnit couvre le serveur — idempotence, écarts de stock,
 * autorisations. Elle ne peut rien dire du service worker, d'IndexedDB ni du
 * comportement réel d'une coupure réseau : c'est l'objet de ce test, qui coupe
 * effectivement le réseau du navigateur, encaisse deux ventes, le rétablit, et
 * vérifie que tout est arrivé en base sans doublon.
 *
 * Prérequis :
 *   npm install
 *   php artisan migrate:fresh && php artisan db:seed   (ou un jeu de données
 *                                                       équivalent, voir plus bas)
 *   php artisan serve --port=8123 --no-reload
 *   node tests/Navigateur/hors-ligne.mjs
 *
 * Les identifiants et la base ciblée se règlent par variables d'environnement :
 *   URL_BASE, EMAIL_VENDEUR, MOT_DE_PASSE
 *
 * Le compte utilisé doit être un vendeur rattaché à une boutique disposant
 * d'au moins un produit en stock.
 */
import { chromium } from 'playwright';

const BASE = process.env.URL_BASE || 'http://127.0.0.1:8123';
const EMAIL = process.env.EMAIL_VENDEUR || 'vendeur@demo.cm';
const MOT_DE_PASSE = process.env.MOT_DE_PASSE || 'motdepasse123';

const echecs = [];

function verifier(nom, condition, detail = '') {
    console.log(`${condition ? '✓' : '✗'} ${nom}${detail ? ` — ${detail}` : ''}`);

    if (! condition) {
        echecs.push(nom);
    }
}

const navigateur = await chromium.launch({ args: ['--no-sandbox', '--disable-dev-shm-usage'] });
const contexte = await navigateur.newContext({ serviceWorkers: 'allow' });
const page = await contexte.newPage();
page.setDefaultTimeout(20000);

const erreursConsole = [];
page.on('console', (message) => {
    if (message.type() === 'error') {
        erreursConsole.push(message.text());
    }
});
page.on('pageerror', (erreur) => erreursConsole.push(String(erreur)));

// --- 1. Connexion ---------------------------------------------------------
await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
await page.fill('#email', EMAIL);
await page.fill('#password', MOT_DE_PASSE);
await page.locator('form[action$="/login"] button[type="submit"]').click();
await page.waitForURL(/\/dashboard/, { timeout: 20000 }).catch(() => {});
verifier('connexion du vendeur', ! page.url().includes('/login'), page.url());

// --- 2. En ligne : le service worker s'installe et le catalogue est capturé -
await page.goto(`${BASE}/ventes/create`, { waitUntil: 'networkidle' });

const serviceWorkerActif = await page.evaluate(() => Promise.race([
    navigator.serviceWorker.ready.then((enregistrement) => !! enregistrement.active),
    new Promise((resoudre) => setTimeout(() => resoudre(false), 10000)),
]));
verifier('service worker actif', serviceWorkerActif);

await page.waitForTimeout(1500);

const catalogue = await page.evaluate(() => window.CaisseHorsLigne.catalogue());
verifier(
    'instantané du catalogue enregistré',
    catalogue && catalogue.produits.length > 0,
    catalogue ? `${catalogue.produits.length} produit(s), stock ${catalogue.produits[0].stock}` : 'absent'
);

// --- 3. Coupure réseau ----------------------------------------------------
await contexte.setOffline(true);

const reponse = await page.goto(`${BASE}/ventes/create`, { waitUntil: 'domcontentloaded' }).catch(() => null);
verifier(
    'caisse accessible hors réseau',
    !! reponse && (await page.locator('#lignes-body').count()) > 0,
    `statut ${reponse ? reponse.status() : 'aucune réponse'}`
);

await page.waitForTimeout(500);
const bandeau = await page.locator('#bandeau-hors-ligne').textContent().catch(() => '');
verifier('bandeau « hors ligne » affiché', /Hors ligne/i.test(bandeau || ''), bandeau);

// --- 4. Encaissement sans réseau -----------------------------------------
page.on('dialog', (dialogue) => dialogue.accept());

async function encaisser(quantite) {
    await page.selectOption('#boutique_id', { index: 1 });
    await page.selectOption('#mode_paiement', 'especes');
    await page.selectOption('.produit-select', { index: 1 });
    await page.fill('.quantite-input', String(quantite));
    await page.click('form[action$="/ventes"] button[type="submit"]');
    await page.waitForTimeout(1500);
}

await encaisser(4);
const enAttente = await page.evaluate(() => window.CaisseHorsLigne.enAttente());
verifier('vente mise en file locale', enAttente.length === 1, `${enAttente.length} en file`);

await encaisser(2);
const enAttente2 = await page.evaluate(() => window.CaisseHorsLigne.enAttente());
verifier('deuxième vente mise en file', enAttente2.length === 2, `${enAttente2.length} en file`);

// --- 5. Retour du réseau : synchronisation automatique --------------------
await contexte.setOffline(false);
await page.evaluate(() => window.dispatchEvent(new Event('online')));

for (let essai = 0; essai < 30; essai++) {
    const restantes = await page.evaluate(() => window.CaisseHorsLigne.enAttente());

    if (restantes.length === 0) {
        break;
    }

    await page.waitForTimeout(500);
}

const restantes = await page.evaluate(() => window.CaisseHorsLigne.enAttente());
verifier('file vidée après reconnexion', restantes.length === 0, `${restantes.length} restante(s)`);

// --- 6. Les ventes sont bien en base -------------------------------------
await page.goto(`${BASE}/ventes`, { waitUntil: 'networkidle' });
const nbLignes = await page.locator('table tbody tr').count();
verifier('ventes visibles côté serveur', nbLignes >= 2, `${nbLignes} ligne(s)`);

// --- 7. Idempotence : rejouer la même vente ne duplique rien -------------
const rejeu = await page.evaluate(async (vente) => {
    const jeton = document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='));

    const reponse = await fetch('/sync/ventes', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': decodeURIComponent(jeton.split('=')[1]),
        },
        body: JSON.stringify({ ventes: [vente] }),
    });

    return reponse.json();
}, enAttente[0]);

verifier(
    'rejeu de la même vente refusé comme doublon',
    rejeu.resultats[0].statut === 'deja_enregistree',
    rejeu.resultats[0].statut
);

await page.goto(`${BASE}/ventes`, { waitUntil: 'networkidle' });
verifier('aucune vente dupliquée', (await page.locator('table tbody tr').count()) === nbLignes);

// Les échecs de chargement pendant la coupure sont attendus ; on ne retient que
// ce qui trahirait un vrai défaut, à commencer par une violation de la CSP.
const erreursReelles = erreursConsole.filter(
    (message) => ! /ERR_INTERNET_DISCONNECTED|ERR_NETWORK_CHANGED|Failed to fetch/i.test(message)
);
verifier('aucune violation CSP ni erreur JS', erreursReelles.length === 0, erreursReelles.slice(0, 3).join(' | '));

console.log(echecs.length === 0 ? '\nRÉSULTAT : tout est vert' : `\nRÉSULTAT : ${echecs.length} échec(s) — ${echecs.join(', ')}`);

await navigateur.close();
process.exit(echecs.length === 0 ? 0 : 1);
