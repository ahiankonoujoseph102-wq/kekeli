// Mesure réelle des documents et des présentations, comme un client sur un téléphone simulé : chaque demande part de
// « Que voulez-vous créer aujourd'hui ? » ; un gros document passe par « Construire ce plan » ; la dernière demande
// joint un fichier Word avec des fautes. Puis une modification (« mets le titre en bleu ») pour une 2e version.
// Le site, la file d'attente et le Runner doivent tourner (avec le même KEKELI_RUNNER_SECRET).
//
//   php artisan tinker --execute="require 'scripts/preparer-essai-documents.php';"
//   node scripts/essai-documents.js docs/captures/documents
import { createRequire } from 'node:module';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const dossier = process.argv[2] || 'docs/captures/documents';
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123';
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));

const DEMANDES = [
  { nom: 'cv', texte: "Fais-moi un CV professionnel. Je m'appelle Esi Lawson, j'habite à Lomé (Bè), téléphone +228 90 12 34 56, e-mail esi.lawson@exemple.tg. Je suis comptable depuis 6 ans : 2020-2026 comptable chez Togo Distribution SARL (paie de 45 employés, déclarations fiscales, rapprochements bancaires), 2018-2020 assistante comptable au Cabinet Akakpo. Licence en comptabilité à l'Université de Lomé (2018). Logiciels : Sage, Excel avancé. Langues : français, éwé, anglais (bon niveau)." },
  { nom: 'lettre', texte: "Écris une lettre de motivation pour le poste de responsable comptable à la Banque Populaire du Togo, à Lomé, au nom d'Esi Lawson, comptable depuis 6 ans (paie, fiscalité, rapprochements bancaires)." },
  { nom: 'devis', texte: "Fais un devis en FCFA de ma boutique « Pagnes Ama » au Grand Marché de Lomé pour l'école Les Étoiles : 40 pagnes wax à 12 000 FCFA, la couture de 40 tenues à 5 000 FCFA et la livraison à 10 000 FCFA. Acompte de 50 % par Mixx by Yas ou Flooz." },
  { nom: 'rapport', texte: "Rédige un rapport de 6 pages sur le développement du commerce en ligne des petites boutiques à Lomé, avec un tableau des coûts de démarrage (site, téléphone, Mobile Money, livraison), des recommandations et une conclusion." },
  { nom: 'presentation', texte: "Fais une présentation de 8 diapositives pour Lomé IA School, une école de formation en intelligence artificielle à Adidogomé : nos formations (initiation 2 jours 25 000 FCFA, agents IA 5 jours 75 000 FCFA, automatisations 3 jours 45 000 FCFA), pour qui, comment s'inscrire." },
  { nom: 'correction', texte: 'Corrige les fautes d’orthographe et de grammaire de ce compte rendu, sans changer la mise en page.', fichier: `${dossier}/fichiers/compte-rendu-avec-fautes.docx` },
];

async function attendreVersion(page, debut) {
  for (let i = 0; i < 180; i++) {
    await attendre(5000);
    if (await page.locator('[data-build-plan]').count()) {
      await page.locator('[data-build-plan]').click();
      console.log('  plan accepté', Math.round((Date.now() - debut) / 1000), 's');
      await attendre(3000);
    }
    const cartes = page.locator('[data-run-status]');
    const statut = (await cartes.count()) ? await cartes.last().getAttribute('data-run-status') : null;
    if (['completed', 'failed', 'stopped'].includes(statut) && !(await page.locator('[data-build-plan]').count())) {
      const vrai = await page.locator('[data-run-status]').last().getAttribute('data-run-status');
      if (vrai !== 'running' && vrai !== 'queued') return vrai;
    }
  }
  return 'trop long';
}

(async () => {
  mkdirSync(dossier, { recursive: true });
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  const page = await (await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR', colorScheme: 'light' })).newPage();
  await page.goto(base + '/login');
  await page.fill('input[type=email]', 'esi-documents@exemple.tg');
  await page.fill('input[type=password]', 'motdepasse-solide-2026');
  await page.click('button[type=submit]');
  await page.waitForURL('**/projets**');
  // SEULEMENT=rapport,presentation : refaire seulement ces demandes (les autres résultats sont gardés).
  const seulement = (process.env.SEULEMENT || '').split(',').filter(Boolean);
  const anciens = seulement.length && existsSync(`${dossier}/essai.json`) ? JSON.parse(readFileSync(`${dossier}/essai.json`, 'utf8')) : [];
  const resultats = anciens.filter((r) => !seulement.includes(r.nom) && r.nom !== 'modification');

  for (const demande of DEMANDES.filter((d) => !seulement.length || seulement.includes(d.nom))) {
    const debut = Date.now();
    await page.goto(base + '/projets');
    await page.locator('textarea').first().fill(demande.texte);
    if (demande.fichier) await page.locator('[data-prompt-file-input] input').first().setInputFiles(demande.fichier);
    if (demande.fichier) await attendre(2500);
    await page.locator('[data-prompt-send]').first().click();
    await page.waitForURL('**/projets/**', { timeout: 60000 });
    const statut = await attendreVersion(page, debut);
    const projet = page.url().split('/projets/')[1].split('?')[0];
    const secondes = Math.round((Date.now() - debut) / 1000);
    console.log(demande.nom, statut, secondes, 's', projet);
    resultats.splice(DEMANDES.findIndex((d) => d.nom === demande.nom), 0, { nom: demande.nom, projet, statut, secondes });
    await page.goto(`${base}/projets/${projet}?vue=apercu`);
    await page.locator('[data-page]').first().waitFor({ timeout: 20000 }).catch(() => {});
    await attendre(1500);
    await page.screenshot({ path: `${dossier}/essai-telephone-${demande.nom}.png` });
  }

  // Une modification : une nouvelle version, l'ancienne reste.
  const lettre = resultats.find((r) => r.nom === 'lettre');
  await page.goto(`${base}/projets/${lettre.projet}`);
  await page.locator('[data-message-input]').fill('Mets le titre et l’objet en bleu et ajoute une phrase sur ma maîtrise de Sage.');
  const debut = Date.now();
  await page.locator('[data-prompt-send]').first().click();
  await attendre(4000);
  const statut = await attendreVersion(page, debut);
  console.log('modification', statut, Math.round((Date.now() - debut) / 1000), 's');
  resultats.push({ nom: 'modification', projet: lettre.projet, statut, secondes: Math.round((Date.now() - debut) / 1000) });

  writeFileSync(`${dossier}/essai.json`, JSON.stringify(resultats, null, 2));
  await navigateur.close();
})();
