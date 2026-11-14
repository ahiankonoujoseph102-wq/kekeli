<?php

namespace App\Support;

/**
 * Numéros de téléphone affichés (reçus, pied des e-mails) : chaque numéro reste sur une seule ligne, sur téléphone
 * comme sur ordinateur. Les espaces deviennent insécables et le trait d'union devient insécable ; on peut toujours
 * passer à la ligne entre deux numéros.
 */
class Phones
{
    public const NBSP = "\u{00A0}";

    public static function unbreakable(string $phone): string
    {
        $phone = trim(preg_replace('/\s+/u', ' ', $phone) ?? $phone);

        return str_replace([' ', '-'], [self::NBSP, "\u{2011}"], $phone);
    }

    /**
     * @param  iterable<mixed>  $phones
     * @return list<string>
     */
    public static function list(iterable $phones): array
    {
        $list = [];
        foreach ($phones as $phone) {
            $phone = trim((string) $phone);
            if ($phone !== '') {
                $list[] = self::unbreakable($phone);
            }
        }

        return $list;
    }
}
