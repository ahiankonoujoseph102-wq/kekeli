// FreeLLMAPI (licence MIT, https://github.com/tashfeenahmed/freellmapi) à côté du Runner : une source de capacité
// gratuite de plus (Groq, LongCat, Z.ai et les autres fournisseurs validés), derrière une seule adresse compatible
// OpenAI. Décision du porteur du projet du 15 novembre 2026 (docs/plan.md, partie 26.37) : il remplace Coarena.
//
// Architecture :
//  - FreeLLMAPI tourne dans un processus voisin, sur la même machine que le Runner, et n'écoute que sur 127.0.0.1 :
//    personne d'autre que le Runner ne peut l'appeler (ni le site, ni un client, ni l'ordinateur virtuel) ;
//  - il ne reçoit que les clés des fournisseurs qui passent par lui (jamais OpenAI, Gemini, OpenRouter ni Cloudflare,
//    que Kekeli appelle directement : un même quota ne doit pas être partagé entre deux routeurs) ;
//  - sa clé d'accès locale est lue au démarrage et gardée en mémoire seulement ; son chiffrement, son compte
//    d'administration et le jeton de sa sauvegarde sont tirés du secret du Runner (aucun nouveau secret à créer) ;
//  - sa base (compteurs de quotas, pauses des clés) est sauvegardée chez le site, pour survivre au sommeil du Runner
//    sur Laravel Cloud, chiffrée avec une clé que seul le Runner connaît (FREELLMAPI_CLE_SAUVEGARDE) : le site range un
//    fichier qu'il ne peut pas lire ;
//  - Kekeli garde la main sur les clients : énergie, limites, priorité et comptage restent dans le site ; ici, une seule
//    demande à la fois par client et un plafond de demandes simultanées.
import { spawn } from 'node:child_process'
import { createHmac } from 'node:crypto'
import { existsSync, mkdirSync } from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

/** Version figée de FreeLLMAPI (commit exact), mise à jour volontairement après essai (comme OpenCode). */
export const VERSION_FREELLMAPI = 'bce469c3994f460b97cbf0e2b32fc2e47fcd4e88'

export const PORT_PAR_DEFAUT = 3101

/** Dossier où runner/freellmapi/installer.mjs construit FreeLLMAPI. */
export const DOSSIER_PAR_DEFAUT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../freellmapi/depot')

/**
 * Fournisseurs que Kekeli fait passer par FreeLLMAPI, avec la variable de leur clé (dans le Runner seulement).
 * Chacun n'est actif que si sa clé est présente. Conditions lues le 15 novembre 2026 (docs/freellmapi.md) :
 *  - Groq : intégration dans une application autorisée (contrat GroqCloud), aucun entraînement ;
 *  - LongCat (Meituan) : quota gratuit par jour, au-delà la demande est refusée (aucune facture sans recharge) ;
 *  - Z.ai : intégration dans une application autorisée (Additional Terms, 1.a et 1.b), l'usage des modèles de Z.ai
 *    doit être indiqué (politique de confidentialité) ; seuls ses modèles gratuits sont permis (MODELES_FREELLMAPI).
 * Les revendeurs (Nara, xKiro, LLM7, Kilo, Requesty, Routeway, SEA-LION, BazaarLink, ElectronHub) ne sont branchés
 * que lorsque leurs conditions ont été lues et qu'ils sont nommés dans FREELLMAPI_PLATEFORMES_VALIDEES.
 * Écartés pour une raison réelle : NVIDIA (évaluation seulement), Cohere (pas en production), Zhipu Chine et
 * ModelScope (non commercial), DreamPrompting (revend les offres gratuites d'autres, dont NVIDIA), AI Horde (aucun
 * outil, demandes lues par des bénévoles).
 */
export const PLATEFORMES_FREELLMAPI = {
  groq: { variableCle: 'GROQ_API_KEY' },
  longcat: { variableCle: 'LONGCAT_API_KEY' },
  zhipu: { variableCle: 'ZAI_API_KEY', baseUrl: 'https://api.z.ai/api/paas/v4' },
}

export const PLATEFORMES_A_VALIDER = {
  nara: { variableCle: 'NARA_API_KEY' },
  xkiro: { variableCle: 'XKIRO_API_KEY' },
  llm7: { variableCle: 'LLM7_API_KEY' },
  kilo: { variableCle: 'KILO_API_KEY' },
  requesty: { variableCle: 'REQUESTY_API_KEY' },
  routeway: { variableCle: 'ROUTEWAY_API_KEY' },
  sealion: { variableCle: 'SEALION_API_KEY' },
  bazaarlink: { variableCle: 'BAZAARLINK_API_KEY' },
  electronhub: { variableCle: 'ELECTRONHUB_API_KEY' },
}

/**
 * Modèles (familles de FreeLLMAPI) que Kekeli peut demander : liste fermée, jamais « auto », jamais un modèle payant
 * (chez Z.ai, seulement les modèles gratuits ; GLM-5.3-Flash y est payant). Une famille regroupe le même modèle chez
 * plusieurs fournisseurs : FreeLLMAPI prend celui qui a encore du quota.
 */
export const MODELES_FREELLMAPI = [
  'gpt-oss-120b',
  'gpt-oss-20b',
  'qwen3.8-27b',
  'glm-4.7-flash',
  'glm-4.5-flash',
  'longcat-2.5',
  'longcat-flash-chat',
  'longcat-flash-lite',
]

export function modeleFreellmapiPermis(modele) {
  return typeof modele === 'string' && MODELES_FREELLMAPI.includes(modele.toLowerCase())
}

function valeur(env, nom) {
  return String(env[nom] ?? '').trim().replace(/^[\s\[\]«»"'`<>()]+|[\s\[\]«»"'`<>()]+$/g, '')
}

/** Fournisseurs validés qui ont leur clé dans l'environnement (seulement les NOMS, jamais la valeur). */
export function plateformesActives(env = process.env) {
  const validees = new Set(String(env.FREELLMAPI_PLATEFORMES_VALIDEES ?? '').split(/[\s,]+/).filter(Boolean))
  const toutes = { ...PLATEFORMES_FREELLMAPI, ...Object.fromEntries(Object.entries(PLATEFORMES_A_VALIDER).filter(([nom]) => validees.has(nom))) }
  return Object.entries(toutes).filter(([, p]) => Boolean(valeur(env, p.variableCle))).map(([nom]) => nom)
}

/** FreeLLMAPI est-il demandé (interrupteur, au moins une clé, secret du Runner pour tirer ses secrets) ? */
export function freellmapiDemande(env = process.env) {
  return !/^(0|non|false|off)$/i.test(String(env.FREELLMAPI_ACTIF ?? '1')) && plateformesActives(env).length > 0 && Boolean(env.KEKELI_RUNNER_SECRET)
}

/** Secret tiré du secret du Runner : même valeur à chaque démarrage, jamais écrite nulle part. */
export function secretDerive(secretRunner, usage) {
  return createHmac('sha256', String(secretRunner)).update(`freellmapi:${usage}`).digest('hex')
}

export const COMPTE_ADMIN = 'runner@kekeli.local'

/** Configuration donnée à FreeLLMAPI au démarrage (FREEAPI_CONFIG_JSON) : clés des fournisseurs actifs, compte. */
export function configurationDeclarative(env = process.env) {
  const toutes = { ...PLATEFORMES_FREELLMAPI, ...PLATEFORMES_A_VALIDER }
  return {
    admin: { email: COMPTE_ADMIN, password: secretDerive(env.KEKELI_RUNNER_SECRET, 'admin') },
    keys: plateformesActives(env).map((nom) => ({ platform: nom, key: valeur(env, toutes[nom].variableCle), label: 'kekeli', ...(toutes[nom].baseUrl ? { baseUrl: toutes[nom].baseUrl } : {}) })),
    // « fastest » : parmi les fournisseurs qui servent la famille demandée, le plus rapide qui a encore du quota.
    routing: { strategy: 'fastest' },
  }
}

/**
 * Empreinte courte du secret du Runner et de la clé de sauvegarde : la base locale et sa sauvegarde en portent le nom.
 * Si l'un des deux change, FreeLLMAPI repart d'une base neuve (l'ancienne ne pourrait plus être déchiffrée).
 */
export function empreinte(env = process.env) {
  return secretDerive(env.KEKELI_RUNNER_SECRET, `empreinte:${String(env.FREELLMAPI_CLE_SAUVEGARDE ?? '').trim()}`).slice(0, 12)
}

/**
 * Adresse du site où ranger la sauvegarde chiffrée de la base de FreeLLMAPI, ou null (pas de sauvegarde) : il faut
 * l'adresse du site et la clé de chiffrement propre au Runner (64 caractères hexadécimaux).
 */
export function adresseSauvegarde(env = process.env) {
  const site = String(env.KEKELI_SITE_ADRESSE ?? '').trim().replace(/\/+$/, '')
  if (!/^[0-9a-f]{64}$/i.test(String(env.FREELLMAPI_CLE_SAUVEGARDE ?? '').trim())) return null
  return /^https?:\/\//.test(site) ? `${site}/runner/freellmapi/sauvegarde?empreinte=${empreinte(env)}` : null
}

/** Jeton de la sauvegarde, que le site vérifie (même calcul côté site : App\Agent\FreeLlmApi::backupToken). */
export function jetonSauvegarde(secretRunner) {
  return secretDerive(secretRunner, 'sauvegarde')
}

/**
 * Variables d'environnement du processus voisin : le minimum, jamais les autres clés du Runner (OpenAI, Gemini,
 * OpenRouter, Cloudflare, E2B…). Les clés de ses propres fournisseurs passent seulement par FREEAPI_CONFIG_JSON.
 */
export function environnementVoisin(env = process.env, { port = PORT_PAR_DEFAUT, dossierDonnees, sansSauvegarde = false } = {}) {
  const sauvegarde = sansSauvegarde ? null : adresseSauvegarde(env)
  const garder = ['PATH', 'HOME', 'LANG', 'TZ', 'HTTPS_PROXY', 'HTTP_PROXY', 'NO_PROXY', 'https_proxy', 'http_proxy', 'no_proxy', 'NODE_EXTRA_CA_CERTS', 'SSL_CERT_FILE']
  return {
    ...Object.fromEntries(garder.filter((nom) => env[nom] !== undefined).map((nom) => [nom, env[nom]])),
    NODE_ENV: 'production',
    HOST: '127.0.0.1',
    PORT: String(port),
    ENCRYPTION_KEY: secretDerive(env.KEKELI_RUNNER_SECRET, 'chiffrement'),
    FREEAPI_CONFIG_JSON: JSON.stringify(configurationDeclarative(env)),
    FREEAPI_DB_PATH: path.join(dossierDonnees, 'freellmapi.db'),
    FREEAPI_DB_DIR_HARDENING: '1',
    // Aucune réponse gardée pour la donner à un autre client ; adresse des clients jamais notée.
    RESPONSE_CACHE: 'off',
    REQUEST_ANALYTICS_LOG_CLIENT: '0',
    // Une demande ne doit jamais traîner : au-delà, Kekeli passe à son fournisseur suivant.
    FALLBACK_TIME_BUDGET_MS: String(Number(env.FREELLMAPI_BUDGET_SECOURS_MS) || 8000),
    ...(sauvegarde ? { FREEAPI_DB_BACKUP_URL: sauvegarde, FREEAPI_DB_BACKUP_TOKEN: jetonSauvegarde(env.KEKELI_RUNNER_SECRET), FREEAPI_DB_BACKUP_KEY: String(env.FREELLMAPI_CLE_SAUVEGARDE).trim(), FREEAPI_DB_BACKUP_INTERVAL_MS: '120000' } : {}),
  }
}

/**
 * Une seule demande à la fois par client et un plafond global : un client ne peut pas prendre toute la capacité
 * gratuite. Quand la place n'est pas libre, on ne fait pas attendre : Kekeli passe à son fournisseur suivant.
 */
export class PlacesFreellmapi {
  constructor(maximum = 6) {
    this.maximum = Math.max(1, Number(maximum) || 6)
    this.occupees = new Map()
    this.total = 0
  }

  prendre(client) {
    const cle = String(client || 'anonyme')
    if (this.total >= this.maximum || (this.occupees.get(cle) ?? 0) >= 1) return null
    this.occupees.set(cle, (this.occupees.get(cle) ?? 0) + 1)
    this.total++
    let rendue = false
    return () => {
      if (rendue) return
      rendue = true
      this.total--
      const reste = (this.occupees.get(cle) ?? 1) - 1
      if (reste <= 0) this.occupees.delete(cle)
      else this.occupees.set(cle, reste)
    }
  }
}

/** Lit l'en-tête « X-Routed-Via » (« groq/openai/gpt-oss-120b ») : le fournisseur et le modèle qui ont répondu. */
export function routeServie(entetes) {
  const brut = String(entetes?.get?.('x-routed-via') ?? '').trim()
  if (!brut || !/^[a-z0-9-]+\/[\w.:@/-]+$/i.test(brut) || brut.length > 120) return null
  const [plateforme] = brut.split('/')
  return { plateforme, modele: brut }
}

/**
 * Le processus voisin : démarrage, attente, clé locale en mémoire, redémarrage après un arrêt inattendu.
 * `lancer` et `appeler` sont remplaçables pour les tests.
 */
export class Freellmapi {
  constructor({ env = process.env, dossier = env.FREELLMAPI_DOSSIER || DOSSIER_PAR_DEFAUT, port = Number(env.FREELLMAPI_PORT) || PORT_PAR_DEFAUT, lancer = spawn, appeler = fetch, journal = console } = {}) {
    this.env = env
    this.dossier = dossier
    this.port = port
    this.lancer = lancer
    this.appeler = appeler
    this.journal = journal
    this.processus = null
    this.cleLocale = null
    this.erreur = null
    this.redemarrages = 0
    this.arrete = false
    this.places = new PlacesFreellmapi(Number(env.FREELLMAPI_SIMULTANEES) || 6)
    // Une sauvegarde illisible (clé changée) arrête FreeLLMAPI au démarrage : après deux échecs de suite, il repart
    // sans sauvegarde (les quotas se réapprennent avec les refus des fournisseurs).
    this.echecsDemarrage = 0
    this.sansSauvegarde = false
  }

  get adresse() {
    return `http://127.0.0.1:${this.port}`
  }

  /** Prêt à servir : processus lancé et clé locale lue. */
  pret() {
    return Boolean(this.cleLocale)
  }

  cle() {
    return this.cleLocale
  }

  etat() {
    return { demande: freellmapiDemande(this.env), pret: this.pret(), plateformes: plateformesActives(this.env), version: VERSION_FREELLMAPI, sauvegarde: Boolean(adresseSauvegarde(this.env)) && !this.sansSauvegarde, erreur: this.erreur, redemarrages: this.redemarrages }
  }

  async demarrer() {
    if (!freellmapiDemande(this.env)) return false
    const entree = path.join(this.dossier, 'server/dist/index.js')
    if (!existsSync(entree)) {
      this.erreur = 'non installé (npm run freellmapi:installer)'
      return false
    }
    const donnees = path.join(os.tmpdir(), `kekeli-freellmapi-${empreinte(this.env)}`)
    mkdirSync(donnees, { recursive: true, mode: 0o700 })
    this.arrete = false
    if (this.echecsDemarrage >= 2) this.sansSauvegarde = true
    this.processus = this.lancer(process.execPath, [entree], { cwd: path.join(this.dossier, 'server'), env: environnementVoisin(this.env, { port: this.port, dossierDonnees: donnees, sansSauvegarde: this.sansSauvegarde }), stdio: ['ignore', 'pipe', 'pipe'] })
    // Le journal de FreeLLMAPI ne contient jamais de clé (il ne les affiche pas) ; on n'en garde que les erreurs.
    this.processus.stderr?.on('data', (morceau) => this.journal.warn?.(`[freellmapi] ${String(morceau).trim().slice(0, 300)}`))
    this.processus.on('exit', (code) => {
      if (!this.cleLocale) this.echecsDemarrage++
      this.cleLocale = null
      this.processus = null
      if (this.arrete) return
      this.erreur = `arrêté (code ${code})`
      // Redémarrage avec une pause qui grandit (5 s, 10 s, 20 s… au plus 2 minutes).
      const pause = Math.min(120000, 5000 * 2 ** Math.min(this.redemarrages, 5))
      this.redemarrages++
      setTimeout(() => this.demarrer().catch(() => {}), pause).unref?.()
    })
    return this.attendrePret()
  }

  async attendrePret(delaiMs = 60000) {
    const fin = Date.now() + delaiMs
    while (Date.now() < fin && this.processus) {
      try {
        const cle = await this.lireCleLocale()
        if (cle) {
          this.cleLocale = cle
          this.erreur = null
          this.echecsDemarrage = 0
          return true
        }
      } catch {
        // Pas encore prêt.
      }
      await new Promise((r) => setTimeout(r, 500))
    }
    this.erreur = this.erreur ?? 'ne démarre pas'
    return false
  }

  /** Clé d'accès locale : connexion avec le compte d'administration créé par la configuration, puis lecture. */
  async lireCleLocale() {
    const connexion = await this.appeler(`${this.adresse}/api/auth/login`, { method: 'POST', headers: { 'content-type': 'application/json' }, body: JSON.stringify({ email: COMPTE_ADMIN, password: secretDerive(this.env.KEKELI_RUNNER_SECRET, 'admin') }), signal: AbortSignal.timeout(5000) })
    if (!connexion.ok) return null
    const { token } = await connexion.json()
    const reponse = await this.appeler(`${this.adresse}/api/settings/api-key`, { headers: { authorization: `Bearer ${token}` }, signal: AbortSignal.timeout(5000) })
    if (!reponse.ok) return null
    const { apiKey } = await reponse.json()
    return typeof apiKey === 'string' && apiKey.length > 16 ? apiKey : null
  }

  /** Jeton du tableau d'administration de FreeLLMAPI (jamais montré, jamais gardé hors de la mémoire). */
  async jetonAdmin() {
    const r = await this.appeler(`${this.adresse}/api/auth/login`, { method: 'POST', headers: { 'content-type': 'application/json' }, body: JSON.stringify({ email: COMPTE_ADMIN, password: secretDerive(this.env.KEKELI_RUNNER_SECRET, 'admin') }), signal: AbortSignal.timeout(5000) })
    return r.ok ? (await r.json()).token ?? null : null
  }

  /**
   * État de chaque fournisseur pour l'administration de Kekeli : actif, pause, consommation du mois, quota restant.
   * Seulement des noms et des nombres : jamais la clé, ni même sa version masquée.
   */
  async sante() {
    if (!this.pret()) return { fournisseurs: [], quotas: [] }
    try {
      const jeton = await this.jetonAdmin()
      if (!jeton) return { fournisseurs: [], quotas: [] }
      const lire = async (chemin) => {
        const r = await this.appeler(`${this.adresse}${chemin}`, { headers: { authorization: `Bearer ${jeton}` }, signal: AbortSignal.timeout(5000) })
        return r.ok ? r.json() : null
      }
      const [cles, gratuit] = await Promise.all([lire('/api/keys'), lire('/api/free-tier')])
      const fournisseurs = (Array.isArray(cles) ? cles : []).map((c) => ({
        nom: String(c.platform ?? ''),
        etat: String(c.status ?? 'unknown'),
        actif: c.enabled !== false,
        requetes_mois: Number(c.monthlyUsage?.requests ?? 0) || 0,
        tokens_mois: Number(c.monthlyUsage?.tokens ?? 0) || 0,
        pauses: (Array.isArray(c.cooldowns) ? c.cooldowns : []).length,
      }))
      const quotas = (Array.isArray(gratuit?.pools) ? gratuit.pools : []).map((p) => ({
        nom: String(p.platform ?? ''),
        modeles: Number(p.modelCount ?? 0) || 0,
        budget_mois: String(p.bestLabel ?? ''),
        restant: p.quota?.remaining ?? null,
        limite: p.quota?.limit ?? null,
        unite: String(p.quota?.metric ?? ''),
      }))
      return { fournisseurs, quotas }
    } catch {
      return { fournisseurs: [], quotas: [] }
    }
  }

  arreter() {
    this.arrete = true
    this.cleLocale = null
    this.processus?.kill('SIGTERM')
  }
}

let instance = null

/** Le processus voisin du Runner (un seul par Runner). Les tests peuvent en donner un autre. */
export function freellmapi() {
  if (!instance) instance = new Freellmapi()
  return instance
}

export function utiliserFreellmapi(autre) {
  instance = autre
}
