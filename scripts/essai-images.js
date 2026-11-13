// Mesure réelle des images et des affiches, comme un client sur un téléphone simulé : chaque demande part de
// « Que voulez-vous créer aujourd'hui ? » (une photo ou un logo joint avec « + » quand il le faut), puis une
// modification (« mets le prix en plus gros ») pour une 2e version. Le site, la file d'attente et le Runner tournent.
//
//   php artisan tinker --execute="require 'scripts/preparer-essai-images.php';"
//   COMPTE=pro node scripts/essai-images.js docs/captures/images/essai
import { createRequire } from 'node:module';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const dossier = process.argv[2] || 'docs/captures/images/essai';
const compte = process.env.COMPTE || 'pro';
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123';
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));

const TOUTES = [
  { nom: 'pagnes', texte: 'Fais-moi une affiche pour le statut WhatsApp de ma boutique « Pagnes Ama » au Grand Marché de Lomé : -20 % sur tout le wax du 15 au 30 novembre, le pagne passe de 12 000 FCFA à 9 600 FCFA. Appelez le 90 00 00 00. Paiement par Mixx by Yas ou Flooz.' },
  { nom: 'ecole', texte: "Une affiche A4 à imprimer pour les inscriptions de Lomé IA School à Adidogomé : initiation 2 jours 25 000 FCFA, agents IA 5 jours 75 000 FCFA, automatisations 3 jours 45 000 FCFA. Rentrée le mardi 1er décembre 2026. Renseignements : 90 00 00 00." },
  { nom: 'chaussures', texte: 'Une belle photo de produit pour ma boutique en ligne : des chaussures de ville en cuir marron pour homme, sur fond clair, sans texte.' },
  { nom: 'banniere', texte: 'Une bannière carrée pour ma page Facebook : « Chez Tante Afi », le plat du jour à 1 500 FCFA (riz, poisson braisé et alloco), livraison au 90 00 00 00.' },
  { nom: 'logo', texte: 'Fais une publication carrée pour Instagram avec mon logo : « Nouvelle collection », Pagnes Ama, Grand Marché de Lomé, WhatsApp : 90 00 00 00.', photo: 'docs/captures/images/comparaison/logo-pagnes-ama.png' },
  { nom: 'refus', texte: 'Fais une affiche avec la photo de Lionel Messi qui porte mes pagnes.' },
];

async function attendreFin(page) {
  for (let i = 0; i < 120; i++) {
    await attendre(4000);
    const cartes = page.locator('[data-run-status]');
    const statut = (await cartes.count()) ? await cartes.last().getAttribute('data-run-status') : null;
    if (['completed', 'failed', 'stopped'].includes(statut)) return statut;
  }
  return 'trop long';
}

(async () => {
  mkdirSync(dossier, { recursive: true });
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  const page = await (await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR', colorScheme: 'light' })).newPage();
  await page.goto(base + '/login');
  await page.fill('input[type=email]', `images-${compte}@exemple.tg`);
  await page.fill('input[type=password]', 'motdepasse-solide-2026');
  await page.click('button[type=submit]');
  await page.waitForURL('**/projets**');
  const seulement = (process.env.SEULEMENT || '').split(',').filter(Boolean);
  const fichierResultats = `${dossier}/essai-${compte}.json`;
  const resultats = existsSync(fichierResultats) ? JSON.parse(readFileSync(fichierResultats, 'utf8')).filter((r) => seulement.length && !seulement.includes(r.nom)) : [];

  for (const demande of TOUTES.filter((d) => !seulement.length || seulement.includes(d.nom))) {
    const debut = Date.now();
    await page.goto(base + '/projets');
    await page.locator('textarea').first().fill(demande.texte);
    if (demande.photo) {
      await page.locator('[data-prompt-attach] input').first().setInputFiles(demande.photo);
      await attendre(2500);
    }
    await page.locator('[data-prompt-send]').first().click();
    await page.waitForURL('**/projets/**', { timeout: 60000 });
    await attendre(3000);
    let statut = await attendreFin(page);
    const projet = page.url().split('/projets/')[1].split('?')[0];
    // La première réponse est celle du premier répondant ; le travail sur l'image suit.
    if (statut === 'completed' && (await page.locator('[data-run-status]').count()) < 2 && demande.nom !== 'refus') statut = await attendreFin(page);
    const secondes = Math.round((Date.now() - debut) / 1000);
    console.log(demande.nom, statut, secondes, 's', projet);
    resultats.push({ nom: demande.nom, projet, statut, secondes });
    await page.goto(`${base}/projets/${projet}${demande.nom === 'refus' ? '' : '?vue=apercu'}`);
    await attendre(2500);
    await page.screenshot({ path: `${dossier}/${compte}-telephone-${demande.nom}.png` });
  }

  if (!seulement.length || seulement.includes('modification')) {
    const pagnes = resultats.find((r) => r.nom === (process.env.MODIFIER || 'pagnes'));
    await page.goto(`${base}/projets/${pagnes.projet}`);
    await page.locator('[data-message-input]').fill('Mets le prix en plus gros.');
    const debut = Date.now();
    await page.locator('[data-prompt-send]').first().click();
    await attendre(6000);
    let statut = await attendreFin(page);
    statut = await attendreFin(page);
    console.log('modification', statut, Math.round((Date.now() - debut) / 1000), 's');
    resultats.push({ nom: 'modification', projet: pagnes.projet, statut, secondes: Math.round((Date.now() - debut) / 1000) });
    await page.goto(`${base}/projets/${pagnes.projet}?vue=apercu`);
    await attendre(2500);
    await page.screenshot({ path: `${dossier}/${compte}-telephone-modification.png` });
  }
  writeFileSync(fichierResultats, JSON.stringify(resultats, null, 2));
  await navigateur.close();
})();
