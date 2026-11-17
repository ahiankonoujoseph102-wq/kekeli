<?php

// Données pour les captures de l'étape 6 des vidéos motion design (17 novembre 2026) : les vraies vidéos fabriquées
// par l'essai réel de bout en bout (runner/scripts/essai-video.mjs, dossier donné par VIDEOS) rangées comme des
// versions, avec le vrai message de fin et ses boutons de suite. Aucune requête au modèle ici.
//   VIDEOS=… php artisan tinker --execute="require 'scripts/preparer-captures-montage-etape-6.php';"

use App\Agent\RunReport;
use App\Models\AgentRun;
use App\Models\DocumentVersion;
use App\Models\Message;
use App\Models\Project;
use App\Models\ProjectPhoto;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

$dossier = rtrim((string) getenv('VIDEOS'), '/');
$user = User::where('email', 'afi-montage@exemple.tg')->first() ?? new User(['email' => 'afi-montage@exemple.tg']);
$user->forceFill(['name' => 'Afi Mensah', 'password' => Hash::make('motdepasse-solide-2026'), 'email_verified_at' => now(), 'country' => 'TG', 'terms_accepted_at' => now(), 'plan' => 'free', 'appearance' => 'system'])->save();
$user->projects()->get()->each->delete();
$disk = Storage::disk(ProjectPhoto::disk());

$video = function (string $nom, int $minutes, string $demande, array $essais) use ($user, $disk, $dossier) {
    $projet = $user->projects()->make(['name' => $nom]);
    $projet->forceFill(['kind' => Project::KIND_DOCUMENT, 'document_type' => 'video', 'updated_at' => now()->subMinutes($minutes)])->save();
    foreach ($essais as $numero => [$texte, $essai]) {
        $bilan = json_decode((string) file_get_contents("$dossier/$essai/bilan.json"), true);
        $evenements = json_decode((string) file_get_contents("$dossier/$essai/evenements.json"), true);
        $reponse = (string) collect($evenements)->firstWhere('type', 'terminee')['reponse'];
        $message = $projet->messages()->create(['role' => Message::ROLE_USER, 'content' => $numero === 0 ? $demande : $texte]);
        $run = $projet->agentRuns()->make(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_DOCUMENT]);
        $run->forceFill(['status' => AgentRun::STATUS_COMPLETED, 'started_at' => now()->subMinutes(3), 'finished_at' => now(), 'document_options' => ['type' => 'video', 'action' => $numero === 0 ? 'creer' : 'modifier', 'formats' => []]])->save();
        $folder = DocumentVersion::folder($projet, $run->id);
        $fichiers = ['mp4' => 'video.mp4', 'srt' => 'sous-titres.srt', 'medias' => 'medias.json'];
        foreach ([...array_values($fichiers), 'apercu-1.jpg', 'apercu-2.jpg', 'apercu-3.jpg'] as $f) {
            $disk->put("$folder/$f", (string) file_get_contents("$dossier/$essai/$f"));
        }
        $structure = $bilan['structure'];
        $projet->documentVersions()->create(['agent_run_id' => $run->id, 'number' => $numero + 1, 'title' => $structure['titre'] ?? $nom, 'request' => $message->content, 'files' => $fichiers, 'previews' => ['apercu-1.jpg', 'apercu-2.jpg', 'apercu-3.jpg'], 'pages' => (int) round($structure['duree']), 'structure' => $structure]);
        foreach (['J’écris le plan de la vidéo.', 'J’enregistre la voix off.', 'Je monte la vidéo.', 'Je mixe le son et je vérifie la vidéo.'] as $phrase) {
            $run->steps()->create(['kind' => 'narration', 'target' => $phrase]);
        }
        $projet->messages()->create(['role' => Message::ROLE_ASSISTANT, 'content' => $reponse, 'agent_run_id' => $run->id]);
        $run->forceFill(['report' => RunReport::build($run->fresh(), $reponse)])->save();
    }

    return $projet;
};

$produit = $video('Clean Express', 1, 'Fais-moi une vidéo publicitaire pour mon pressing Clean Express à Agoè : chemise lavée et repassée 500 FCFA, costume 2 500 FCFA, prêt en 24 heures, livraison à domicile. WhatsApp 91 11 11 11.', [['', 'produit'], ['Version plus dynamique', 'modif']]);
$temoignage = $video('Belle Afi', 3, 'Une vidéo témoignage pour mon salon de coiffure Belle Afi à Bè avec ma propre voix. Ma cliente Akossiwa m’a écrit : « Mes tresses tiennent un mois, et on me reçoit toujours à l’heure. Je ne vais plus ailleurs. » Tresses à partir de 5 000 FCFA. WhatsApp 92 45 67 89.', [['', 'temoignage']]);

echo json_encode(['produit' => $produit->id, 'temoignage' => $temoignage->id])."\n";
