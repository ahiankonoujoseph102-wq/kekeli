// Kekeli Motion : l'image fixe d'une animation (un fichier SVG), dessinée sur le Runner par le même moteur que dans
// le navigateur, grâce à un tout petit « document » imité (assez pour créer des éléments SVG et les écrire en texte).
// Elle sert d'aperçu dans la bibliothèque et se télécharge comme image.

import { monter } from './construit/kekeli-motion.mjs'

const NS = 'http://www.w3.org/2000/svg'
const echapper = (t) => String(t).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c])

class FauxElement {
  constructor(tag) {
    this.tagName = tag
    this.attributs = new Map()
    this.enfants = []
    this.parentNode = null
    this.texte = null
    const proprietes = new Map()
    this.style = new Proxy({}, {
      get: (_c, cle) => (cle === 'setProperty' ? (n, v) => proprietes.set(n, String(v)) : cle === 'proprietes' ? proprietes : proprietes.get(cle) ?? ''),
      set: (_c, cle, v) => { proprietes.set(String(cle), String(v)); return true },
    })
    const classes = new Set()
    this.classList = {
      add: (...n) => { n.forEach((x) => classes.add(x)); this.attributs.set('class', [...classes].join(' ')) },
      remove: (...n) => { n.forEach((x) => classes.delete(x)); if (classes.size) this.attributs.set('class', [...classes].join(' ')); else this.attributs.delete('class') },
    }
    this.classes = classes
  }

  setAttribute(n, v) {
    if (n === 'class') { this.classes.clear(); String(v).split(/\s+/).filter(Boolean).forEach((c) => this.classes.add(c)) }
    this.attributs.set(n, String(v))
  }

  getAttribute(n) { return this.attributs.has(n) ? this.attributs.get(n) : null }
  hasAttribute(n) { return this.attributs.has(n) }
  addEventListener() {}
  appendChild(e) { this.retirer(e); this.enfants.push(e); e.parentNode = this; return e }
  insertBefore(e, ref) {
    this.retirer(e)
    const i = ref ? this.enfants.indexOf(ref) : -1
    if (i < 0) this.enfants.push(e)
    else this.enfants.splice(i, 0, e)
    e.parentNode = this
    return e
  }

  retirer(e) {
    if (e.parentNode) { const l = e.parentNode.enfants; const i = l.indexOf(e); if (i >= 0) l.splice(i, 1) }
  }

  get nextSibling() {
    if (!this.parentNode) return null
    const l = this.parentNode.enfants
    return l[l.indexOf(this) + 1] ?? null
  }

  querySelector(tag) {
    for (const e of this.enfants) {
      if (e.tagName === tag) return e
      const trouve = e.querySelector(tag)
      if (trouve) return trouve
    }
    return null
  }

  set textContent(t) { this.texte = String(t); this.enfants = [] }
  get textContent() { return this.texte ?? this.enfants.map((e) => e.textContent).join('') }

  enTexte() {
    const attributs = new Map(this.attributs)
    const styles = [...this.style.proprietes].map(([n, v]) => `${n.replace(/[A-Z]/g, (c) => `-${c.toLowerCase()}`)}:${v}`)
    if (styles.length) attributs.set('style', [attributs.get('style'), ...styles].filter(Boolean).join(';'))
    const a = [...attributs].map(([n, v]) => ` ${n}="${echapper(v)}"`).join('')
    const dedans = this.texte !== null ? echapper(this.texte) : this.enfants.map((e) => e.enTexte()).join('')
    return `<${this.tagName}${a}>${dedans}</${this.tagName}>`
  }
}

/** Le SVG d'une scène (déjà vérifiée) à un moment donné, en secondes. */
export async function imageFixe(scene, { temps = 2.5 } = {}) {
  const avant = globalThis.document
  const racine = new FauxElement('div')
  globalThis.document = { createElementNS: (_ns, tag) => new FauxElement(tag) }
  try {
    const { svg } = monter(racine, scene, { statique: true, temps, id: 'kekeli-image' })
    svg.setAttribute('xmlns', NS)
    svg.attributs.delete('style')
    svg.style.proprietes.set('background', '#fdfdfc')
    return `<?xml version="1.0" encoding="UTF-8"?>\n${svg.enTexte()}\n`
  } finally {
    if (avant === undefined) delete globalThis.document
    else globalThis.document = avant
  }
}
