// Coarena (docs/coarena.md) : une interface compatible OpenAI qui donne accès à de nombreux modèles (GPT, Gemini,
// DeepSeek, Kimi, GLM, MiniMax, Claude quand il est disponible…), avec 5 $ gratuits par jour et par compte.
//
// Registre des clés : toutes les variables COARENA_API_KEY, COARENA_API_KEY_2, COARENA_API_KEY_3… sont lues, sans
// changer le code (on en ajoute ou on en retire dans l'environnement). Les clés restent dans le Runner : jamais dans
// un ordinateur virtuel, jamais renvoyées au site, jamais écrites dans un journal (seul leur NOM circule).
//
// Comptes : le registre reconnaît le COMPTE de chaque clé (les clés d'un même compte se voient entre elles par
// GET /keys ; rien n'est supposé). Le quota (5 $ par jour) est celui du compte : changer de clé dans le même compte ne
// le renouvelle pas. Une autre clé du MÊME compte prend le relais quand une clé est expirée ou révoquée.
// Basculement vers un AUTRE compte quand le quota d'un compte est épuisé ou qu'une clé est hors service : ACTIVÉ
// (décision du porteur du projet, propriétaire des 11 comptes, 14 novembre 2026), avec un interrupteur dans
// l'administration (réglage « coarena_multi_accounts »). Les conditions de Coarena ne l'interdisent pas ; elles disent
// que les limites peuvent changer et l'accès être retiré (docs/coarena.md, « Plusieurs comptes »).
import { createHash } from 'node:crypto'
import { valeurCle } from './fournisseurs.js'

export const BASE_PAR_DEFAUT = 'https://coarena.ai/api/v1'

/** Adresse de l'interface : seulement coarena.ai en https (un réglage erroné ne peut pas envoyer la clé ailleurs). */
export function baseCoarena(env = process.env) {
  const base = String(env.COARENA_BASE_URL ?? '').trim().replace(/\/+$/, '')
  return /^https:\/\/coarena\.ai\/api\/v1$/.test(base) ? base : BASE_PAR_DEFAUT
}

/** Noms des variables qui portent une clé, dans l'ordre : COARENA_API_KEY, puis _2, _3… (jamais leurs valeurs). */
export function nomsCles(env = process.env) {
  return Object.keys(env)
    .filter((nom) => /^COARENA_API_KEY(?:_(\d{1,3}))?$/.test(nom) && valeurCle(nom, env))
    .sort((a, b) => numero(a) - numero(b))
}

function numero(nom) {
  const m = /_(\d+)$/.exec(nom)
  return m ? Number(m[1]) : 1
}

export const CLE_PRINCIPALE = 'COARENA_API_KEY'

/**
 * Classement d'une erreur de Coarena, pour ne pas confondre une gêne passagère avec une limite :
 * « cle » (clé invalide, expirée ou révoquée), « quota » (5 $ du jour épuisés : jusqu'à minuit UTC), « debit » (trop de
 * demandes à la minute ou en même temps : passager), « sature » (modèle saturé un moment : un autre modèle prend le
 * relais), « modele » (modèle indisponible chez Coarena ou chez son fabricant, pour longtemps), « fournisseur » (Coarena
 * en panne ou trop lent : passager), « demande » (demande refusée).
 */
export function classerErreur(statut, contenu = '') {
  const texte = String(contenu)
  if (statut === 401 || /invalid[_ ]?api[_ ]?key|key (?:was )?revoked|expired key|unauthori[sz]ed/i.test(texte)) return 'cle'
  if (/insufficient_quota|daily (?:free )?limit|usd_per_day/i.test(texte)) return 'quota'
  if (statut === 429) return 'debit'
  if (/at capacity|try again shortly|overloaded/i.test(texte)) return 'sature'
  if (/not available right now|model_not_found|unknown model|provider rejected|usage limits|regain access/i.test(texte)) return 'modele'
  if (statut === 0 || statut >= 500 || /timeout|timed out|aborted/i.test(texte)) return 'fournisseur'
  return 'demande'
}

/** Une erreur qui vaut d'être réessayée tout de suite (une fois) avec la même clé et le même modèle. */
export function vautUnNouvelEssai(classe) {
  return classe === 'debit' || classe === 'fournisseur'
}

/**
 * État des clés, gardé en mémoire par le Runner : compte de chaque clé (connu après un contrôle), clé hors service.
 * Aucun secret ici : seulement des noms, des empreintes de compte et des dates.
 */
export class RegistreCoarena {
  constructor({ env = process.env, maintenant = () => Date.now() } = {}) {
    this.env = env
    this.maintenant = maintenant
    this.comptes = new Map() // nom -> empreinte du compte
    this.horsService = new Map() // nom -> { raison, jusqua }
    this.quotaEpuise = new Map() // empreinte du compte (ou nom) -> heure de remise à zéro
    this.comptesMultiples = true // basculement vers un autre compte : activé ; l'administration peut le couper
    this.desactivees = new Set() // clés éteintes dans l'administration
    this.stats = new Map() // nom -> { demandes, reussites, erreurs: { classe: nombre }, derniere_erreur, duree_totale_ms }
  }

  /** Réglages envoyés par le site avec chaque demande (administration) : basculement entre comptes, clés éteintes. */
  regler({ comptes_multiples = true, cles_desactivees = [] } = {}) {
    this.comptesMultiples = comptes_multiples !== false
    this.desactivees = new Set(Array.isArray(cles_desactivees) ? cles_desactivees.map(String) : [])
  }

  noter(nom, compte) {
    if (compte) this.comptes.set(nom, compte)
  }

  /**
   * Clés utilisables maintenant, dans l'ordre : la principale, les autres clés de SON compte, puis (seulement si le
   * basculement entre comptes est activé) les clés des autres comptes, compte par compte.
   */
  clesUtilisables() {
    const noms = nomsCles(this.env).filter((nom) => !this.desactivees.has(nom))
    if (!noms.length) return []
    const principale = noms.includes(CLE_PRINCIPALE) ? CLE_PRINCIPALE : noms[0]
    const compte = this.comptes.get(principale)
    const memeCompte = (nom) => nom === principale || (Boolean(compte) && this.comptes.get(nom) === compte)
    const permises = [...noms.filter(memeCompte), ...(this.comptesMultiples ? noms.filter((nom) => !memeCompte(nom)) : [])]
    const t = this.maintenant()
    return permises.filter((nom) => {
      const h = this.horsService.get(nom)
      if (h && h.jusqua > t) return false
      const q = this.quotaEpuise.get(this.comptes.get(nom) ?? nom)
      return !(q && q > t)
    })
  }

  /** La clé à utiliser maintenant (son nom), ou null si Coarena n'est pas utilisable. */
  cleActive() {
    return this.clesUtilisables()[0] ?? null
  }

  valeur(nom) {
    return nom ? valeurCle(nom, this.env) : ''
  }

  /**
   * Après une erreur : clé hors service (un jour), quota du compte épuisé (jusqu'à minuit UTC), ou clé trop sollicitée
   * (30 demandes par minute, 2 en même temps) mise de côté 20 secondes, le temps qu'un autre compte prenne le relais.
   */
  signaler(nom, classe) {
    if (!nom) return
    const t = this.maintenant()
    if (classe === 'cle') this.horsService.set(nom, { raison: 'cle', jusqua: t + 24 * 3600 * 1000 })
    if (classe === 'quota') this.quotaEpuise.set(this.comptes.get(nom) ?? nom, minuitUtcSuivant(t))
    if (classe === 'debit') this.horsService.set(nom, { raison: 'debit', jusqua: t + 20 * 1000 })
    this.compter(nom, { ok: false, classe })
  }

  /** Statistiques de chaque clé (donc de chaque compte) : demandes, réussites, erreurs par type, durée moyenne. */
  compter(nom, { ok, classe = null, dureeMs = 0 }) {
    if (!nom) return
    const s = this.stats.get(nom) ?? { demandes: 0, reussites: 0, erreurs: {}, derniere_erreur: null, duree_totale_ms: 0 }
    s.demandes++
    if (ok) {
      s.reussites++
      s.duree_totale_ms += dureeMs
    } else {
      s.erreurs[classe] = (s.erreurs[classe] ?? 0) + 1
      s.derniere_erreur = { classe, le: new Date(this.maintenant()).toISOString() }
    }
    this.stats.set(nom, s)
  }

  /** Coarena peut-il servir maintenant ? (au moins une clé, et le quota du compte n'est pas épuisé). */
  disponible() {
    return this.cleActive() !== null
  }

  etat() {
    const t = this.maintenant()
    return nomsCles(this.env).map((nom) => ({
      nom,
      compte: this.comptes.get(nom) ?? null,
      hors_service: (this.horsService.get(nom)?.jusqua ?? 0) > t,
      raison: (this.horsService.get(nom)?.jusqua ?? 0) > t ? this.horsService.get(nom).raison : null,
      desactivee: this.desactivees.has(nom),
      statistiques: this.stats.get(nom) ?? null,
      quota_epuise_jusqua: this.quotaEpuise.get(this.comptes.get(nom) ?? nom) ?? null,
    }))
  }
}

export function minuitUtcSuivant(t) {
  const d = new Date(t)
  return Date.UTC(d.getUTCFullYear(), d.getUTCMonth(), d.getUTCDate() + 1)
}

/** Empreinte d'un compte : ses identifiants de clés (vus par GET /keys), mélangés ; jamais réversible. */
export function empreinteCompte(idsCles) {
  const ids = [...new Set((idsCles ?? []).map(String))].sort()
  return ids.length ? 'compte-' + createHash('sha256').update(ids.join(',')).digest('hex').slice(0, 10) : null
}

/**
 * Contrôle d'une clé (bouton « Tester » de l'administration) : valide ou non, compte, expiration, quota du jour,
 * modèles accessibles, petite demande chronométrée. Ne renvoie jamais la clé.
 */
export async function controlerCle(nom, { env = process.env, appeler = fetch, modeleEssai = 'gpt-6-luna' } = {}) {
  const cle = valeurCle(nom, env)
  const base = baseCoarena(env)
  const resultat = { nom, valide: false, compte: null, expire_le: null, statut: null, quota: null, modeles: [], essai: null, erreur: null, controle_le: new Date().toISOString() }
  if (!cle) return { ...resultat, erreur: 'absente' }
  const entetes = { Authorization: `Bearer ${cle}`, 'Content-Type': 'application/json' }
  const lire = async (chemin, options = {}) => {
    const r = await appeler(`${base}${chemin}`, { headers: entetes, signal: AbortSignal.timeout(30000), ...options })
    const texte = await r.text()
    let donnees = null
    try {
      donnees = JSON.parse(texte)
    } catch {}
    return { statut: r.status, donnees, texte }
  }
  try {
    const cles = await lire('/keys')
    if (cles.statut !== 200) {
      return { ...resultat, erreur: classerErreur(cles.statut, cles.texte), detail: messageSur(cles.donnees) }
    }
    const liste = Array.isArray(cles.donnees?.data) ? cles.donnees.data : []
    const celleCi = liste.find((k) => k.this_key) ?? null
    resultat.valide = true
    resultat.compte = empreinteCompte(liste.map((k) => k.id))
    resultat.statut = celleCi?.status ?? null
    resultat.expire_le = celleCi?.expires_at ? new Date(celleCi.expires_at).toISOString() : null
    resultat.cles_du_compte = liste.length

    const limites = await lire('/limits')
    const d = limites.donnees?.data
    if (d) {
      resultat.quota = {
        usd_par_jour: d.usd_per_day ?? null,
        utilise_usd: d.today?.used_usd ?? null,
        reste_usd: d.today?.left_usd ?? null,
        demandes_aujourdhui: d.today?.requests ?? null,
        demandes_par_minute: d.requests_per_minute_account ?? null,
        en_meme_temps: d.concurrency ?? null,
        remise_a_zero: d.today?.resets_at ? new Date(d.today.resets_at).toISOString() : null,
        refus: d.today?.refused ?? null,
        peut_demander_plus: d.can_ask === true,
        paliers: d.next_steps_usd ?? [],
      }
    }

    const modeles = await lire('/models')
    resultat.modeles = Array.isArray(modeles.donnees?.data) ? modeles.donnees.data.map((m) => m.id) : []

    const debut = Date.now()
    const essai = await lire('/chat/completions', { method: 'POST', body: JSON.stringify({ model: modeleEssai, max_tokens: 20, messages: [{ role: 'user', content: 'Réponds seulement : ok' }] }) })
    resultat.essai = {
      modele: modeleEssai,
      ok: essai.statut === 200 && !essai.donnees?.error,
      duree_ms: Date.now() - debut,
      erreur: essai.statut === 200 && !essai.donnees?.error ? null : classerErreur(essai.statut, essai.texte),
      detail: essai.statut === 200 ? null : messageSur(essai.donnees),
    }
  } catch (erreur) {
    resultat.erreur = classerErreur(0, erreur.message)
  }
  return resultat
}

/** Message d'erreur de Coarena, raccourci et sans aucune clé. */
function messageSur(donnees) {
  return String(donnees?.error?.message ?? '').replace(/(sk|ck|key)[-_][A-Za-z0-9_-]{8,}/gi, '[masqué]').slice(0, 200) || null
}

/**
 * Demande officielle de capacité supplémentaire à Coarena (palier suivant : 10 $ par jour, puis 25 $, 50 $…), pour le
 * compte de la clé principale seulement. C'est le chemin prévu par Coarena pour avoir plus.
 */
export async function demanderPlus({ usdParJour = null, env = process.env, appeler = fetch } = {}) {
  const cle = valeurCle(CLE_PRINCIPALE, env)
  if (!cle) return { ok: false, erreur: 'absente' }
  const corps = usdParJour ? { usd_per_day: Number(usdParJour), note: 'Kekeli, assistant IA de Lomé IA School (Togo) : plus de demandes de nos clients.' } : {}
  const r = await appeler(`${baseCoarena(env)}/limits/requests`, { method: 'POST', headers: { Authorization: `Bearer ${cle}`, 'Content-Type': 'application/json' }, body: JSON.stringify(corps), signal: AbortSignal.timeout(30000) })
  let donnees = null
  try {
    donnees = await r.json()
  } catch {}
  return { ok: r.ok, statut: r.status, reponse: r.ok ? (donnees?.data ?? null) : null, erreur: r.ok ? null : messageSur(donnees) }
}

/**
 * Modèles de Coarena que Kekeli peut choisir : seulement ceux qui ont bien réussi le banc d'essai du 14 novembre 2026
 * (runner/mesures/modeles-coarena.md). Écartés : Gemini 4 Argon (lent, couleurs criardes, dollars non demandés),
 * DeepSeek V4.1 Flash (souvent saturé, « ```html » laissé dans la page), MiniMax M3.1 Flash (caractères chinois dans
 * le français, page presque vide). Claude : indisponible chez Coarena jusqu'au 1er décembre 2026, donc pas testé ; il
 * est permis mais dans aucune liste, à ajouter dans l'administration seulement après son passage au banc. Un réglage
 * erroné ne peut jamais choisir un autre modèle.
 */
export const MODELES_COARENA = [
  'gpt-6-luna',
  'gpt-6-1-terra',
  'gpt-6-1-sol',
  'gemini-3-8-flash',
  'glm-5-3-flash',
  'kimi-k3-1-pro',
  'claude-haiku-5-5',
  'claude-sonnet-5-5',
  'claude-opus-5-5',
]

const REGISTRES = new WeakMap()

/** Le registre des clés pour cet environnement (un seul par Runner ; un par environnement d'essai dans les tests). */
export function registrePour(env = process.env) {
  if (!REGISTRES.has(env)) REGISTRES.set(env, new RegistreCoarena({ env }))
  return REGISTRES.get(env)
}

/**
 * Au démarrage du Runner : le compte de chaque clé (GET /keys seulement, aucune demande à un modèle), pour savoir quelles
 * clés partagent un même quota.
 */
export async function apprendreComptes(registre, { appeler = fetch } = {}) {
  for (const nom of nomsCles(registre.env)) {
    try {
      const r = await appeler(`${baseCoarena(registre.env)}/keys`, { headers: { Authorization: `Bearer ${valeurCle(nom, registre.env)}` }, signal: AbortSignal.timeout(20000) })
      if (r.status === 401) registre.signaler(nom, 'cle')
      if (!r.ok) continue
      const d = await r.json()
      registre.noter(nom, empreinteCompte((d?.data ?? []).map((k) => k.id)))
    } catch {}
  }
  return registre.etat()
}
