<?php

// Rend un e-mail d'essai (données inventées) en HTML, pour les captures avant et après (docs/captures/logo-pwa-design.md).
// Les images pointent vers le site lancé (APP_URL), pour qu'elles s'affichent dans la capture.
//   APP_URL=http://127.0.0.1:8123 php artisan tinker --execute="require 'scripts/rendre-email-essai.php';" > email.html

use App\Mail\KekeliMail;

echo (new KekeliMail('Votre reçu Kekeli KEK-2026-000001', 'Votre formule Pro est active', ['Merci ! Votre paiement est validé.', 'Votre reçu est joint à cet e-mail. Vous le retrouvez aussi dans Réglages, Formule, Mes reçus.'], details: ['Formule' => 'Pro', 'Période' => 'du 14 novembre 2026 au 14 décembre 2026', 'Montant' => '7 500 FCFA', 'Numéro du reçu' => 'KEK-2026-000001'], button: ['label' => 'Mes reçus', 'url' => config('app.url').'/settings/formule#mes-recus']))->render();
