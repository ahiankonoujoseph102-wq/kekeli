<?php

// Compte d'essai inventé pour la mesure réelle des documents et des présentations (formule Pro, Togo).
//   php artisan tinker --execute="require 'scripts/preparer-essai-documents.php';"

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$user = User::where('email', 'esi-documents@exemple.tg')->first() ?? new User(['email' => 'esi-documents@exemple.tg']);
$user->forceFill(['name' => 'Esi Lawson', 'password' => Hash::make('motdepasse-solide-2026'), 'email_verified_at' => now(), 'country' => 'TG', 'terms_accepted_at' => now(), 'plan' => 'pro', 'plan_expires_at' => now()->addDays(20), 'appearance' => 'system'])->save();
$user->projects()->get()->each->delete();

echo $user->email, "\n";
