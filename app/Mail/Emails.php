<?php

namespace App\Mail;

use App\Jobs\SendEmail;
use App\Models\EmailLog;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\User;
use App\Support\Contact;
use App\Support\Settings;
use Carbon\CarbonInterface;

/**
 * The e-mails of Kekeli, in French, sent through the queue and written in the journal of the administration:
 * login code, welcome, payment to check (administrator), payment refused, payment validated with the receipt,
 * formula ending tomorrow, formula ended, password link. Codes, password links and receipts always go first.
 */
class Emails
{
    /** Types that pass even when the ceiling of the day is reached. */
    public const PRIORITY = ['login_code', 'password_reset', 'receipt'];

    public const TYPES = [
        'login_code' => 'Login code',
        'welcome' => 'Welcome',
        'payment_to_check' => 'Payment to check',
        'payment_refused' => 'Payment refused',
        'receipt' => 'Receipt',
        'plan_ending' => 'Formula ending tomorrow',
        'plan_ended' => 'Formula ended',
        'password_reset' => 'Password',
    ];

    public function __construct(private Settings $settings) {}

    public function send(string $type, string $to, KekeliMail $mail, ?User $user = null): EmailLog
    {
        $log = EmailLog::create([
            'type' => $type,
            'user_id' => $user?->id,
            'recipient_masked' => EmailLog::mask($to),
            'priority' => in_array($type, self::PRIORITY, true),
            'status' => EmailLog::QUEUED,
        ]);
        SendEmail::dispatch($log->id, $to, $mail);

        return $log;
    }

    public static function date(?CarbonInterface $date, bool $hour = false): string
    {
        if (! $date) {
            return '';
        }
        $local = $date->copy()->timezone(config('kekeli.display_timezone'))->locale('fr');

        return $hour ? $local->isoFormat('D MMMM YYYY').' à '.$local->format('G').' h '.$local->format('i') : $local->isoFormat('D MMMM YYYY');
    }

    public function loginCode(string $email, string $code, string $url): EmailLog
    {
        $minutes = (int) config('kekeli.login_codes.minutes');

        return $this->send('login_code', $email, new KekeliMail(
            subjectLine: 'Votre code Kekeli : '.$code,
            title: 'Votre code de connexion',
            paragraphs: ['Voici votre code pour vous connecter à Kekeli. Tapez-le dans la page ouverte, ou touchez le bouton.'],
            code: $code,
            button: ['label' => 'Se connecter', 'url' => $url],
            note: 'Ce code est valable '.$minutes.' minutes et ne sert qu’une fois. Si vous n’avez rien demandé, ignorez cet e-mail : personne ne peut entrer dans votre compte sans ce code.',
        ), User::query()->where('email', $email)->first());
    }

    public function welcome(User $user): EmailLog
    {
        return $this->send('welcome', $user->email, new KekeliMail(
            subjectLine: 'Bienvenue sur Kekeli',
            title: 'Bienvenue, '.$user->name,
            paragraphs: [
                'Votre compte Kekeli est prêt.',
                'Dites simplement ce que vous voulez créer (un site, une application, un assistant, une affiche, une vidéo, un document) : Kekeli s’en occupe et vous montre le résultat.',
            ],
            button: ['label' => 'Ouvrir Kekeli', 'url' => route('projects.index')],
        ), $user);
    }

    /** A screenshot of a Mobile Money payment is waiting: to the administrator. */
    public function paymentToCheck(Payment $payment): void
    {
        $plan = (array) ($this->settings->get('plans')[$payment->plan] ?? []);
        foreach ($this->adminAddresses() as $address) {
            $this->send('payment_to_check', $address, new KekeliMail(
                subjectLine: 'Un paiement attend votre vérification',
                title: 'Un paiement attend votre vérification',
                paragraphs: ['Un client a envoyé la capture de son paiement. Vérifiez-le dans l’historique de l’opérateur, puis validez-le ou refusez-le.'],
                details: [
                    'Formule' => (string) ($plan['name'] ?? $payment->plan),
                    'Montant' => number_format($payment->amount_fcfa, 0, ',', ' ').' FCFA',
                    'Pays' => (string) (config('kekeli.countries.'.$payment->country.'.name') ?? $payment->country),
                    'Client' => $payment->user->email,
                    'Envoyé le' => self::date($payment->created_at, true),
                ],
                button: ['label' => 'Voir les paiements', 'url' => route('admin.payments')],
            ));
        }
    }

    public function paymentRefused(Payment $payment): EmailLog
    {
        $plan = (array) ($this->settings->get('plans')[$payment->plan] ?? []);

        return $this->send('payment_refused', $payment->user->email, new KekeliMail(
            subjectLine: 'Votre paiement n’a pas pu être validé',
            title: 'Votre paiement n’a pas pu être validé',
            paragraphs: array_values(array_filter([
                'Nous n’avons pas pu valider votre paiement pour la formule '.($plan['name'] ?? $payment->plan).'.',
                $payment->refusal_reason ? 'Raison : '.$payment->refusal_reason : null,
                'Si c’est une erreur, envoyez une nouvelle capture depuis la page des formules, ou contactez-nous : nous regardons avec vous.',
                Contact::sentence() ?: null,
            ])),
            button: ['label' => 'Voir les formules', 'url' => route('plans')],
        ), $payment->user);
    }

    /** The payment is validated: the receipt goes with it, as a PDF. */
    public function receipt(Receipt $receipt, bool $again = false): EmailLog
    {
        $cancel = $receipt->isCancellation();
        $title = $cancel ? 'Reçu d’annulation '.$receipt->number : ($again ? 'Votre reçu '.$receipt->number : 'Votre formule '.$receipt->plan_name.' est active');

        return $this->send('receipt', $receipt->client_email, new KekeliMail(
            subjectLine: $cancel ? 'Reçu d’annulation '.$receipt->number : 'Votre reçu Kekeli '.$receipt->number,
            title: $title,
            paragraphs: $cancel
                ? ['Le reçu '.$receipt->cancelledNumber().' est annulé. Le reçu d’annulation est joint à cet e-mail.']
                : array_values(array_filter([
                    $again ? null : 'Merci ! Votre paiement est validé.',
                    'Votre reçu est joint à cet e-mail. Vous le retrouvez aussi dans Réglages, Formule, Mes reçus.',
                ])),
            details: $cancel ? ['Numéro' => $receipt->number] : [
                'Formule' => $receipt->plan_name,
                'Période' => 'du '.self::date($receipt->period_start).' au '.self::date($receipt->period_end),
                'Montant' => $receipt->amountText(),
                'Numéro du reçu' => $receipt->number,
            ],
            button: ['label' => 'Mes reçus', 'url' => route('settings.plan').'#mes-recus'],
            file: $receipt->pdf_path ? ['disk' => Receipt::disk(), 'path' => $receipt->pdf_path, 'name' => $receipt->number.'.pdf'] : null,
        ), $receipt->user);
    }

    public function planEnding(User $user, string $planName): EmailLog
    {
        return $this->send('plan_ending', $user->email, new KekeliMail(
            subjectLine: 'Votre formule '.$planName.' se termine demain',
            title: 'Votre formule '.$planName.' se termine demain',
            paragraphs: [
                'Votre formule '.$planName.' se termine le '.self::date($user->plan_expires_at, true).' (heure de Lomé).',
                'Pour continuer sans interruption, renouvelez-la : les jours qui restent ne sont jamais perdus.',
            ],
            button: ['label' => 'Renouveler', 'url' => route('plans')],
        ), $user);
    }

    public function planEnded(User $user, string $planName): EmailLog
    {
        return $this->send('plan_ended', $user->email, new KekeliMail(
            subjectLine: 'Votre formule '.$planName.' est terminée',
            title: 'Votre formule '.$planName.' est terminée',
            paragraphs: [
                'Votre compte est revenu à la formule Gratuite. Vos projets sont tous gardés.',
                'Vous pouvez reprendre une formule à tout moment.',
            ],
            button: ['label' => 'Voir les formules', 'url' => route('plans')],
        ), $user);
    }

    public function passwordReset(User $user, string $url): EmailLog
    {
        return $this->send('password_reset', $user->email, new KekeliMail(
            subjectLine: 'Changer votre mot de passe Kekeli',
            title: 'Changer votre mot de passe',
            paragraphs: ['Vous avez demandé à changer le mot de passe de votre compte Kekeli. Touchez le bouton pour en choisir un nouveau.'],
            button: ['label' => 'Choisir un nouveau mot de passe', 'url' => $url],
            note: 'Ce lien est valable '.(int) config('auth.passwords.users.expire', 60).' minutes. Si vous n’avez rien demandé, ignorez cet e-mail : votre mot de passe ne change pas.',
        ), $user);
    }

    /**
     * Who receives the e-mails of the administrator: ADMIN_ALERT_EMAIL, otherwise the administrator accounts,
     * otherwise the sender address.
     *
     * @return list<string>
     */
    public function adminAddresses(): array
    {
        $configured = trim((string) config('kekeli.admin_alert_email'));
        if ($configured !== '') {
            return [$configured];
        }
        $admins = User::query()->where('is_admin', true)->pluck('email')->filter()->values()->all();

        return $admins !== [] ? $admins : array_values(array_filter([(string) config('mail.from.address')]));
    }
}
