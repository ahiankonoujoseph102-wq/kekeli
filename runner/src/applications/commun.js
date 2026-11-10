// Outils communs aux deux fabriques d'applications web (Laravel et Next.js).

/** « la réservation », « le séjour », « l'inscription ». */
export function le(entite) {
  return /^[aeiouyéèêàâîôûh]/i.test(entite.singulier) ? `l'${entite.singulier}` : `${entite.feminin ? 'la' : 'le'} ${entite.singulier}`
}

/** « une réservation », « un séjour ». */
export function un(entite) {
  return `${entite.feminin ? 'une' : 'un'} ${entite.singulier}`
}

/** Accord au féminin : « enregistrée » ou « enregistré ». */
export function accord(entite, mot) {
  return entite.feminin ? `${mot}e` : mot
}

/** « aucune réservation », « aucun séjour ». */
export function aucun(entite) {
  return `${entite.feminin ? 'aucune' : 'aucun'} ${entite.singulier}`
}

/** « toutes les réservations », « tous les séjours ». */
export function tous(entite) {
  return `${entite.feminin ? 'toutes' : 'tous'} les ${entite.pluriel}`
}

/** « Mes réservations ». */
export function mes(entite) {
  return `Mes ${entite.pluriel}`
}

/** Majuscule au début d'un texte. */
export function majuscule(texte) {
  return texte.charAt(0).toUpperCase() + texte.slice(1)
}

/** Nom d'une constante d'options : « chambre » → « CHAMBRES ». */
export function constanteOptions(champ) {
  return `${champ.nom.toUpperCase()}_OPTIONS`
}

/** Valeur d'exemple d'un champ sous forme de texte. */
export function exemple(champ) {
  if (champ.exemple !== undefined) return String(champ.exemple)
  if (champ.type === 'select') return champ.options[0]
  if (champ.type === 'integer') return String(champ.min ?? 1)
  return 'Exemple'
}

/** Vérifie une description d'application (forme attendue par les deux fabriques). */
export function verifierModele(modele) {
  const e = modele?.entite
  const erreurs = []
  if (!/^[a-z0-9-]{2,60}$/.test(modele?.slug ?? '')) erreurs.push('slug invalide')
  if (!e || !/^[A-Z][A-Za-z]{2,40}$/.test(e.classe ?? '')) erreurs.push('classe invalide')
  // Noms déjà pris par le code commun des applications.
  if (['User', 'Session', 'CreationCompte', 'Connexion', 'MotDePasse', 'Espace', 'Installation', 'TableauDeBord', 'Utilisateur', 'Admin', 'Controller'].includes(e?.classe)) erreurs.push(`classe ${e.classe} réservée`)
  if (['users', 'sessions', 'password_reset_tokens', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'migrations'].includes(e?.table)) erreurs.push(`table ${e.table} réservée`)
  if (['admin', 'nouveau'].includes(e?.chemin)) erreurs.push(`chemin ${e.chemin} réservé`)
  if (e && !/^[a-z_]{2,40}$/.test(e.table ?? '')) erreurs.push('table invalide')
  if (e && !/^[a-z-]{2,40}$/.test(e.chemin ?? '')) erreurs.push('chemin invalide')
  const types = ['string', 'text', 'tel', 'email', 'date', 'time', 'integer', 'select']
  for (const champ of e?.champs ?? []) {
    if (!/^[a-z][a-z_]{1,40}$/.test(champ.nom ?? '')) erreurs.push(`champ ${champ.nom} : nom invalide`)
    if (!types.includes(champ.type)) erreurs.push(`champ ${champ.nom} : type ${champ.type} inconnu`)
    if (['user_id', 'statut', 'id', 'created_at', 'updated_at'].includes(champ.nom)) erreurs.push(`champ ${champ.nom} : nom réservé`)
    if (champ.type === 'select' && !(champ.options?.length > 0)) erreurs.push(`champ ${champ.nom} : options manquantes`)
  }
  if (!(e?.champs?.length > 0)) erreurs.push('aucun champ')
  if (!(e?.statuts?.length >= 2)) erreurs.push('au moins deux statuts')
  if (erreurs.length) throw new Error(`Modèle d'application « ${modele?.slug} » : ${erreurs.join(', ')}.`)
  return modele
}

/** Feuille de style commune aux deux piles (mobile d'abord, sans aucune dépendance). */
export const FEUILLE_DE_STYLE = `/* Style de l'application : mobile d'abord, sans dépendance. Couleurs à changer ici. */
:root {
  --couleur: #b45309;
  --couleur-foncee: #92400e;
  --fond: #fffbf5;
  --carte: #ffffff;
  --texte: #1f2937;
  --discret: #6b7280;
  --bordure: #e5e7eb;
  --succes: #047857;
  --erreur: #b91c1c;
  --rayon: 14px;
}
* { box-sizing: border-box; }
html { -webkit-text-size-adjust: 100%; }
body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: var(--fond); color: var(--texte); line-height: 1.55; font-size: 16px; }
a { color: var(--couleur-foncee); }
img, svg { max-width: 100%; }
.conteneur { width: 100%; max-width: 960px; margin: 0 auto; padding: 0 16px; }
.entete { background: var(--carte); border-bottom: 1px solid var(--bordure); }
.entete .conteneur { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; padding-top: 12px; padding-bottom: 12px; }
.marque { font-weight: 700; font-size: 1.1rem; text-decoration: none; color: var(--texte); }
.navigation { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 14px; font-size: .95rem; }
.navigation a, .lien-bouton { color: var(--texte); text-decoration: none; background: none; border: 0; padding: 0; font: inherit; cursor: pointer; }
.navigation a:hover, .lien-bouton:hover { color: var(--couleur-foncee); text-decoration: underline; }
main { padding: 24px 0 48px; }
h1 { font-size: 1.6rem; line-height: 1.25; margin: 0 0 12px; }
h2 { font-size: 1.2rem; margin: 24px 0 8px; }
.accroche { font-size: 1.1rem; color: var(--discret); max-width: 36rem; }
.heros { padding: 32px 0 16px; }
.carte { background: var(--carte); border: 1px solid var(--bordure); border-radius: var(--rayon); padding: 16px; margin: 12px 0; }
.grille { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
.bouton { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 44px; padding: 10px 18px; border-radius: 999px; border: 0; background: var(--couleur); color: #fff; font: inherit; font-weight: 600; text-decoration: none; cursor: pointer; }
.bouton:hover { background: var(--couleur-foncee); }
.bouton.secondaire { background: transparent; color: var(--couleur-foncee); border: 1px solid var(--couleur); }
.actions { display: flex; flex-wrap: wrap; gap: 10px; margin: 16px 0; }
form .champ { margin: 0 0 14px; }
label { display: block; font-weight: 600; margin-bottom: 4px; }
input, select, textarea { width: 100%; min-height: 44px; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 10px; font: inherit; background: #fff; color: var(--texte); }
textarea { min-height: 110px; }
input:focus, select:focus, textarea:focus { outline: 2px solid var(--couleur); outline-offset: 1px; }
.case { display: flex; align-items: center; gap: 8px; font-weight: 400; }
.case input { width: auto; min-height: 0; }
.aide { font-size: .9rem; color: var(--discret); }
.erreur-champ { color: var(--erreur); font-size: .9rem; margin-top: 4px; }
.message { padding: 12px 14px; border-radius: 10px; margin: 0 0 16px; }
.message.succes { background: #ecfdf5; color: var(--succes); border: 1px solid #a7f3d0; }
.message.erreur { background: #fef2f2; color: var(--erreur); border: 1px solid #fecaca; }
.liste { list-style: none; margin: 0; padding: 0; }
.liste li { border-bottom: 1px solid var(--bordure); padding: 12px 0; display: flex; flex-wrap: wrap; justify-content: space-between; gap: 6px 12px; }
.statut { display: inline-block; white-space: nowrap; padding: 2px 10px; border-radius: 999px; font-size: .85rem; background: #fef3c7; color: #92400e; }
.statut.s1 { background: #dbeafe; color: #1e40af; }
.statut.s2 { background: #d1fae5; color: #065f46; }
.statut.s3 { background: #fee2e2; color: #991b1b; }
.details { display: grid; grid-template-columns: minmax(120px, 40%) 1fr; gap: 6px 12px; margin: 0; }
.details dt { color: var(--discret); }
.details dd { margin: 0; word-break: break-word; }
.tableau { width: 100%; border-collapse: collapse; font-size: .95rem; }
.tableau th, .tableau td { text-align: left; padding: 8px 6px; border-bottom: 1px solid var(--bordure); vertical-align: top; }
.defilement { overflow-x: auto; }
.chiffres { display: grid; gap: 10px; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); margin: 12px 0; }
.chiffres .carte { margin: 0; padding: 12px 14px; }
.chiffres p { margin: 0; }
.chiffre { font-size: 1.6rem; font-weight: 700; line-height: 1.2; }
.filtres { display: flex; flex-wrap: wrap; gap: 8px; margin: 8px 0 12px; }
.filtres a { padding: 4px 12px; border: 1px solid var(--bordure); border-radius: 999px; text-decoration: none; color: var(--texte); background: var(--carte); font-size: .9rem; }
.filtres a.actif { background: var(--couleur); color: #fff; border-color: var(--couleur); }
.pied { border-top: 1px solid var(--bordure); padding: 16px 0 32px; color: var(--discret); font-size: .9rem; }
.icone { width: 20px; height: 20px; flex: none; }
@media (min-width: 720px) { h1 { font-size: 2rem; } .heros { padding: 56px 0 24px; } }
`

/** Icônes Lucide (licence ISC) utilisées par les applications : tracés SVG seulement. */
export const ICONES = {
  plus: '<path d="M5 12h14"/><path d="M12 5v14"/>',
  utilisateur: '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
  bouclier: '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>',
  liste: '<path d="M3 12h.01"/><path d="M3 18h.01"/><path d="M3 6h.01"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M8 6h13"/>',
}
