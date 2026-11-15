<?php

// Relit dans la base les temps d'une création mesurée par scripts/mesurer-creation.js, à la seconde près :
// premier message du client, début du travail, première page écrite (l'aperçu montre le site), fin du travail.
//   PROJETS=id1,id2 php artisan tinker --execute="require 'scripts/relire-mesures-creation.php';"

use App\Models\AgentRun;
use App\Models\Project;
use App\Support\Settings;

$taux = app(Settings::class)->float('usd_to_fcfa');
foreach (array_filter(explode(',', (string) getenv('PROJETS'))) as $id) {
    $project = Project::find($id);
    if (! $project) {
        continue;
    }
    $t0 = $project->messages()->where('role', 'user')->orderBy('id')->first()?->created_at;
    $build = $project->agentRuns()->where(fn ($w) => $w->whereNull('purpose')->orWhere('purpose', '!=', AgentRun::PURPOSE_QUESTION))->orderBy('created_at')->first();
    $page = $build?->steps()->whereIn('kind', ['fichier_ecrit', 'fichier_modifie'])->where('target', 'like', '%.html%')->orderBy('id')->first();
    $s = fn ($moment) => $moment && $t0 ? round($t0->diffInMilliseconds($moment) / 1000, 1) : null;
    echo json_encode([
        'projet' => $project->name,
        'questions' => $project->agentRuns()->get()->contains(fn ($r) => filled(data_get($r->report, 'questions'))),
        'debut_travail_s' => $s($build?->started_at),
        'premiere_page_s' => $s($page?->created_at),
        'premiere_page_depuis_debut_s' => $build?->started_at && $page ? round($build->started_at->diffInMilliseconds($page->created_at) / 1000, 1) : null,
        'fin_s' => $s($build?->finished_at),
        'fcfa' => $build ? round(($build->model_cost_usd + $build->computer_cost_usd + $build->image_cost_usd) * $taux, 1) : null,
        'statut' => $build?->status,
    ], JSON_UNESCAPED_UNICODE)."\n";
}
