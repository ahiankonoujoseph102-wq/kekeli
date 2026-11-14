<?php

namespace App\Support;

use Throwable;

/**
 * Comment un client joint Kekeli : l'adresse e-mail et les téléphones de l'émetteur (Administration → Réglages,
 * valeurs de départ dans config/kekeli.php, visibles dans le dépôt public avec l'accord du porteur du projet).
 * Une seule source pour le pied des e-mails, l'adresse de réponse, les reçus, les formules, le paiement, les pages
 * légales et les pages d'erreur. Les pages d'erreur s'affichent même sans base de données : on revient alors aux
 * valeurs de départ.
 */
class Contact
{
    /** @return array<string, mixed> */
    private static function issuer(): array
    {
        try {
            return (array) app(Settings::class)->get('receipt_issuer');
        } catch (Throwable) {
            return (array) config('kekeli.settings.receipt_issuer');
        }
    }

    public static function email(): string
    {
        $email = trim((string) (self::issuer()['email'] ?? ''));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    public static function mainPhone(): string
    {
        return trim((string) (self::issuer()['phone'] ?? ''));
    }

    /**
     * Le téléphone principal d'abord, puis les téléphones de contact, sans doublon (tels qu'ils sont saisis).
     *
     * @return list<string>
     */
    public static function phones(): array
    {
        try {
            $others = (array) app(Settings::class)->get('contact_phones');
        } catch (Throwable) {
            $others = (array) config('kekeli.settings.contact_phones');
        }
        $phones = [];
        foreach ([self::mainPhone(), ...$others] as $phone) {
            $phone = trim((string) $phone);
            $key = preg_replace('/\D+/', '', $phone);
            if ($phone !== '' && $key !== '' && ! isset($phones[$key])) {
                $phones[$key] = $phone;
            }
        }

        return array_values($phones);
    }

    /**
     * Le contact réduit de l'accueil, des pages d'erreur et de la page hors connexion : seulement le numéro
     * principal (le champ « Téléphone » de l'émetteur), ou à défaut le premier téléphone de contact.
     *
     * @return list<string>
     */
    public static function mainPhones(): array
    {
        $main = self::mainPhone();

        return $main !== '' ? [$main] : array_slice(self::phones(), 0, 1);
    }

    /** Lien WhatsApp du numéro principal (chiffres seulement, avec l'indicatif du pays). */
    public static function whatsappLink(): string
    {
        $phone = self::mainPhones()[0] ?? '';
        $digits = preg_replace('/\D+/', '', $phone);

        return $digits !== '' ? 'https://wa.me/'.$digits : '';
    }

    /** Lien « tel: » d'un numéro affiché. */
    public static function telLink(string $phone): string
    {
        return 'tel:'.preg_replace('/[^\d+]+/', '', $phone);
    }

    /** Une phrase courte pour un e-mail ou un message : « Écrivez-nous à … ou appelez le … ». */
    public static function sentence(): string
    {
        $email = self::email();
        $phone = self::phones()[0] ?? '';
        $parts = array_filter([
            $email !== '' ? 'écrivez-nous à '.$email : null,
            $phone !== '' ? 'appelez le '.Phones::unbreakable($phone) : null,
        ]);

        return $parts ? ucfirst(implode(' ou ', $parts)).'.' : '';
    }
}
