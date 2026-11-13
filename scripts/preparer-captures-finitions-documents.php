<?php

// Captures « avant / après » des finitions des documents : le compte de la mesure des documents reçoit aussi un projet
// inventé de chaque type (site, application, agent, automatisation, discussion), pour montrer « Récents ».
//   php artisan tinker --execute="require 'scripts/preparer-captures-finitions-documents.php';"

use App\Models\Project;
use App\Models\User;

$user = User::where('email', 'esi-documents@exemple.tg')->firstOrFail();
$user->projects()->where('name', 'like', '[essai] %')->get()->each->delete();

foreach ([
    ['[essai] Restaurant Le Palmier', ['kind' => Project::KIND_SITE]],
    ['[essai] Rendez-vous du salon', ['kind' => Project::KIND_WEBAPP, 'is_application' => true, 'app_stack' => 'laravel']],
    ['[essai] Assistante du salon', ['kind' => Project::KIND_WEBAPP, 'is_agent' => true, 'app_stack' => 'langgraph']],
    ['[essai] Conseils du matin', ['kind' => Project::KIND_WEBAPP, 'is_automation' => true, 'app_stack' => 'automatisation']],
    ['[essai] Prix des pagnes à Lomé', ['kind' => Project::KIND_CHAT]],
] as [$name, $attributes]) {
    $project = $user->projects()->make(['name' => $name]);
    $project->forceFill($attributes)->save();
    $project->forceFill(['updated_at' => now()->subHours(3)])->saveQuietly();
}

echo $user->projects()->count(), "\n";
