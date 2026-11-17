<?php

// Données inventées pour les captures de l'étape 5 des vidéos motion design (16 novembre 2026) : les trois questions
// avant une vidéo (style, format, voix), telles que le modèle gratuit les a posées pendant le banc
// (runner/mesures/questions-video-etape-5.json). Aucune requête au modèle ici.
//   php artisan tinker --execute="require 'scripts/preparer-captures-montage-etape-5.php';"

use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$user = User::where('email', 'afi-montage@exemple.tg')->first() ?? new User(['email' => 'afi-montage@exemple.tg']);
$user->forceFill(['name' => 'Afi Mensah', 'password' => Hash::make('motdepasse-solide-2026'), 'email_verified_at' => now(), 'country' => 'TG', 'terms_accepted_at' => now(), 'plan' => 'free', 'appearance' => 'system'])->save();
$user->projects()->get()->each->delete();

$questions = function (string $nom, int $minutes, string $demande, array $items) use ($user) {
    $projet = $user->projects()->make(['name' => $nom]);
    $projet->forceFill(['kind' => Project::KIND_CHAT, 'updated_at' => now()->subMinutes($minutes)])->save();
    $message = $projet->messages()->create(['role' => Message::ROLE_USER, 'content' => $demande]);
    $run = $projet->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
    $texte = "Pour créer une vidéo adaptée, j’ai besoin de quelques précisions :\n\n".collect($items)->map(fn ($q, $i) => ($i + 1).'. '.$q['question'])->implode("\n")."\n\nTouchez vos choix, écrivez vos réponses, ou laissez-moi choisir pour vous.";
    $projet->messages()->create(['role' => Message::ROLE_ASSISTANT, 'content' => $texte, 'agent_run_id' => $run->id]);
    $run->forceFill(['status' => AgentRun::STATUS_COMPLETED, 'finished_at' => now(), 'requests' => 1,
        'report' => ['done' => $texte, 'note' => null, 'question' => true, 'markdown' => true, 'sources' => [], 'files' => [], 'files_count' => 0, 'tests' => [], 'problems' => [],
            'questions' => ['tool' => 'creer_video', 'items' => $items]]])->save();
};

$style = ['question' => 'Quel style pour votre vidéo ?', 'choices' => ['Dynamique', 'Sobre et professionnel', 'Produit et boutique', 'Témoignage', 'Éducatif']];
$format = ['question' => 'Quel format ?', 'choices' => ['Vertical (TikTok, statut WhatsApp)', 'Carré (Facebook, Instagram)', 'Horizontal (YouTube)']];
$voix = ['question' => 'Quelle voix off voulez-vous ?', 'choices' => ['Voix de femme', 'Voix d’homme', 'Ma propre voix']];

// 1. Une demande détaillée : les trois questions, toujours.
$questions('Publicité Clean Express', 1, 'Fais-moi une vidéo publicitaire pour mon pressing Clean Express à Agoè, chemise lavée et repassée 500 FCFA, WhatsApp 91 11 11 11', [$style, $format, $voix]);
// 2. Le style et le format déjà donnés : seulement la voix (et ce qui manque).
$questions('Témoignage Belle Afi', 3, 'Une vidéo témoignage pour mon statut WhatsApp, salon Belle Afi : ma cliente Akossiwa a dit « Mes tresses tiennent un mois, je ne vais plus ailleurs »', [$voix, ['question' => 'Quel est le prix de vos tresses ?', 'choices' => ['À partir de 5 000 FCFA', 'À partir de 10 000 FCFA']]]);

echo "Prêt : afi-montage@exemple.tg\n";
