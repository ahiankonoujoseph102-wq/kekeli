// Analyse de la mesure de la course entre fournisseurs (chantier « premier mot », 15 novembre 2026) : pour chaque
// question, avant (course éteinte) et après (course à 1 seconde), la médiane et le pire cas du premier mot vu par le
// téléphone ; après, la part des messages où la course est partie, le gagnant ; et le coût réel enregistré de chaque
// message (modèle, demande annulée comprise, recherche à part), lu dans la base locale.
//   node scripts/analyser-course.js <dossier> [<dossier>…]
import { readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { join } from 'node:path';

const dossiers = process.argv.slice(2);
const sqlite = process.env.BASE_SQLITE || '/home/user/kekeli-reponses.sqlite';
const lignes = [];
for (const dossier of dossiers) {
    for (const fichier of readdirSync(dossier).filter((f) => /^(avant|apres)-\d+\.json$/.test(f))) {
        const mode = fichier.startsWith('avant') ? 'avant' : 'apres';
        for (const r of JSON.parse(readFileSync(join(dossier, fichier), 'utf8')).resultats) {
            const q = (r.serveur ?? []).find((x) => x.but === 'question');
            if (q) lignes.push({ moment: dossier, mode, scenario: r.scenario, premier_mot: r.premier_mot, id: q.id });
        }
    }
}
const ids = lignes.map((l) => l.id);
const donnees = JSON.parse(execFileSync('php', ['-r', `$db = new PDO("sqlite:" . $argv[1]); $ids = json_decode($argv[2]); $q = $db->prepare("select id, model_cost_usd, web_search_cost_usd, provider, timings from agent_runs where id = ?"); $r = []; foreach ($ids as $id) { $q->execute([$id]); $r[] = $q->fetch(PDO::FETCH_ASSOC); } echo json_encode($r);`, sqlite, JSON.stringify(ids)]).toString());
const parId = Object.fromEntries(donnees.filter(Boolean).map((d) => [d.id, d]));
const mediane = (v) => { const t = [...v].sort((a, b) => a - b); return t.length ? t[Math.floor((t.length - 1) / 2)] : null; };
const resume = {};
for (const scenario of ['salut', 'question', 'longue']) {
    for (const mode of ['avant', 'apres']) {
        const choix = lignes.filter((l) => l.scenario === scenario && l.mode === mode && l.premier_mot != null);
        const temps = choix.map((l) => l.premier_mot);
        const runs = choix.map((l) => parId[l.id]).filter(Boolean);
        const courses = runs.map((d) => JSON.parse(d.timings || '{}').course).filter(Boolean);
        const lancees = courses.filter((c) => c.lancee);
        const gagnants = {};
        for (const c of lancees) gagnants[c.gagnant] = (gagnants[c.gagnant] ?? 0) + 1;
        const cout = runs.reduce((s, d) => s + Number(d.model_cost_usd || 0), 0) / Math.max(1, runs.length);
        resume[`${scenario}-${mode}`] = { essais: choix.length, mediane_s: mediane(temps), pire_s: temps.length ? Math.max(...temps) : null, course_lancee: `${lancees.length}/${courses.length || runs.length}`, gagnants, cout_modele_moyen_usd: Number(cout.toFixed(6)) };
    }
}
console.log(JSON.stringify(resume, null, 1));
writeFileSync(join(dossiers[0], 'analyse.json'), JSON.stringify({ dossiers, resume, lignes }, null, 2) + '\n');
