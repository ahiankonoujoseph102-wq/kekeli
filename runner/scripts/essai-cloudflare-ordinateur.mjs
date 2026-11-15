// Essai réel : la création d'images gratuite (Cloudflare Workers AI) depuis l'intérieur d'un ordinateur E2B, avec
// le vrai outil de l'agent (images.mjs) et la clé ajoutée par le proxy de sortie d'E2B (secrets « kekeli_cloudflare »
// et « kekeli_gemini »), comme pendant un vrai travail. Aucune clé n'est affichée.
// Usage : node scripts/essai-cloudflare-ordinateur.mjs
import { Sandbox, Secret } from 'e2b'
import { readFile } from 'node:fs/promises'
import { reglesReseau, reglagesOutils, fournisseursAvecCle } from '../src/fournisseurs.js'
import { synchroniserSecrets, VARIABLES_CERTIFICATS } from '../src/ordinateurs.js'

const toutes = fournisseursAvecCle()
const cles = new Set(['cloudflare', 'gemini'].filter((n) => toutes.has(n)))
await synchroniserSecrets(cles)
const reglages = reglagesOutils({ pays: 'Togo', images_restantes: 3 }, cles)
console.log('réglages de l’outil :', JSON.stringify({ ...reglages, cloudflare_compte: reglages.cloudflare_compte ? 'présent' : null }))
const sandbox = await Sandbox.create('opencode', {
  timeoutMs: 5 * 60 * 1000,
  envs: process.env.SANS_CERTIFICATS ? {} : VARIABLES_CERTIFICATS,
  network: { allowOut: ['api.cloudflare.com', 'generativelanguage.googleapis.com'], denyOut: ['0.0.0.0/0'], rules: reglesReseau(cles, (nom) => Secret.fill(nom)) },
})
try {
  for (const f of ['images.mjs', 'photos.mjs']) await sandbox.files.write(`/home/user/.kekeli/${f}`, await readFile(new URL(`../src/outils/${f}`, import.meta.url), 'utf8'))
  await sandbox.files.write('/home/user/.kekeli/reglages.json', JSON.stringify(reglages))
  await sandbox.commands.run('mkdir -p /home/user/projet/images')
  const debut = Date.now()
  const r = await sandbox.commands.run('cd /home/user/.kekeli && node images.mjs creer "a stack of colorful african wax print fabrics in a small shop, soft daylight" --fichier images/pagnes.jpg; ls -la /home/user/projet/images', { timeoutMs: 180000 }).catch((e) => e)
  console.log(`durée : ${Math.round((Date.now() - debut) / 1000)} s`)
  console.log((r.stdout ?? '').trim().slice(0, 800), (r.stderr ?? '').trim().slice(0, 800))
} finally {
  await sandbox.kill()
}
