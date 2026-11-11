<?php

// Comptes inventés pour les captures des formules (après scripts/preparer-captures-nettoyage.php).
//   php artisan tinker --execute="require 'scripts/preparer-captures-formules.php';"

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$motDePasse = Hash::make('motdepasse-solide-2026');
foreach ([
    ['esi-formules@exemple.tg', 'Esi Amouzou', 'TG', 'pro', now()->addDays(20)],
    ['kwame-formules@exemple.gh', 'Kwame Mensah', 'GH', 'gratuite', null],
] as [$email, $nom, $pays, $formule, $fin]) {
    $user = User::where('email', $email)->first() ?? new User(['email' => $email]);
    $user->forceFill(['name' => $nom, 'password' => $motDePasse, 'email_verified_at' => now(), 'country' => $pays, 'terms_accepted_at' => now(), 'plan' => $formule, 'plan_expires_at' => $fin])->save();
}

echo "Comptes des formules prêts.\n";
