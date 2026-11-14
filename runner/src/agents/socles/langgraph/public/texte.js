// Affichage sûr des réponses de l'agent dans la page de discussion (et donc dans la bulle) : gras, italique, listes et
// retours à la ligne. Aucun HTML n'est jamais accepté : chaque morceau de texte est posé avec textContent, et les
// signes de mise en forme qui restent seuls (« ** », « ## ») disparaissent au lieu de s'afficher.
// Ce fichier est fait par Kekeli : il ne faut pas le modifier.
;(function (racine) {
  // Gras (**texte** ou __texte__), italique (*texte*), code (`texte`, affiché comme du texte simple).
  const MOTIF = /\*\*([^*]+?)\*\*|__([^_]+?)__|`([^`]+?)`|\*([^*\s](?:[^*]*?[^*\s])?)\*/g

  /** Les morceaux d'une ligne : [{ texte, gras, italique }]. */
  function morceaux(ligne) {
    const resultat = []
    const propre = (t) => t.replace(/\*\*|__/g, '').replace(/(^|\s)\*(\s|$)/g, '$1$2')
    let debut = 0
    let trouve
    MOTIF.lastIndex = 0
    while ((trouve = MOTIF.exec(ligne))) {
      if (trouve.index > debut) resultat.push({ texte: propre(ligne.slice(debut, trouve.index)) })
      if (trouve[1] !== undefined || trouve[2] !== undefined) resultat.push({ texte: propre(trouve[1] ?? trouve[2]), gras: true })
      else if (trouve[3] !== undefined) resultat.push({ texte: trouve[3] })
      else resultat.push({ texte: propre(trouve[4]), italique: true })
      debut = MOTIF.lastIndex
    }
    if (debut < ligne.length) resultat.push({ texte: propre(ligne.slice(debut)) })
    return resultat.filter((m) => m.texte !== '')
  }

  /**
   * Les blocs d'un texte : paragraphes (lignes gardées), listes à puces et listes numérotées. Un titre Markdown
   * (« ### Commande ») devient une ligne en gras ; une ligne de séparation (« --- ») disparaît.
   */
  function analyser(texte) {
    const blocs = []
    let courant = null
    for (const brute of String(texte ?? '').replace(/\r\n?/g, '\n').split('\n')) {
      const ligne = brute.replace(/\s+$/, '')
      if (!ligne.trim() || /^\s*([-*_])\1{2,}\s*$/.test(ligne)) {
        courant = null
        continue
      }
      const puce = /^\s*[-*•]\s+(.*)$/.exec(ligne)
      const numero = /^\s*(\d{1,3})[.)]\s+(.*)$/.exec(ligne)
      if (puce || numero) {
        const type = puce ? 'puces' : 'numeros'
        if (!courant || courant.type !== type) {
          courant = { type, elements: [], depart: numero ? Number(numero[1]) : 1 }
          blocs.push(courant)
        }
        courant.elements.push(morceaux(puce ? puce[1] : numero[2]))
        continue
      }
      const titre = /^\s*#{1,6}\s+(.*)$/.exec(ligne)
      const contenu = titre ? [{ texte: titre[1].replace(/\*\*|__/g, '').trim(), gras: true }] : morceaux(ligne.trim())
      if (!contenu.length) continue
      if (courant && courant.type === 'paragraphe') courant.lignes.push(contenu)
      else {
        courant = { type: 'paragraphe', lignes: [contenu] }
        blocs.push(courant)
      }
    }
    return blocs
  }

  /** Pose les morceaux dans un élément, chacun avec textContent (jamais de HTML). */
  function poser(document, parent, liste) {
    for (const m of liste) {
      const element = document.createElement(m.gras ? 'strong' : m.italique ? 'em' : 'span')
      element.textContent = m.texte
      parent.appendChild(element)
    }
  }

  /** Remplace le contenu de l'élément par le texte mis en forme. */
  function afficher(element, texte) {
    const document = element.ownerDocument
    element.textContent = ''
    for (const bloc of analyser(texte)) {
      if (bloc.type === 'paragraphe') {
        const p = document.createElement('p')
        bloc.lignes.forEach((ligne, i) => {
          if (i) p.appendChild(document.createElement('br'))
          poser(document, p, ligne)
        })
        element.appendChild(p)
      } else {
        const liste = document.createElement(bloc.type === 'puces' ? 'ul' : 'ol')
        if (bloc.type === 'numeros' && bloc.depart !== 1) liste.setAttribute('start', String(bloc.depart))
        for (const morceauxLigne of bloc.elements) {
          const li = document.createElement('li')
          poser(document, li, morceauxLigne)
          liste.appendChild(li)
        }
        element.appendChild(liste)
      }
    }
  }

  const api = { analyser, morceaux, afficher }
  if (typeof module === 'object' && module && module.exports) module.exports = api
  else racine.KekeliTexte = api
})(typeof window !== 'undefined' ? window : globalThis)
