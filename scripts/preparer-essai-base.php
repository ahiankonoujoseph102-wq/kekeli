<?php

// Comptes d'essai inventés pour l'essai réel de la base de connaissances (18 novembre 2026) : « ama-base » (sa boutique
// de pagnes, ses documents) et « kossi-base » (un autre client, pour vérifier qu'il ne voit jamais les documents d'Ama).
//   php artisan tinker --execute="require 'scripts/preparer-essai-base.php';"

use App\Knowledge\KnowledgeSpaces;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

foreach (['ama-base@exemple.tg' => 'Ama Kodjo', 'kossi-base@exemple.tg' => 'Kossi Mensah'] as $email => $name) {
    $user = User::where('email', $email)->first() ?? new User(['email' => $email]);
    $user->forceFill(['name' => $name, 'password' => Hash::make('motdepasse-solide-2026'), 'email_verified_at' => now(), 'country' => 'TG', 'terms_accepted_at' => now(), 'plan' => 'gratuite', 'plan_expires_at' => null, 'appearance' => 'light'])->save();
    foreach ($user->knowledgeSpaces()->get() as $space) {
        app(KnowledgeSpaces::class)->deleteSpace($space);
    }
    echo $email, "\n";
}
