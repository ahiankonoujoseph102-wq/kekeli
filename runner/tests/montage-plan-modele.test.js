import { test } from 'node:test'
import assert from 'node:assert/strict'
import { assemblerVoix, chiffresAConfirmer, consignesSysteme, couperMots, defautsDuPlan, ecrireMontage, ICONES_PROPOSEES } from '../src/videos/montage/plan-modele.js'
import { alignerSurLeTexte, retirerPlansTropCourts } from '../src/videos/montage/temps.js'
import { cheminLisible, formesIcone } from '../src/videos/montage/icones.js'

const MARQUE = { nom: 'Pagnes Ama', contact: '+228 90 22 33 44' }
const DEMANDE = 'Pub pour Pagnes Ama : pagnes à partir de 12 000 FCFA, couture en 7 jours. WhatsApp +228 90 22 33 44.'
const brut = (plans) => ({ marque: MARQUE, plans })
const BON = brut([
  { type: 'accroche', voix: 'Tu cherches du beau wax ?', texte: 'Du beau wax ?', icone: 'sparkles' },
  { type: 'etiquette', voix: 'Des pagnes dès 12 000 FCFA.', texte: 'Dès 12 000 FCFA', icone: 'tag', numero: 4 },
  { type: 'horloge', voix: 'Ta tenue prête en 7 jours.', texte: 'Prête en 7 jours' },
  { type: 'etiquette', voix: 'Livraison partout à Lomé.', texte: 'Livraison', icone: 'truck', numero: 9 },
  { type: 'numero', voix: 'Écris-nous au +228 90 22 33 44.', texte: 'Écris-nous', groupes: [{ texte: '+228', mot: '+228' }, { texte: '90', mot: '90' }, { texte: '22', mot: '22' }, { texte: '33', mot: '33' }, { texte: '44', mot: '44' }] },
  { type: 'carte', voix: 'Pagnes Ama, le wax qui te ressemble.' },
])

test('la voix découpée par plan donne le texte et les mots de départ, sans compter ni recopier', () => {
  const { brut: b } = assemblerVoix(BON)
  assert.equal(b.plans[1].mot, 'Des pagnes')
  assert.ok(b.texte_voix.startsWith('Tu cherches du beau wax ? Des pagnes'))
  // Le préfixe du pays n'est ni dit ni montré comme un groupe du numéro.
  assert.doesNotMatch(b.texte_voix, /\+228/)
  assert.deepEqual(b.plans[4].groupes.map((g) => g.texte), ['90', '22', '33', '44'])
  // Les étiquettes sont renumérotées dans l'ordre.
  assert.deepEqual(b.plans.filter((p) => p.type === 'etiquette').map((p) => p.numero), [1, 2])
  const { plan, defauts } = defautsDuPlan(BON, { sources: [DEMANDE] })
  assert.deepEqual(defauts, [])
  assert.equal(plan.plans.length, 6)
})

test('un plan trop long est coupé par le code aux meilleures places, en gardant son pictogramme', () => {
  assert.deepEqual(couperMots("Tu veux qu'on fasse un petit calcul ? Combien d'entreprises existent dans ta ville ?".split(' ')).map((m) => m.join(' ')), ["Tu veux qu'on", 'fasse un petit calcul ?', "Combien d'entreprises existent dans ta ville ?"])
  const long = brut([{ type: 'accroche', voix: 'Tu cherches du beau wax ?', texte: 'Du wax' }, { type: 'texte', voix: 'Nos pagnes wax sont beaux, solides et uniques, faits pour toi.', texte: 'Nos pagnes', icone: 'shirt' }, { type: 'carte', voix: 'Pagnes Ama.' }])
  const { plan, defauts } = defautsDuPlan(long, { sources: [DEMANDE] })
  assert.deepEqual(defauts, [])
  assert.equal(plan.plans.length, 4)
  assert.equal(plan.plans[2].type, 'texte')
  assert.equal(plan.plans[2].icone, 'shirt')
})

test('aucun chiffre inventé : ni à l’écran ni dans la voix', () => {
  const invente = brut([{ type: 'accroche', voix: 'Plus de 500 clients heureux !', texte: '500 clients' }, { type: 'compteur', voix: 'Des prix dès 12 000 FCFA.', texte: 'Prix', de: 0, a: 9000 }, { type: 'carte', voix: 'Pagnes Ama.' }])
  const { defauts } = defautsDuPlan(invente, { sources: [DEMANDE] })
  assert.ok(defauts.some((d) => /9000/.test(d)))
  assert.ok(defauts.some((d) => /500/.test(d) && /voix/.test(d)))
  assert.ok(defauts.some((d) => /500/.test(d) && /accroche/.test(d)))
  // Les promesses toutes faites, non dites par le client, sont refusées.
  const promesse = defautsDuPlan(brut([{ type: 'accroche', voix: 'Du wax de qualité premium !', texte: 'Prix imbattables' }, { type: 'carte', voix: 'Pagnes Ama.' }]), { sources: [DEMANDE] })
  assert.ok(promesse.defauts.some((d) => /premium/.test(d)))
  assert.ok(promesse.defauts.some((d) => /imbattable/.test(d)))
  assert.deepEqual(defautsDuPlan(brut([{ type: 'accroche', voix: 'Du wax premium !', texte: 'Wax' }, { type: 'carte', voix: 'Pagnes Ama.' }]), { sources: ['Pagnes Ama, du wax premium'] }).defauts.filter((d) => /premium/.test(d)), [])
  // Les chiffres du client passent (« 12 000 » écrit avec une espace compte comme 12000).
  assert.deepEqual(chiffresAConfirmer({ plans: [{ type: 'texte', texte: 'Dès 12 000 FCFA' }] }, [DEMANDE]), [])
})

test('variété, longueur visée et voix du client recopiée mot pour mot', () => {
  const textes = brut([{ type: 'accroche', voix: 'Un', texte: 'a' }, ...[1, 2, 3, 4].map((i) => ({ type: 'texte', voix: `Phrase numéro ${'deux trois quatre cinq six'.split(' ')[i]}.`, texte: 'b' })), { type: 'carte', voix: 'Fin.' }])
  const { defauts } = defautsDuPlan(textes, { sources: [] })
  assert.ok(defauts.some((d) => /trois plans « texte » de suite/.test(d)))
  assert.ok(defauts.some((d) => /plans « texte » sur/.test(d)))
  assert.ok(defautsDuPlan(BON, { sources: [DEMANDE], dureeVoulue: 60 }).defauts.some((d) => /trop court pour 60 secondes/.test(d)))
  const client = defautsDuPlan(BON, { sources: [DEMANDE], transcription: 'Tu cherches du wax ? Autre chose.' })
  assert.ok(client.defauts.some((d) => /mot pour mot/.test(d)))
})

test('le texte connu aligné sur une transcription imparfaite garde son orthographe et prend les temps entendus', () => {
  const entendus = [['Tu', 0], ['cherches', 0.2], ['chez', 0.6], ['Panyama.', 0.8], ['Des', 1.5], ['panneaux', 1.7], ['wax', 2.1]].map(([mot, t]) => ({ mot, debut: t, fin: t + 0.18 }))
  const alignes = alignerSurLeTexte('Tu cherches chez Pagnes Ama. Des pagnes wax', entendus)
  assert.deepEqual(alignes.map((m) => m.mot), ['Tu', 'cherches', 'chez', 'Pagnes', 'Ama.', 'Des', 'pagnes', 'wax'])
  assert.equal(alignes.find((m) => m.mot === 'pagnes').debut, 1.7)
  assert.equal(alignes.find((m) => m.mot === 'Des').debut, 1.5)
  for (let i = 1; i < alignes.length; i++) assert.ok(alignes[i].debut >= alignes[i - 1].debut)
})

test('un plan trop court pour être lu est fondu avec son voisin', () => {
  const cale = { plans: [{ type: 'accroche', debut: 0, duree: 2, temps: {} }, { type: 'texte', texte: 'a', debut: 2, duree: 1.2, temps: {} }, { type: 'texte', texte: 'court', debut: 3.2, duree: 0.3, temps: {} }, { type: 'avant_apres', debut: 3.5, duree: 0.4, temps: { element0: 0.1 } }, { type: 'texte', texte: 'b', debut: 3.9, duree: 1, temps: {} }, { type: 'carte', debut: 4.9, duree: 3, temps: {} }] }
  const r = retirerPlansTropCourts(cale)
  assert.deepEqual(r.plans.map((p) => p.type), ['accroche', 'avant_apres', 'texte', 'carte'])
  const aa = r.plans[1]
  assert.equal(aa.debut, 2)
  assert.equal(aa.duree, 1.9)
  assert.equal(aa.temps.element0, 1.6)
})

test('les icônes proposées existent et leurs tracés sont lisibles par Revideo', () => {
  assert.equal(cheminLisible('M15.914 4a1.5 1.5 0 00-2.474-1.561l-9 9A.5.5 0 01.471.666'), 'M 15.914 4 a 1.5 1.5 0 0 0 -2.474 -1.561 l -9 9 A .5 .5 0 0 1 .471 .666')
  for (const nom of ICONES_PROPOSEES) {
    const formes = formesIcone(nom)
    assert.ok(formes?.length, nom)
    for (const f of formes.filter((x) => x.forme === 'chemin')) assert.doesNotMatch(f.d, /\d[01]{2}[-\d.]/, `${nom} : ${f.d}`)
  }
  assert.match(consignesSysteme(30), /Environ 75 mots/)
})

test('le modèle écrit le plan, le code trouve les défauts et le fait corriger', async () => {
  const appels = []
  let n = 0
  const reponses = [
    brut([{ type: 'accroche', voix: 'Plus de 900 clients !', texte: 'Clients' }, { type: 'carte', voix: 'Pagnes Ama.' }]),
    BON,
  ]
  const appeler = async (url, init) => {
    appels.push(JSON.parse(init.body))
    const objet = reponses[Math.min(n++, reponses.length - 1)]
    const corps = { candidates: [{ content: { parts: [{ text: JSON.stringify(objet) }] } }], usageMetadata: { promptTokenCount: 1000, candidatesTokenCount: 500 } }
    return { ok: true, status: 200, headers: new Headers(), text: async () => JSON.stringify(corps) }
  }
  const r = await ecrireMontage({ demande: DEMANDE, dureeVoulue: 14, fournisseurs: [{ fournisseur: 'gemini', modele: 'gemini-3.5-flash-lite' }] }, { clesPresentes: new Set(['gemini']), env: { GEMINI_API_KEY: 'fausse' }, appeler })
  assert.equal(r.essais, 2)
  assert.ok(r.plan)
  assert.equal(r.consommations.length, 2)
  assert.match(JSON.stringify(appels[1]), /900/)
})
