// Analyse d'une série de mesures du premier mot (chantier « premier mot », 15 novembre 2026) : pour chaque question,
// médiane et pire cas du premier mot vu par le téléphone, fournisseur qui a répondu, et coût réel enregistré de chaque
// message (modèle et recherche), lu dans la base locale.
//   node scripts/analyser-premier-mot.js <fichier de scripts/mesurer-vitesse.js> [<autre fichier>…]
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';

const sqlite = process.env.BASE_SQLITE || '/home/user/kekeli-reponses.sqlite';
const lignes = [];
for (const fichier of process.argv.slice(2)) {
    for (const r of JSON.parse(readFileSync(fichier, 'utf8')).resultats) {
        const q = (r.serveur ?? []).find((x) => x.but === 'question');
        if (q && r.premier_mot != null) lignes.push({ fichier, scenario: r.scenario, premier_mot: r.premier_mot, id: q.id });
    }
}
const donnees = JSON.parse(execFileSync('php', ['-r', '$db = new PDO("sqlite:" . $argv[1]); $q = $db->prepare("select id, provider, model, model_cost_usd, web_search_cost_usd from agent_runs where id = ?"); $r = []; foreach (json_decode($argv[2]) as $id) { $q->execute([$id]); $r[] = $q->fetch(PDO::FETCH_ASSOC); } echo json_encode($r);', sqlite, JSON.stringify(lignes.map((l) => l.id))]).toString());
const parId = Object.fromEntries(donnees.filter(Boolean).map((d) => [d.id, d]));
const mediane = (v) => { const t = [...v].sort((a, b) => a - b); return t.length ? t[Math.floor((t.length - 1) / 2)] : null; };
const resume = {};
for (const scenario of [...new Set(lignes.map((l) => l.scenario))]) {
    const choix = lignes.filter((l) => l.scenario === scenario);
    const runs = choix.map((l) => parId[l.id]).filter(Boolean);
    const fournisseurs = {};
    for (const d of runs) fournisseurs[`${d.provider}/${d.model}`] = (fournisseurs[`${d.provider}/${d.model}`] ?? 0) + 1;
    const moyenne = (cle) => runs.reduce((s, d) => s + Number(d[cle] || 0), 0) / Math.max(1, runs.length);
    resume[scenario] = { essais: choix.length, mediane_s: mediane(choix.map((l) => l.premier_mot)), pire_s: Math.max(...choix.map((l) => l.premier_mot)), fournisseurs, cout_modele_moyen_usd: Number(moyenne('model_cost_usd').toFixed(6)), cout_recherche_moyen_usd: Number(moyenne('web_search_cost_usd').toFixed(6)) };
}
console.log(JSON.stringify(resume, null, 1));
