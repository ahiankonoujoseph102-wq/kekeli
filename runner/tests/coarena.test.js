// Coarena : registre des clés (noms seulement), comptes, basculement, classement des erreurs, passerelle et premier
// répondant (docs/coarena.md). Aucun appel réel : un faux Coarena répond.
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { RegistreCoarena, classerErreur, controlerCle, empreinteCompte, minuitUtcSuivant, nomsCles, registrePour, baseCoarena, MODELES_COARENA } from '../src/coarena.js'
import { passerOpenAI, jetonPasserelle } from '../src/passerelle.js'
import { premierRepondant } from '../src/repondant.js'
import { configurationOpencode } from '../src/ordinateurs.js'
import { modeleAutorise, modeleOpencode } from '../src/fournisseurs.js'

const ENV = { COARENA_API_KEY: 'cle-une', COARENA_API_KEY_2: 'cle-deux', COARENA_API_KEY_10: 'cle-dix', COARENA_API_KEY_3: '', AUTRE: 'x' }

test('les clés sont lues dans l’ordre, sans changer le code, et seulement leur nom circule', () => {
  assert.deepEqual(nomsCles(ENV), ['COARENA_API_KEY', 'COARENA_API_KEY_2', 'COARENA_API_KEY_10'])
  const etat = new RegistreCoarena({ env: ENV }).etat()
  assert.ok(!JSON.stringify(etat).includes('cle-une'))
})

test('l’adresse de Coarena ne peut pas être détournée', () => {
  assert.equal(baseCoarena({ COARENA_BASE_URL: 'https://coarena.ai/api/v1/' }), 'https://coarena.ai/api/v1')
  assert.equal(baseCoarena({ COARENA_BASE_URL: 'https://pirate.example/api/v1' }), 'https://coarena.ai/api/v1')
})

test('les erreurs sont classées : clé, quota, débit, modèle, panne', () => {
  assert.equal(classerErreur(401, 'invalid api key'), 'cle')
  assert.equal(classerErreur(429, '{"error":{"code":"insufficient_quota","message":"Daily free limit reached"}}'), 'quota')
  assert.equal(classerErreur(429, 'rate limit'), 'debit')
  assert.equal(classerErreur(400, 'The model provider rejected the request: You have reached your specified API usage limits. You will regain access on 2026-12-01'), 'modele')
  assert.equal(classerErreur(503, 'This model is not available right now'), 'modele')
  assert.equal(classerErreur(503, 'The model provider is at capacity for Coarena right now. Try again shortly.'), 'sature')
  assert.equal(classerErreur(502, 'bad gateway'), 'fournisseur')
  assert.equal(classerErreur(0, 'The operation was aborted due to timeout'), 'fournisseur')
})

test('une clé expirée laisse la place à une autre clé du MÊME compte ; le quota est celui du compte', () => {
  const t = Date.UTC(2026, 10, 14, 15)
  const r = new RegistreCoarena({ env: ENV, maintenant: () => t })
  r.noter('COARENA_API_KEY', 'compte-a')
  r.noter('COARENA_API_KEY_2', 'compte-a')
  r.noter('COARENA_API_KEY_10', 'compte-b')
  // Les clés du compte de la principale d'abord, puis les autres comptes.
  assert.deepEqual(r.clesUtilisables(), ['COARENA_API_KEY', 'COARENA_API_KEY_2', 'COARENA_API_KEY_10'])
  r.regler({ comptes_multiples: false })
  assert.deepEqual(r.clesUtilisables(), ['COARENA_API_KEY', 'COARENA_API_KEY_2'])
  r.signaler('COARENA_API_KEY', 'cle')
  assert.equal(r.cleActive(), 'COARENA_API_KEY_2')
  // Quota épuisé : tout le compte A attend minuit UTC ; changer de clé dans le même compte ne sert à rien.
  r.signaler('COARENA_API_KEY_2', 'quota')
  assert.equal(r.cleActive(), null)
  assert.equal(r.disponible(), false)
  assert.equal(minuitUtcSuivant(t), Date.UTC(2026, 10, 15))
})

test('quota d’un compte épuisé : le compte suivant prend le relais ; l’interrupteur de l’administration le coupe', () => {
  const r = new RegistreCoarena({ env: ENV })
  r.noter('COARENA_API_KEY', 'compte-a')
  r.noter('COARENA_API_KEY_2', 'compte-b')
  r.noter('COARENA_API_KEY_10', 'compte-c')
  assert.equal(r.cleActive(), 'COARENA_API_KEY')
  r.signaler('COARENA_API_KEY', 'quota')
  assert.equal(r.cleActive(), 'COARENA_API_KEY_2')
  r.regler({ comptes_multiples: true, cles_desactivees: ['COARENA_API_KEY_2'] })
  assert.equal(r.cleActive(), 'COARENA_API_KEY_10')
  r.regler({ comptes_multiples: false })
  assert.equal(r.cleActive(), null)
})

test('une clé trop sollicitée est mise de côté 20 secondes, puis revient', () => {
  let t = 0
  const r = new RegistreCoarena({ env: ENV, maintenant: () => t })
  r.signaler('COARENA_API_KEY', 'debit')
  assert.notEqual(r.cleActive(), 'COARENA_API_KEY')
  t = 21000
  assert.equal(r.cleActive(), 'COARENA_API_KEY')
})

test('les statistiques de chaque compte sont tenues, sans aucune clé', () => {
  const r = new RegistreCoarena({ env: ENV })
  r.compter('COARENA_API_KEY', { ok: true, dureeMs: 1200 })
  r.signaler('COARENA_API_KEY', 'sature')
  const etat = r.etat().find((c) => c.nom === 'COARENA_API_KEY')
  assert.equal(etat.statistiques.demandes, 2)
  assert.equal(etat.statistiques.reussites, 1)
  assert.equal(etat.statistiques.erreurs.sature, 1)
  assert.ok(!JSON.stringify(r.etat()).includes('cle-une'))
})

test('l’empreinte d’un compte ne révèle rien et ne dépend pas de l’ordre', () => {
  assert.equal(empreinteCompte(['b', 'a']), empreinteCompte(['a', 'b']))
  assert.match(empreinteCompte(['a']), /^compte-[0-9a-f]{10}$/)
  assert.equal(empreinteCompte([]), null)
})

const fauxCoarena = (journal, reponses) => async (url, options = {}) => {
  journal.push({ url, auth: options.headers?.Authorization, corps: options.body ? JSON.parse(options.body) : null })
  const chemin = new URL(url).pathname.replace('/api/v1', '')
  const r = reponses[chemin]
  const valeur = typeof r === 'function' ? r(options) : r
  return new Response(JSON.stringify(valeur.corps), { status: valeur.statut ?? 200, headers: { 'content-type': 'application/json' } })
}

test('le contrôle d’une clé donne validité, compte, expiration, quota, modèles et durée, sans la clé', async () => {
  const journal = []
  const appeler = fauxCoarena(journal, {
    '/keys': { corps: { data: [{ id: 'k1', this_key: true, status: 'active', expires_at: Date.UTC(2027, 1, 12) }] } },
    '/limits': { corps: { data: { usd_per_day: 5, can_ask: true, next_steps_usd: [10, 25], requests_per_minute_account: 30, concurrency: 2, today: { used_usd: 0.1, left_usd: 4.9, requests: 3, resets_at: Date.UTC(2026, 10, 15) } } } },
    '/models': { corps: { data: [{ id: 'gpt-6-luna' }, { id: 'kimi-k3-1-pro' }] } },
    '/chat/completions': { corps: { choices: [{ message: { content: 'ok' } }] } },
  })
  const r = await controlerCle('COARENA_API_KEY', { env: ENV, appeler })
  assert.equal(r.valide, true)
  assert.equal(r.compte, empreinteCompte(['k1']))
  assert.equal(r.expire_le, '2027-02-12T00:00:00.000Z')
  assert.equal(r.quota.reste_usd, 4.9)
  assert.deepEqual(r.modeles, ['gpt-6-luna', 'kimi-k3-1-pro'])
  assert.equal(r.essai.ok, true)
  assert.ok(!JSON.stringify(r).includes('cle-une'))
  assert.equal(journal[0].auth, 'Bearer cle-une')
})

test('une clé refusée est signalée « cle », sans détail qui la contienne', async () => {
  const appeler = fauxCoarena([], { '/keys': { statut: 401, corps: { error: { message: 'Invalid API key ck_abcdefghijkl' } } } })
  const r = await controlerCle('COARENA_API_KEY', { env: ENV, appeler })
  assert.equal(r.valide, false)
  assert.equal(r.erreur, 'cle')
  assert.ok(!String(r.detail).includes('abcdefghijkl'))
})

test('la passerelle envoie un modèle « coarena-… » à Coarena avec la clé du registre, jamais un autre modèle', async () => {
  const env = { COARENA_API_KEY: 'cle-une' }
  const journal = []
  const appeler = fauxCoarena(journal, { '/responses': { corps: { output: [] } } })
  const jeton = jetonPasserelle('secret', 'projet1')
  const r = await passerOpenAI({ projet: 'projet1', chemin: 'responses', autorisation: `Bearer ${jeton}`, corps: JSON.stringify({ model: 'coarena-gpt-6-1-sol', input: 'x' }) }, { secretRunner: 'secret', appeler, env })
  assert.equal(r.status, 200)
  assert.equal(journal[0].url, 'https://coarena.ai/api/v1/responses')
  assert.equal(journal[0].corps.model, 'gpt-6-1-sol')
  assert.equal(journal[0].auth, 'Bearer cle-une')
  const refuse = await passerOpenAI({ projet: 'projet1', chemin: 'responses', autorisation: `Bearer ${jeton}`, corps: JSON.stringify({ model: 'coarena-gpt-6-astra' }) }, { secretRunner: 'secret', appeler, env })
  assert.equal(refuse.statut, 403)
})

test('pour l’agent, un quota épuisé passe au compte suivant, puis devient une limite quand plus aucun compte ne reste', async () => {
  const deux = { COARENA_API_KEY: 'cle-une', COARENA_API_KEY_2: 'cle-deux' }
  const journal = []
  const appelerDeux = fauxCoarena(journal, { '/responses': (o) => o.headers.Authorization === 'Bearer cle-une' ? { statut: 429, corps: { error: { code: 'insufficient_quota', message: 'Daily free limit reached' } } } : { corps: { output: [] } } })
  const jetonDeux = jetonPasserelle('secret', 'p3')
  const ok = await passerOpenAI({ projet: 'p3', chemin: 'responses', autorisation: `Bearer ${jetonDeux}`, corps: JSON.stringify({ model: 'coarena-gpt-6-luna' }) }, { secretRunner: 'secret', appeler: appelerDeux, env: deux })
  assert.equal(ok.status, 200)
  assert.deepEqual(journal.map((j) => j.auth), ['Bearer cle-une', 'Bearer cle-deux'])
  const env = { COARENA_API_KEY: 'cle-une' }
  const appeler = fauxCoarena([], { '/responses': { statut: 429, corps: { error: { code: 'insufficient_quota', message: 'Daily free limit reached' } } } })
  const jeton = jetonPasserelle('secret', 'p2')
  const r = await passerOpenAI({ projet: 'p2', chemin: 'responses', autorisation: `Bearer ${jeton}`, corps: JSON.stringify({ model: 'coarena-gpt-6-luna' }) }, { secretRunner: 'secret', appeler, env })
  assert.equal(r.statut, 429)
  assert.equal(registrePour(env).disponible(), false)
})

test('OpenCode reçoit les modèles Coarena par le relais, sans aucune clé', () => {
  const config = configurationOpencode(new Set(), [{ fournisseur: 'coarena', modele: 'gpt-6-1-sol' }], { jetonOpenAI: 'jeton-du-projet' })
  assert.ok(config.provider.openai.whitelist.includes('coarena-gpt-6-1-sol'))
  assert.ok(!JSON.stringify(config).includes('cle-une'))
  assert.equal(modeleOpencode({ fournisseur: 'coarena', modele: 'kimi-k3-1-pro' }), 'coarena-kimi-k3-1-pro')
  assert.equal(modeleAutorise('coarena', 'gpt-6-astra'), false)
  assert.ok(MODELES_COARENA.includes('gpt-6-1-sol'))
})

test('le premier répondant reprend la même demande avec le compte suivant quand le quota d’un compte est épuisé', async () => {
  const env = { COARENA_API_KEY: 'cle-une', COARENA_API_KEY_2: 'cle-deux' }
  const journal = []
  const appeler = fauxCoarena(journal, {
    '/chat/completions': (o) => o.headers.Authorization === 'Bearer cle-une'
      ? { statut: 429, corps: { error: { code: 'insufficient_quota', message: 'Daily free limit reached: $5.00 of model usage today.' } } }
      : { corps: { choices: [{ message: { content: 'Bonjour !' } }], usage: { prompt_tokens: 10, completion_tokens: 3 } } },
  })
  const r = await premierRepondant({ lieu: 'discussion', message: 'Bonjour', contexte: {}, historique: [], fournisseurs: [{ fournisseur: 'coarena', modele: 'gpt-6-luna' }] }, { clesPresentes: new Set(), appeler, env })
  assert.equal(r.ok, true)
  assert.equal(r.reponse, 'Bonjour !')
  assert.deepEqual(journal.map((j) => j.auth), ['Bearer cle-une', 'Bearer cle-deux'])
  assert.deepEqual(r.refus, [])
})

test('un modèle saturé laisse la place au modèle suivant sans être mis de côté', async () => {
  const env = { COARENA_API_KEY: 'cle-une' }
  const appeler = fauxCoarena([], {
    '/chat/completions': (o) => JSON.parse(o.body).model === 'deepseek-v4-1-flash-1017'
      ? { statut: 503, corps: { error: { message: 'The model provider is at capacity for Coarena right now. Try again shortly.' } } }
      : { corps: { choices: [{ message: { content: 'Réponse.' } }] } },
  })
  const r = await premierRepondant({ lieu: 'discussion', message: 'Bonjour', contexte: {}, historique: [], fournisseurs: [{ fournisseur: 'coarena', modele: 'deepseek-v4-1-flash-1017' }, { fournisseur: 'coarena', modele: 'glm-5-3-flash' }] }, { clesPresentes: new Set(), appeler, env })
  assert.equal(r.modele, 'glm-5-3-flash')
  assert.deepEqual(r.refus, [])
})

test('le premier répondant passe au modèle suivant quand un modèle Coarena est indisponible, sans rien dire au client', async () => {
  const env = { COARENA_API_KEY: 'cle-une' }
  const journal = []
  const appeler = fauxCoarena(journal, {
    '/chat/completions': (options) => {
      const corps = JSON.parse(options.body)
      return corps.model === 'claude-sonnet-5-5'
        ? { statut: 400, corps: { error: { message: 'The model provider rejected the request: You have reached your specified API usage limits.' } } }
        : { corps: { choices: [{ message: { content: 'Bonjour ! Comment puis-je vous aider ?' } }], usage: { prompt_tokens: 100, completion_tokens: 10 } } }
    },
  })
  const r = await premierRepondant({ lieu: 'discussion', message: 'Bonjour', contexte: {}, historique: [], fournisseurs: [{ fournisseur: 'coarena', modele: 'claude-sonnet-5-5' }, { fournisseur: 'coarena', modele: 'gpt-6-1-terra' }] }, { clesPresentes: new Set(), appeler, env })
  assert.equal(r.ok, true)
  assert.equal(r.modele, 'gpt-6-1-terra')
  assert.deepEqual(r.refus, [{ fournisseur: 'coarena', modele: 'claude-sonnet-5-5' }])
  assert.ok(journal.every((j) => j.auth === 'Bearer cle-une'))
})
