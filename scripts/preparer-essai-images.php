<?php

// Comptes d'essai inventés pour la mesure réelle des images et des affiches (Togo) : un compte Gratuit et un compte Pro.
//   php artisan tinker --execute="require 'scripts/preparer-essai-images.php';"

use App\Models\User;
use Illuminate\Support\Facades\Hash;

foreach (['images-gratuite@exemple.tg' => ['gratuite', null], 'images-pro@exemple.tg' => ['pro', now()->addDays(20)]] as $email => [$plan, $expires]) {
    $user = User::where('email', $email)->first() ?? new User(['email' => $email]);
    $user->forceFill(['name' => 'Ama Koffi', 'password' => Hash::make('motdepasse-solide-2026'), 'email_verified_at' => now(), 'country' => 'TG', 'terms_accepted_at' => now(), 'plan' => $plan, 'plan_expires_at' => $expires, 'appearance' => 'system'])->save();
    $user->projects()->get()->each->delete();
    echo $user->email, "\n";
}
