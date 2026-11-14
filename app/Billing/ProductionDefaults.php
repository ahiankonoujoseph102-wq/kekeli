<?php

namespace App\Billing;

use App\Models\Setting;
use App\Support\Settings;

/**
 * Fills the settings of a new production base (Administration → Paiements and Administration → Réglages), so that
 * the project owner has nothing to type by hand. Decision of the project owner of the 14th of November 2026: these
 * Mobile Money numbers, holders and WhatsApp number may be written in the public repository.
 *
 * Only what is empty or missing is filled: a value already typed in the administration is never changed, and running
 * it again adds nothing twice. Same keys and formats as the administration pages (App\Support\Settings).
 */
class ProductionDefaults
{
    /**
     * Numbers to pay by Mobile Money, all in FCFA. In Togo: always « Mixx by Yas » and « Flooz », never « T-Money ».
     *
     * @var array<string, array{holder: string, methods: list<array{name: string, number: string}>}>
     */
    public const PAYMENT_COUNTRIES = [
        'TG' => ['holder' => 'Ahiankonou Kokou Joseph', 'methods' => [
            ['name' => 'Mixx by Yas', 'number' => '+228 72 24 02 31'],
            ['name' => 'Flooz', 'number' => '+228 79 16 23 90'],
        ]],
        'BF' => ['holder' => 'Anderson Passoule Oyo', 'methods' => [
            ['name' => 'Orange Money', 'number' => '+226 07 27 63 81'],
            ['name' => 'Wave', 'number' => '+226 03 53 21 76'],
        ]],
        'BJ' => ['holder' => 'KOUAGOU Noutcha Adam Fernando', 'methods' => [
            ['name' => 'MTN', 'number' => '+229 01 51 53 39 35'],
            ['name' => 'Moov', 'number' => '+229 01 94 55 59 14'],
            ['name' => 'Celtiis', 'number' => '+229 01 40 28 57 61'],
        ]],
        'CI' => ['holder' => 'OULOUSSEGUEA CHARLES DANHAN', 'methods' => [
            ['name' => 'Orange Money', 'number' => '+225 07 09 51 82 85'],
            ['name' => 'MTN Mobile Money', 'number' => '+225 05 45 53 11 70'],
            ['name' => 'Wave', 'number' => '+225 07 09 51 82 85'],
        ]],
    ];

    /** WhatsApp number for the clients of the other countries. */
    public const PAYMENT_WHATSAPP = '+228 72 24 02 31';

    /** Brevo is shared with Talkami (150 e-mails a day in all): Kekeli keeps 100. */
    public const EMAIL_DAILY_CAP = 100;

    public const OPENAI_DAILY_CAP_USD = 1.0;

    public const CONTACT_PHONES = ['+228 72 24 02 31', '+228 79 16 23 90', '+228 79 74 21 27'];

    /** Only these three fields of the issuer of the receipts: never a legal number nor an invented address. */
    public const RECEIPT_ISSUER = ['name' => 'Kekeli', 'email' => 'kekelilome3@gmail.com', 'phone' => '+228 72 24 02 31'];

    public function __construct(private Settings $settings) {}

    /**
     * Fill what is empty or missing. Returns what was added, in words, for the migration output.
     *
     * @return list<string>
     */
    public function apply(): array
    {
        $added = [];

        $countries = $this->stored('payment_countries');
        $countries = is_array($countries) ? $countries : [];
        $changed = false;
        foreach (self::PAYMENT_COUNTRIES as $code => $place) {
            $current = is_array($countries[$code] ?? null) ? $countries[$code] : [];
            if (trim((string) ($current['holder'] ?? '')) === '') {
                $current['holder'] = $place['holder'];
                $changed = true;
            }
            $methods = array_values(array_filter((array) ($current['methods'] ?? []), 'is_array'));
            foreach ($place['methods'] as $method) {
                $index = collect($methods)->search(fn ($existing) => mb_strtolower(trim((string) ($existing['name'] ?? ''))) === mb_strtolower($method['name']));
                if ($index === false) {
                    $methods[] = $method;
                    $added[] = $code.' : '.$method['name'];
                    $changed = true;
                } elseif (trim((string) ($methods[$index]['number'] ?? '')) === '') {
                    // Un moyen déjà présent mais sans numéro : seul le numéro manquant est ajouté.
                    $methods[$index]['number'] = $method['number'];
                    $added[] = $code.' : '.$method['name'];
                    $changed = true;
                }
            }
            $countries[$code] = ['holder' => $current['holder'], 'methods' => $methods];
        }
        if ($changed) {
            $this->settings->set('payment_countries', $countries);
        }

        if (trim((string) $this->stored('payment_whatsapp')) === '') {
            $this->settings->set('payment_whatsapp', self::PAYMENT_WHATSAPP);
            $added[] = 'WhatsApp';
        }

        foreach (['email_daily_cap' => self::EMAIL_DAILY_CAP, 'openai_daily_cap_usd' => self::OPENAI_DAILY_CAP_USD, 'contact_phones' => self::CONTACT_PHONES] as $key => $value) {
            if ($this->isEmpty($this->stored($key))) {
                $this->settings->set($key, $value);
                $added[] = $key;
            }
        }

        $issuer = $this->stored('receipt_issuer');
        $issuer = is_array($issuer) ? $issuer : [];
        $missing = array_filter(self::RECEIPT_ISSUER, fn ($value, $field) => trim((string) ($issuer[$field] ?? '')) === '', ARRAY_FILTER_USE_BOTH);
        if ($missing !== [] || ! Setting::query()->whereKey('receipt_issuer')->exists()) {
            // Les autres champs (adresse, numéros légaux) restent tels quels, vides s'ils n'ont jamais été saisis.
            $this->settings->set('receipt_issuer', array_merge(['name' => '', 'address' => '', 'email' => '', 'phone' => '', 'legal' => ''], $issuer, $missing));
            $added[] = 'receipt_issuer';
        }

        return $added;
    }

    /**
     * The value saved in the administration (the table « settings »), never the default of config/kekeli.php.
     */
    private function stored(string $key): mixed
    {
        return Setting::query()->whereKey($key)->value('value');
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}
