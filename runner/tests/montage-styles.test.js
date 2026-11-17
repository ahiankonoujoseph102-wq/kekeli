// Étape 5 du chantier des vidéos motion design : les cinq styles interchangeables, les deux nouveaux types de plans
// (citation et produit), le ton et les bruitages selon le style, et les trois questions avant une vidéo.
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { planPropre, STYLES, STYLES_VOUVOIEMENT } from '../src/videos/montage/schema.js'
import { chiffresAConfirmer, citationsInventees, consignesSysteme, consigneMontage, CONSIGNES_STYLES, defautsDuPlan, tonDuStyle } from '../src/videos/montage/plan-modele.js'
import { bruitagesDuPlan } from '../src/videos/montage/son.js'
import { calerSurLaVoix } from '../src/videos/montage/temps.js'
import { questionsPropres } from '../src/repondant.js'
import { REGLES_DECISION } from '../src/competences.js'

const STYLE_TS = readFileSync(new URL('../src/videos/socle/src/montage/style.ts', import.meta.url), 'utf8')
const ELEMENTS_TS = readFileSync(new URL('../src/videos/socle/src/montage/elements.ts', import.meta.url), 'utf8')

test('les cinq styles existent des deux côtés (Runner et bibliothèque Revideo), avec toutes leurs couleurs', () => {
  assert.deepEqual(STYLES, ['dynamique', 'sobre', 'produit', 'temoignage', 'educatif'])
  const couleurs = [...STYLE_TS.match(/export type Couleurs = \{([\s\S]*?)\n\};/)[1].matchAll(/^\s+(\w+): string;/gm)].map((m) => m[1])
  assert.ok(couleurs.length >= 15)
  for (const style of STYLES) {
    const bloc = STYLE_TS.slice(STYLE_TS.indexOf(`  ${style}: {`)).split('\n  },')[0]
    assert.ok(bloc.includes(`nom: '${style}'`), style)
    for (const c of couleurs) assert.match(bloc, new RegExp(`\\b${c}: '`), `${style} : ${c}`)
    assert.match(bloc, /decor: '(nuit|papier|vitrine|veillee|tableau)'/, style)
  }
  // Plus aucune couleur blanche translucide écrite en dur : elle disparaîtrait sur un fond clair.
  assert.doesNotMatch(ELEMENTS_TS, /rgba\(255,255,255,0\.(1|08|7|88)\)/)
})

test('le ton suit le style : « Sobre » et « Produit » vouvoient, les autres tutoient, le texte du client décide', () => {
  assert.deepEqual(STYLES_VOUVOIEMENT, ['sobre', 'produit'])
  assert.match(tonDuStyle('sobre'), /^Vouvoiement par défaut/)
  assert.match(tonDuStyle('dynamique'), /^Tutoiement par défaut/)
  assert.match(consignesSysteme(30, 'produit'), /Vouvoiement par défaut/)
  assert.doesNotMatch(consignesSysteme(30, 'produit'), /\{TON\}/)
  for (const style of STYLES) assert.ok(consigneMontage({ demande: 'Une vidéo', style }).includes(CONSIGNES_STYLES[style]), style)
})

test('une citation et un produit sont vérifiés : avis et nom du client, prix donnés par lui, jamais inventés', () => {
  const plan = (plans) => ({ marque: { nom: 'Belle Afi' }, plans: [{ type: 'accroche', mot: 'Tes', texte: 'Des tresses' }, ...plans, { type: 'carte', mot: 'Belle' }] })
  const demande = 'Vidéo témoignage pour Belle Afi, ma cliente Akossiwa a dit : « Mes tresses tiennent un mois, je ne vais plus ailleurs ». Tresses 5000 FCFA.'
  const vrai = planPropre(plan([{ type: 'citation', mot: 'Mes', texte: 'Mes tresses tiennent un mois, je ne vais plus ailleurs', auteur: 'Akossiwa', etoiles: 9 }])).plan
  assert.equal(vrai.plans[1].etoiles, 5)
  assert.deepEqual(citationsInventees(vrai, [demande]), [])
  // Les étoiles sont un chiffre : seulement si le client l'a dit.
  assert.deepEqual(chiffresAConfirmer(vrai, [demande]).map((c) => c.chiffre), ['5'])
  const faux = planPropre(plan([{ type: 'citation', mot: 'Super', texte: 'Le meilleur salon de Lomé, une équipe adorable', auteur: 'Mawuena' }])).plan
  assert.equal(citationsInventees(faux, [demande]).length, 2)
  // Un produit sans prix est refusé ; un prix inventé est signalé.
  assert.ok(planPropre(plan([{ type: 'produit', mot: 'Tresses', texte: 'Tresses' }])).erreurs.some((e) => e.includes('nom et de son prix')))
  const produit = planPropre(plan([{ type: 'produit', mot: 'Tresses', texte: 'Tresses', prix: '5000 FCFA', ancien_prix: '7000 FCFA', mot_prix: '5000' }])).plan
  assert.deepEqual(chiffresAConfirmer(produit, [demande]).map((c) => c.chiffre), ['7000'])
})

test('un avis inventé par le modèle est refusé avec la raison', () => {
  const brut = {
    marque: { nom: 'Belle Afi' },
    plans: [
      { type: 'accroche', voix: 'Tes tresses tiennent mal ?', texte: 'Des tresses qui tiennent' },
      { type: 'citation', voix: 'Belle Afi est le salon le plus doux de tout Lomé.', texte: 'Le salon le plus doux de tout Lomé', auteur: 'Mawuena' },
      { type: 'carte', voix: 'Belle Afi, à Bè.' },
    ],
  }
  const { defauts } = defautsDuPlan(brut, { sources: ['Vidéo pour mon salon Belle Afi à Bè'] })
  assert.ok(defauts.some((d) => d.includes("n'est pas un avis donné par le client")), defauts.join(' / '))
})

test('le prix d’un produit tombe sur son mot ; sinon le plan se cale quand même', () => {
  const { plan } = planPropre({ marque: { nom: 'Ama' }, plans: [{ type: 'accroche', mot: 'Du', texte: 'Du wax' }, { type: 'produit', mot: 'Le pagne', texte: 'Pagne', prix: '12 000 FCFA', mot_prix: '12 000' }, { type: 'carte', mot: 'Ama' }] })
  const mots = 'Du beau wax. Le pagne à 12 000 francs. Ama.'.split(' ').map((mot, i) => ({ mot, debut: i * 0.4, fin: i * 0.4 + 0.3 }))
  const c = calerSurLaVoix(plan, mots)
  // Le plan commence 0,1 seconde avant « Le » (1,2 s), le prix tombe sur « 12 » (2,4 s).
  assert.equal(c.plan.plans[1].temps.prix, 1.3)
  plan.plans[1].mot_prix = 'introuvable'
  assert.ok(calerSurLaVoix(plan, mots).plan, 'un prix qui n’est pas dit ne bloque jamais la vidéo')
})

test('les bruitages suivent le style : chocs pour « Dynamique », souffles discrets pour « Sobre » et « Témoignage »', () => {
  const cale = (style) => ({ style, plans: [{ type: 'accroche', debut: 0, temps: {} }, { type: 'tampon', debut: 2, temps: {} }, { type: 'produit', debut: 4, temps: { prix: 0.6 } }] })
  const dynamique = bruitagesDuPlan(cale('dynamique'))
  assert.deepEqual(dynamique.map((b) => b.nom), ['choc', 'souffle', 'pop'])
  const sobre = bruitagesDuPlan(cale('sobre'))
  assert.deepEqual(sobre.map((b) => b.nom), ['souffle', 'souffle', 'pop'])
  assert.ok(sobre[0].volume < dynamique[1].volume)
})

test('avant une vidéo : les trois questions (style, format, voix) avec leurs choix exacts, cinq choix pour le style', () => {
  const texte = REGLES_DECISION.map((c) => c.texte).join('\n')
  for (const mot of ['« Dynamique », « Sobre et professionnel », « Produit et boutique », « Témoignage », « Éducatif »', '« Vertical (TikTok, statut WhatsApp) », « Carré (Facebook, Instagram) », « Horizontal (YouTube) »', '« Voix de femme », « Voix d’homme », « Ma propre voix »', 'Une vidéo ne démarre JAMAIS sans ces questions', 'jamais un avis inventé']) assert.ok(texte.includes(mot), mot)
  const style = { question: 'Quel style pour votre vidéo ?', choix: ['Dynamique', 'Sobre et professionnel', 'Produit et boutique', 'Témoignage', 'Éducatif'] }
  assert.equal(questionsPropres([style], 'creer_video').questions[0].choix.length, 5)
  assert.equal(questionsPropres([style], 'creer_site').questions[0].choix.length, 4)
})
