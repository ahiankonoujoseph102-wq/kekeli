<?php

// Essai réel d'un reçu (docs/plan.md, partie 26.22) avec des données inventées : un client d'essai paie la formule
// Pro par Flooz, l'administrateur valide ; le reçu est mis en page par le vrai moteur de documents (Runner, atelier
// E2B), puis UN seul e-mail réel part vers l'adresse de l'administrateur (ADMIN_ALERT_EMAIL ou MAIL_FROM_ADDRESS),
// avec ce reçu en pièce jointe. L'adresse du client d'essai et celle de l'émetteur sont inventées : le reçu peut
// être montré dans le dépôt public. Site, file d'attente et Runner lancés.
//   php artisan tinker --execute="require 'scripts/essai-recu-reel.php';"

use App\Agent\RunnerClient;
use App\Billing\Subscriptions;
use App\Jobs\GenerateReceipt;
use App\Jobs\SendEmail;
use App\Mail\Emails;
use App\Mail\KekeliMail;
use App\Models\EmailLog;
use App\Models\Receipt;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Facades\Hash;

$settings = app(Settings::class);
$settings->set('receipt_issuer', ['name' => 'Lomé IA School', 'address' => 'Adidogomé, Lomé, Togo', 'email' => 'bonjour@kekeli.dev', 'phone' => '', 'legal' => '']);
$settings->set('payment_countries', ['TG' => ['holder' => 'Titulaire inventé', 'methods' => [['name' => 'Mixx by Yas', 'number' => '+228 90 00 00 01'], ['name' => 'Flooz', 'number' => '+228 90 00 00 02']]]]);

$admin = User::where('email', 'admin-essai@exemple.tg')->first() ?? new User(['email' => 'admin-essai@exemple.tg']);
$admin->forceFill(['name' => 'Administrateur d’essai', 'password' => Hash::make('motdepasse-admin-2026'), 'email_verified_at' => now(), 'country' => 'TG', 'terms_accepted_at' => now(), 'is_admin' => true])->save();
$client = User::where('email', 'afi.mensah@exemple.tg')->first() ?? new User(['email' => 'afi.mensah@exemple.tg']);
$client->forceFill(['name' => 'Afi Mensah', 'password' => null, 'email_verified_at' => now(), 'country' => 'TG', 'terms_accepted_at' => now(), 'plan' => 'gratuite', 'plan_expires_at' => null])->save();

$payment = app(Subscriptions::class)->submit($client, 'pro', 'TG', 'paiements/essai.png');
app(Subscriptions::class)->validate($payment, $admin, 'Flooz', 'FL251114.0930.K7Q2');
$receipt = Receipt::where('payment_id', $payment->id)->sole();
echo 'Reçu : ', $receipt->number, "\n";

// La mise en page tout de suite (sans attendre la file), par le vrai Runner et le vrai moteur.
$debut = microtime(true);
(new GenerateReceipt($receipt->id, false))->handle(app(RunnerClient::class), app(Emails::class));
echo 'PDF : ', $receipt->fresh()->pdf_path, ' en ', round(microtime(true) - $debut), " secondes\n";

// UN seul e-mail réel, vers l'adresse de l'administrateur, avec le reçu en pièce jointe.
$to = app(Emails::class)->adminAddresses()[0] ?? null;
$receipt = $receipt->fresh();
$log = EmailLog::create(['type' => 'receipt', 'recipient_masked' => EmailLog::mask((string) $to), 'priority' => true, 'status' => EmailLog::QUEUED]);
$mail = new KekeliMail('Essai : reçu Kekeli '.$receipt->number, 'Essai réel d’un reçu', ['Ceci est un essai de Kekeli avec des données inventées. Le reçu est joint.'], details: ['Numéro du reçu' => $receipt->number, 'Montant' => $receipt->amountText()], file: ['disk' => Receipt::disk(), 'path' => $receipt->pdf_path, 'name' => $receipt->number.'.pdf']);
try {
    (new SendEmail($log->id, (string) $to, $mail))->handle();
    echo "E-mail réel : parti\n";
} catch (Throwable $e) {
    $log->update(['status' => EmailLog::FAILED]);
    echo 'E-mail réel : non parti (', class_basename($e), ' : ', str_contains($e->getMessage(), 'Connection refused') ? 'connexion refusée par le réseau' : 'erreur', ")\n";
}
