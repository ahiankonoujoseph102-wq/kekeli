<?php

namespace App\Billing;

use App\Jobs\GenerateReceipt;
use App\Mail\Emails;
use App\Models\Payment;
use App\Models\Receipt;
use App\Support\Contact;
use App\Support\Phones;
use App\Support\Settings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Receipts of the subscriptions: one per validated payment, numbered KEK-<year>-<6 digits> without any duplicate
 * (unique index and a new try), with the information fixed at the time of the payment. The PDF is laid out by the
 * document engine of Kekeli (one page), then sent to the client as an attachment. A receipt is never changed: an
 * error gives a cancellation receipt linked to it.
 */
class Receipts
{
    public function __construct(private Settings $settings) {}

    /** The receipt of a validated payment (the same one if it already exists). */
    public function issueFor(Payment $payment): Receipt
    {
        $existing = Receipt::query()->where('payment_id', $payment->id)->where('kind', Receipt::RECEIPT)->first();
        if ($existing) {
            return $existing;
        }

        $user = $payment->user;
        $plans = (array) $this->settings->get('plans');
        $plan = (array) ($plans[$payment->plan] ?? []);
        $country = $user->countryDetails();
        $inDollars = $country !== null && ! str_contains((string) ($country['currency'] ?? ''), 'FCFA');

        $receipt = $this->create([
            'kind' => Receipt::RECEIPT,
            'payment_id' => $payment->id,
            'user_id' => $user->id,
            'client_name' => $user->name,
            'client_email' => $user->email,
            'plan_name' => (string) ($plan['name'] ?? ucfirst((string) $payment->plan)),
            'paid_at' => $payment->reviewed_at ?? now(),
            'period_start' => $payment->starts_at,
            'period_end' => $payment->expires_at,
            'amount_fcfa' => (int) $payment->amount_fcfa,
            'amount_usd' => $inDollars && (int) ($plan['price_usd'] ?? 0) > 0 ? (int) $plan['price_usd'] : null,
            'method' => $payment->method,
            'reference' => $payment->reference,
        ]);
        GenerateReceipt::dispatch($receipt->id, true);

        return $receipt;
    }

    /** A receipt with an error: a cancellation receipt points to it (the first one is never changed). */
    public function cancel(Receipt $receipt, string $reason): Receipt
    {
        if ($receipt->isCancellation() || $receipt->cancellation()) {
            return $receipt->cancellation() ?? $receipt;
        }
        $cancellation = $this->create([
            'kind' => Receipt::CANCELLATION,
            'cancels_receipt_id' => $receipt->id,
            'payment_id' => $receipt->payment_id,
            'user_id' => $receipt->user_id,
            'client_name' => $receipt->client_name,
            'client_email' => $receipt->client_email,
            'plan_name' => $receipt->plan_name,
            'paid_at' => now(),
            'period_start' => $receipt->period_start,
            'period_end' => $receipt->period_end,
            'amount_fcfa' => -$receipt->amount_fcfa,
            'amount_usd' => $receipt->amount_usd !== null ? -$receipt->amount_usd : null,
            'method' => $receipt->method,
            'reference' => $receipt->reference,
            'reason' => mb_substr(trim($reason), 0, 300) ?: null,
        ]);
        GenerateReceipt::dispatch($cancellation->id, true);

        return $cancellation;
    }

    /** « Renvoyer » in the administration: the same PDF, sent again. */
    public function resend(Receipt $receipt): void
    {
        if ($receipt->pdf_path) {
            app(Emails::class)->receipt($receipt, again: true);
        } else {
            GenerateReceipt::dispatch($receipt->id, true);
        }
    }

    /**
     * The next number of the year, written in the same transaction: a unique index refuses any duplicate, and a
     * collision (two validations at the same time) simply takes the next number.
     *
     * @param  array<string, mixed>  $fields
     */
    private function create(array $fields): Receipt
    {
        $year = (int) now(config('kekeli.display_timezone'))->format('Y');
        for ($try = 1; ; $try++) {
            try {
                return DB::transaction(function () use ($fields, $year) {
                    // Le dernier reçu de l'année est verrouillé (PostgreSQL refuse un verrou sur un calcul de maximum).
                    $sequence = (int) Receipt::query()->where('year', $year)->orderByDesc('sequence')->lockForUpdate()->value('sequence') + 1;

                    return Receipt::create($fields + [
                        'year' => $year,
                        'sequence' => $sequence,
                        'number' => sprintf('KEK-%d-%06d', $year, $sequence),
                        'issuer' => $this->issuer(),
                    ]);
                });
            } catch (QueryException $exception) {
                if ($try >= 5) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * Who issues the receipt (Administration → Réglages), fixed in the receipt. No legal number is invented.
     *
     * @return array{name: string, address: string, email: string, phone: string, legal: string, phones: list<string>}
     */
    public function issuer(): array
    {
        $issuer = (array) $this->settings->get('receipt_issuer');

        return [
            'name' => trim((string) ($issuer['name'] ?? '')) ?: 'Lomé IA School',
            'address' => trim((string) ($issuer['address'] ?? '')),
            // Vide par défaut : jamais d'adresse inventée ; la ligne « E-mail » n'apparaît alors pas sur le reçu.
            'email' => trim((string) ($issuer['email'] ?? '')),
            'phone' => trim((string) ($issuer['phone'] ?? '')),
            'legal' => trim((string) ($issuer['legal'] ?? '')),
            'phones' => Contact::phones(),
        ];
    }

    /**
     * The receipt for the document engine (written here, never by a model): one page, a details table, the amount,
     * the issuer and the contact.
     *
     * @return array<string, mixed>
     */
    public static function structure(Receipt $receipt): array
    {
        $issuer = (array) $receipt->issuer;
        $cancel = $receipt->isCancellation();
        $date = fn ($value) => Emails::date($value);
        $details = array_values(array_filter([
            ['Numéro du reçu', $receipt->number],
            $cancel ? ['Annule le reçu', $receipt->cancelledNumber()] : null,
            [$cancel ? 'Date de l’annulation' : 'Date du paiement', $date($receipt->paid_at)],
            ['Client', $receipt->client_name],
            ['E-mail', $receipt->client_email],
            ['Formule', $receipt->plan_name],
            $receipt->period_start ? ['Période couverte', 'du '.$date($receipt->period_start).' au '.$date($receipt->period_end)] : null,
            ['Moyen de paiement', $receipt->method ?: 'Mobile Money'],
            $receipt->reference ? ['Référence de la transaction', $receipt->reference] : null,
            $cancel && $receipt->reason ? ['Raison', $receipt->reason] : null,
            ['Statut', $cancel ? 'Annulé' : 'Payé'],
        ]));
        $contact = array_values(array_filter([
            $issuer['address'] ?? null,
            // Le téléphone principal est déjà le premier de « phones » (Contact::phones) : jamais deux fois.
            ($issuer['phones'] ?? []) ? 'Téléphones : '.implode(' · ', Phones::list($issuer['phones'])) : null,
            ! ($issuer['phones'] ?? []) && ($issuer['phone'] ?? '') !== '' ? 'Téléphone : '.Phones::unbreakable($issuer['phone']) : null,
            ($issuer['email'] ?? '') !== '' ? 'E-mail : '.$issuer['email'] : null,
            ($issuer['legal'] ?? '') !== '' ? $issuer['legal'] : null,
        ]));

        return [
            'type' => 'document',
            'langue' => 'fr-FR',
            'titre' => ($cancel ? 'Reçu d’annulation ' : 'Reçu ').$receipt->number,
            'sous_titre' => 'Kekeli, un produit de '.($issuer['name'] ?? 'Lomé IA School'),
            'theme' => ['couleur' => '1C1A17'],
            'blocs' => [
                ['type' => 'tableau', 'entetes' => ['Détail', 'Valeur'], 'lignes' => $details],
                ['type' => 'tableau', 'entetes' => ['Description', 'Montant'], 'lignes' => [
                    ['Formule '.$receipt->plan_name.($receipt->period_start ? ', '.max(1, (int) round($receipt->period_start->diffInDays($receipt->period_end))).' jours' : ''), $receipt->amountText()],
                    [$cancel ? 'Total annulé' : 'Total payé', $receipt->amountText()],
                ], 'derniere_ligne_total' => true],
                ['type' => 'titre2', 'texte' => 'Émetteur'],
                ['type' => 'paragraphe', 'texte' => '**'.($issuer['name'] ?? 'Lomé IA School').'**'.($contact ? ' · '.implode(' · ', $contact) : '')],
                ['type' => 'paragraphe', 'texte' => $cancel
                    ? 'Ce reçu annule le reçu '.$receipt->cancelledNumber().'. Pour toute question, contactez-nous aux coordonnées ci-dessus.'
                    : 'Merci pour votre confiance. Ce reçu confirme le paiement de votre abonnement à Kekeli. Pour toute question, contactez-nous aux coordonnées ci-dessus.'],
            ],
        ];
    }
}
