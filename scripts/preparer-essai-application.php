<?php

// Compte d'essai inventé pour l'essai réel de la création d'une application (formule Pro, Togo).
//   php artisan tinker --execute="require 'scripts/preparer-essai-application.php';"

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$user = User::where('email', 'afi-application@exemple.tg')->first() ?? new User(['email' => 'afi-application@exemple.tg']);
$user->forceFill(['name' => 'Afi Mensah', 'password' => Hash::make('motdepasse-solide-2026'), 'email_verified_at' => now(), 'country' => 'TG', 'terms_accepted_at' => now(), 'plan' => 'pro', 'plan_expires_at' => now()->addDays(20), 'appearance' => 'system'])->save();
$user->projects()->get()->each->delete();

echo $user->email, "\n";
