<?php

// Données inventées pour les captures « Accueil et abonnements » (docs/captures/accueil-abonnements.md), à lancer
// après scripts/preparer-captures-design.php : le compte Pro d'essai est abonné depuis 40 jours (paiements validés
// inventés) et n'a utilisé qu'une partie de son énergie le mois dernier, pour montrer l'énergie reportée.
//   php artisan tinker --execute="require 'scripts/preparer-captures-abonnements.php';"

use App\Agent\Energy;
use App\Models\Payment;
use App\Models\User;
use App\Support\Settings;

$afi = User::query()->where('email', 'afi-design@exemple.tg')->firstOrFail();
$afi->forceFill(['plan' => 'pro', 'plan_expires_at' => now()->addDays(20)])->save();

$debut = now()->toImmutable()->subDays(40);
foreach ([[$debut, now()->toImmutable()->addDays(20)->subDays(30)], [now()->toImmutable()->addDays(20)->subDays(30), now()->toImmutable()->addDays(20)]] as $i => [$de, $a]) {
    Payment::query()->forceCreate(['user_id' => $afi->id, 'plan' => 'pro', 'amount_fcfa' => 7500, 'country' => 'TG', 'screenshot_path' => 'paiements/design/capture.png', 'status' => Payment::STATUS_VALIDATED, 'starts_at' => $de, 'expires_at' => $a, 'reviewed_at' => $de, 'method' => 'Mixx by Yas']);
}

// Le mois dernier : 1 400 FCFA d'énergie utilisés sur 4 000 (2 600 reportés, soit +65 %).
$projet = $afi->projects()->firstOrFail();
$mois = app(Energy::class)->monthStart($afi);
$run = $projet->agentRuns()->create([]);
$run->forceFill(['status' => 'completed', 'model_cost_usd' => 1400 / app(Settings::class)->float('usd_to_fcfa'), 'created_at' => $mois->subDays(6), 'updated_at' => $mois->subDays(6)])->save();

echo 'Énergie reportée pour Afi : '.round(app(Energy::class)->carriedOverFcfa($afi->fresh())).' FCFA'.PHP_EOL;
