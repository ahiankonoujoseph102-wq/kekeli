<?php

// Compte d'essai inventé pour l'essai réel de la création d'un agent IA (formule Pro, Togo).
//   php artisan tinker --execute="require 'scripts/preparer-essai-agent.php';"

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$user = User::where('email', 'afi-agent@exemple.tg')->first() ?? new User(['email' => 'afi-agent@exemple.tg']);
$user->forceFill(['name' => 'Afi Mensah', 'password' => Hash::make('motdepasse-solide-2026'), 'email_verified_at' => now(), 'country' => 'TG', 'terms_accepted_at' => now(), 'plan' => 'pro', 'plan_expires_at' => now()->addDays(20), 'appearance' => 'system'])->save();
$user->projects()->get()->each->delete();
DB::table('agent_trial_messages')->where('user_id', $user->id)->delete();

echo $user->email, "\n";
