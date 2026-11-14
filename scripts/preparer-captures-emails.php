<?php

// Rend chaque e-mail de Kekeli en HTML (données inventées) dans storage/app/captures-emails/, pour les captures
// « tel qu'il s'affiche sur téléphone » (scripts/captures-emails-connexion.js).
//   php artisan tinker --execute="require 'scripts/preparer-captures-emails.php';"

use App\Mail\KekeliMail;
use App\Support\Contact;

$dossier = storage_path('app/captures-emails');
@mkdir($dossier, 0777, true);
$url = 'http://127.0.0.1:8123';
$mails = [
    '1-code-de-connexion' => new KekeliMail('Votre code Kekeli : 482913', 'Votre code de connexion', ['Voici votre code pour vous connecter à Kekeli. Tapez-le dans la page ouverte, ou touchez le bouton.'], code: '482913', button: ['label' => 'Se connecter', 'url' => $url.'/connexion/lien/exemple'], note: 'Ce code est valable 10 minutes et ne sert qu’une fois. Si vous n’avez rien demandé, ignorez cet e-mail : personne ne peut entrer dans votre compte sans ce code.'),
    '2-bienvenue' => new KekeliMail('Bienvenue sur Kekeli', 'Bienvenue, Afi Mensah', ['Votre compte Kekeli est prêt.', 'Dites simplement ce que vous voulez créer (un site, une application, un assistant, une affiche, une vidéo, un document) : Kekeli s’en occupe et vous montre le résultat.'], button: ['label' => 'Ouvrir Kekeli', 'url' => $url.'/projets']),
    '3-paiement-a-verifier' => new KekeliMail('Un paiement attend votre vérification', 'Un paiement attend votre vérification', ['Un client a envoyé la capture de son paiement. Vérifiez-le dans l’historique de l’opérateur, puis validez-le ou refusez-le.'], details: ['Formule' => 'Pro', 'Montant' => '7 500 FCFA', 'Pays' => 'Togo', 'Client' => 'afi.mensah@exemple.tg', 'Envoyé le' => '14 novembre 2026 à 9 h 30'], button: ['label' => 'Voir les paiements', 'url' => $url.'/administration/paiements']),
    '4-paiement-refuse' => new KekeliMail('Votre paiement n’a pas pu être validé', 'Votre paiement n’a pas pu être validé', ['Nous n’avons pas pu valider votre paiement pour la formule Pro.', 'Raison : la capture ne montre pas le montant payé.', 'Si c’est une erreur, envoyez une nouvelle capture depuis la page des formules, ou contactez-nous : nous regardons avec vous.', Contact::sentence()], button: ['label' => 'Voir les formules', 'url' => $url.'/formules']),
    '5-paiement-valide-recu' => new KekeliMail('Votre reçu Kekeli KEK-2026-000001', 'Votre formule Pro est active', ['Merci ! Votre paiement est validé.', 'Votre reçu est joint à cet e-mail. Vous le retrouvez aussi dans Réglages, Formule, Mes reçus.'], details: ['Formule' => 'Pro', 'Période' => 'du 14 novembre 2026 au 14 décembre 2026', 'Montant' => '7 500 FCFA', 'Numéro du reçu' => 'KEK-2026-000001'], button: ['label' => 'Mes reçus', 'url' => $url.'/settings/formule#mes-recus']),
    '6-formule-finit-demain' => new KekeliMail('Votre formule Pro se termine demain', 'Votre formule Pro se termine demain', ['Votre formule Pro se termine le 14 décembre 2026 à 10 h 00 (heure de Lomé).', 'Pour continuer sans interruption, renouvelez-la : les jours qui restent ne sont jamais perdus.'], button: ['label' => 'Renouveler', 'url' => $url.'/formules']),
    '7-formule-terminee' => new KekeliMail('Votre formule Pro est terminée', 'Votre formule Pro est terminée', ['Votre compte est revenu à la formule Gratuite. Vos projets sont tous gardés.', 'Vous pouvez reprendre une formule à tout moment.'], button: ['label' => 'Voir les formules', 'url' => $url.'/formules']),
    '8-mot-de-passe' => new KekeliMail('Changer votre mot de passe Kekeli', 'Changer votre mot de passe', ['Vous avez demandé à changer le mot de passe de votre compte Kekeli. Touchez le bouton pour en choisir un nouveau.'], button: ['label' => 'Choisir un nouveau mot de passe', 'url' => $url.'/reset-password/exemple'], note: 'Ce lien est valable 60 minutes. Si vous n’avez rien demandé, ignorez cet e-mail : votre mot de passe ne change pas.'),
];
foreach ($mails as $nom => $mail) {
    file_put_contents("$dossier/$nom.html", $mail->render());
}
echo count($mails), " e-mails rendus\n";
