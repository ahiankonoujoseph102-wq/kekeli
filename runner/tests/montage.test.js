import { test } from 'node:test'
import assert from 'node:assert/strict'
import { planPropre, TYPES_PLANS } from '../src/videos/montage/schema.js'
import { memeMot, calerSurLaVoix, controlerRythme, motsCherchables, normaliser, trouverPhrase } from '../src/videos/montage/temps.js'
import { codeMontage } from '../src/videos/montage/code.js'
import { formesIcone, iconesDuPlan } from '../src/videos/montage/icones.js'

const PLAN = {
  style: 'dynamique',
  marque: { nom: 'Clean Express', slogan: 'Votre linge propre en 24 heures', bouton: 'Écrivez-nous sur WhatsApp', contact: '+228 91 11 11 11' },
  plans: [
    { type: 'accroche', mot: 'Tes', texte: 'Tes chemises sont sales ?', cles: ['sales'] },
    { type: 'liste', mot: 'lavage', titre: 'Nos services', elements: [{ texte: 'Lavage', mot: 'lavage', icone: 'shirt' }, { texte: 'Repassage', mot: 'repassage' }] },
    { type: 'numero', mot: 'Écris-nous', texte: 'Écris-nous', groupes: [{ texte: '91', mot: '91' }, { texte: '11', mot: '11' }] },
    { type: 'carte', mot: 'Clean' },
    { type: 'inconnu', mot: 'x' },
  ],
}

// Des mots de voix inventés, avec leurs temps (comme Whisper les rend).
const MOTS = [
  ['Tes', 0.1, 0.3], ['chemises', 0.3, 0.8], ['sont', 0.8, 1.0], ['sales', 1.0, 1.4], ['?', 1.4, 1.5],
  ['lavage,', 1.7, 2.2], ['repassage.', 2.3, 3.0], ['Écris-nous', 3.2, 3.8], ['au', 3.8, 3.9], ['91-11', 4.0, 4.8], ['Clean', 5.0, 5.4], ['Express.', 5.4, 6.0],
].map(([mot, debut, fin]) => ({ mot, debut, fin }))

test('le plan de montage nettoyé : types connus seulement, accroche en premier, carte en dernier', () => {
  const r = planPropre(PLAN)
  assert.equal(r.plan, null)
  assert.match(r.erreurs.join(' '), /type inconnu « inconnu »/)
  assert.equal(planPropre({ ...PLAN, plans: PLAN.plans.slice(0, 2) }).erreurs.at(-1), 'le dernier plan doit être la carte finale')
  const bon = planPropre({ ...PLAN, plans: PLAN.plans.slice(0, 4) })
  assert.deepEqual(bon.erreurs, [])
  assert.equal(bon.plan.plans[0].cles[0], 'sales')
  assert.equal(bon.plan.plans[1].elements.length, 2)
  // Un mot-clé qui n'est pas dans les textes du plan n'est pas gardé.
  assert.equal(planPropre({ ...PLAN, plans: [{ ...PLAN.plans[0], cles: ['inventé'] }, PLAN.plans[3]] }).plan.plans[0].cles, undefined)
  assert.ok(Object.keys(TYPES_PLANS).length >= 16)
})

test('les mots se cherchent sans accents ni ponctuation, et un numéro collé est coupé', () => {
  assert.equal(normaliser('Écris-nous,'), 'ecrisnous')
  const mots = motsCherchables(MOTS)
  assert.ok(mots.some((m) => m.mot === '91') && mots.some((m) => m.mot === '11'))
  assert.equal(trouverPhrase(mots, 'chemises sont'), 1)
  assert.equal(trouverPhrase(mots, 'introuvable'), -1)
  // « 02 » et « 2 » sont le même nombre.
  assert.equal(trouverPhrase(motsCherchables([{ mot: '2', debut: 0, fin: 1 }]), '02'), 0)
})

test('chaque plan commence sur son mot, la carte reste après la voix, le rythme est contrôlé', () => {
  const plan = planPropre({ ...PLAN, plans: PLAN.plans.slice(0, 4) }).plan
  const r = calerSurLaVoix(plan, MOTS, { decalage: 0.2 })
  assert.deepEqual(r.erreurs, [])
  const [accroche, liste, numero, carte] = r.plan.plans
  assert.equal(accroche.debut, 0)
  assert.equal(liste.debut, 1.8)
  assert.equal(liste.temps.element1, 0.7)
  assert.equal(numero.temps.groupe1, 1.3)
  assert.equal(carte.debut, 5.1)
  assert.equal(r.plan.duree, 6.2 + 3)
  // L'accroche dure 1,8 s (permis), la liste 1,7 s : bon rythme ; un plan de 4 s est refusé.
  assert.deepEqual(controlerRythme(r.plan), [])
  assert.match(controlerRythme({ plans: [{ type: 'texte', duree: 4 }] })[0], /plus de 2.5 s/)
  // Un même nombre répété (« 11 11 ») : chaque groupe prend le mot suivant.
  const repete = calerSurLaVoix(planPropre({ ...PLAN, plans: [PLAN.plans[0], { type: 'numero', mot: 'Écris-nous', groupes: [{ texte: '11', mot: '11' }, { texte: '11', mot: '11' }] }, PLAN.plans[3]] }).plan, [...MOTS.slice(0, 9), { mot: '11', debut: 4.0, fin: 4.3 }, { mot: '11', debut: 4.4, fin: 4.7 }, ...MOTS.slice(10)])
  assert.deepEqual([repete.plan.plans[1].temps.groupe0, repete.plan.plans[1].temps.groupe1], [0.9, 1.3])
  // Un mot absent de la voix est signalé.
  assert.match(calerSurLaVoix({ ...plan, plans: [{ ...plan.plans[0], mot: 'absent' }, plan.plans[3]] }, MOTS).erreurs[0], /introuvable/)
})

test('le code de la vidéo ne contient que des données, et les icônes sont dessinées sans Internet', () => {
  const plan = planPropre({ ...PLAN, plans: PLAN.plans.slice(0, 4) }).plan
  const cale = calerSurLaVoix(plan, MOTS).plan
  const piege = { ...cale, plans: [{ ...cale.plans[0], texte: "');process.exit(1);//" }, ...cale.plans.slice(1)] }
  const code = codeMontage(piege)
  assert.match(code, /yield\* monter\(vue, \{/)
  assert.ok(code.includes(JSON.stringify("');process.exit(1);//")))
  assert.ok(formesIcone('shirt')?.length)
  assert.equal(formesIcone('logo-inexistant'), null)
  assert.ok(Object.keys(iconesDuPlan(cale)).includes('shirt') && Object.keys(iconesDuPlan(cale)).includes('message-circle'))
})

test('une lettre d’écart de la transcription est tolérée, jamais pour un nombre ni un mot court', () => {
  assert.ok(memeMot('fini', 'finis'))
  assert.ok(memeMot('agoe', 'agwe'))
  assert.ok(memeMot('satisfaits', 'satisfait'))
  assert.ok(!memeMot('11', '12'))
  assert.ok(!memeMot('la', 'le'))
  assert.ok(!memeMot('lave', 'livre'))
})
