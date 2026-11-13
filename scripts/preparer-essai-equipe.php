<?php

// Compte d'essai inventé pour l'essai réel « équipe d'agents contre agent seul » (formule Pro, Togo). Pendant l'essai,
// la formule Pro construit avec GPT-6.1 Terra (le modèle de construction de la Gratuite, moins cher) et l'essai gratuit
// des agents permet 40 messages ; « KEKELI_RETABLIR=1 » remet les réglages comme avant.
//   php artisan tinker --execute="require 'scripts/preparer-essai-equipe.php';"

use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Facades\Hash;

$settings = app(Settings::class);
$plans = $settings->get('plans');
if (getenv('KEKELI_RETABLIR') === '1') {
    unset($plans['pro']['build_model']);
    $settings->set('plans', $plans);
    $settings->set('agent_trial_messages_per_day', 10);
    echo "réglages rétablis\n";

    return;
}
$plans['pro']['build_model'] = 'openai/gpt-6.1-terra';
$settings->set('plans', $plans);
$settings->set('agent_trial_messages_per_day', 40);

$user = User::where('email', 'ama-equipe@exemple.tg')->first() ?? new User(['email' => 'ama-equipe@exemple.tg']);
$user->forceFill(['name' => 'Ama Kodjo', 'password' => Hash::make('motdepasse-solide-2026'), 'email_verified_at' => now(), 'country' => 'TG', 'terms_accepted_at' => now(), 'plan' => 'pro', 'plan_expires_at' => now()->addDays(20), 'appearance' => 'system'])->save();
$user->projects()->get()->each->delete();
DB::table('agent_trial_messages')->where('user_id', $user->id)->delete();

echo $user->email, "\n";
