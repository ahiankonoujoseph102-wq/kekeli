<?php

// Captures de « GitHub et automatisations » : le compte de l'essai réel est relié à un GitHub inventé (jetons inventés,
// jamais de vraie clé) et son automatisation à un dépôt inventé, avec une pull request ouverte.
// Le site doit tourner avec des réglages inventés de GitHub App (GITHUB_APP_SLUG, GITHUB_CLIENT_ID, GITHUB_CLIENT_SECRET).
//   php artisan tinker --execute="require 'scripts/preparer-captures-github.php';"

use App\Models\User;

$user = User::where('email', 'kofi-automatisation@exemple.tg')->firstOrFail();
$user->forceFill([
    'github_login' => 'kofi-agbeko',
    'github_token' => 'ghu_jeton_invente_pour_les_captures',
    'github_refresh_token' => 'ghr_jeton_invente_pour_les_captures',
    'github_token_expires_at' => now()->addHours(8),
    'github_installation_id' => 1234567,
])->save();

$project = $user->projects()->where('is_automation', true)->latest('id')->firstOrFail();
$project->forceFill([
    'repository' => 'kofi-agbeko/conseils-du-matin',
    'github_repo' => 'kofi-agbeko/conseils-du-matin',
    'repository_base_branch' => 'main',
    'work_branch' => 'kekeli/2026-11-12-173000',
    'github_status' => 'sent',
    'github_push_mode' => 'pull_request',
    'pr_number' => 1,
    'pr_url' => 'https://github.com/kofi-agbeko/conseils-du-matin/pull/1',
    'pr_status' => 'open',
    'pushed_at' => now(),
])->save();

echo $project->id, "\n";
