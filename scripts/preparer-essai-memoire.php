<?php

// Comptes d'essai inventés pour la mesure de la mémoire (docs/plan.md, partie 26.21) : « ama-avant » (mémoire
// désactivée : Kekeli tel qu'il était avant), « ama-apres » (mémoire active) et « kossi-apres » (un autre client, pour
// vérifier qu'aucun souvenir ne passe d'un compte à l'autre). Pendant l'essai, la formule Gratuite répond et construit
// avec le modèle gratuit (aucune dépense OpenAI) et reçoit assez d'énergie ; « KEKELI_RETABLIR=1 » remet tout comme avant.
//   php artisan tinker --execute="require 'scripts/preparer-essai-memoire.php';"

use App\Memory\UserMemory;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Facades\Hash;

$settings = app(Settings::class);
$plans = $settings->get('plans');
if (getenv('KEKELI_RETABLIR') === '1') {
    unset($plans['gratuite']['answer_model'], $plans['gratuite']['build_model']);
    $plans['gratuite']['energy_fcfa'] = 30;
    $settings->set('plans', $plans);
    $settings->set('free_daily_cap_fcfa', 1000);
    echo "réglages rétablis\n";

    return;
}
$plans['gratuite']['answer_model'] = 'gemini/gemini-3.5-flash-lite';
$plans['gratuite']['build_model'] = 'gemini/gemini-3.5-flash-lite';
$plans['gratuite']['energy_fcfa'] = 3000;
$settings->set('plans', $plans);
$settings->set('free_daily_cap_fcfa', 20000);

foreach (['ama-avant@exemple.tg' => ['Ama Kodjo', false], 'ama-apres@exemple.tg' => ['Ama Kodjo', true], 'kossi-apres@exemple.tg' => ['Kossi Mensah', true]] as $email => [$name, $memory]) {
    $user = User::where('email', $email)->first() ?? new User(['email' => $email]);
    $user->forceFill(['name' => $name, 'password' => Hash::make('motdepasse-solide-2026'), 'email_verified_at' => now(), 'country' => 'TG', 'terms_accepted_at' => now(), 'plan' => 'gratuite', 'plan_expires_at' => null, 'appearance' => 'system', 'memory_enabled' => $memory])->save();
    $user->projects()->get()->each->delete();
    app(UserMemory::class)->forgetAll($user);
    echo $email, "\n";
}
