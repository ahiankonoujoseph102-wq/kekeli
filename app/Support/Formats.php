<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Une seule façon d'écrire les dates, les heures et les nombres pour le client (audit unslop, passe 4) : toujours à
 * l'heure de Lomé, la date en toutes lettres (« 4 décembre 2026 », jamais « 04/12/2026 »), l'heure à la française
 * (« 16 h 15 », jamais « 16:15 »), sans abréviation, et des espaces insécables pour que rien ne se coupe en fin de ligne.
 */
class Formats
{
    /** Espace insécable fine, entre les milliers. */
    public const FINE = "\u{202F}";

    /** Espace insécable ordinaire. */
    public const INSECABLE = "\u{00A0}";

    /**
     * « 4 décembre 2026 ».
     */
    public static function date(?CarbonInterface $moment): string
    {
        if (! $moment) {
            return '';
        }

        return str_replace(' ', self::INSECABLE, self::lome($moment)->locale('fr')->translatedFormat('j F Y'));
    }

    /**
     * « 16 h 15 ».
     */
    public static function time(?CarbonInterface $moment): string
    {
        if (! $moment) {
            return '';
        }

        return self::lome($moment)->format('H').self::INSECABLE.'h'.self::INSECABLE.self::lome($moment)->format('i');
    }

    /**
     * « 14 novembre 2026 à 16 h 15 ».
     */
    public static function dateTime(?CarbonInterface $moment): string
    {
        if (! $moment) {
            return '';
        }

        return self::date($moment).' à '.self::time($moment);
    }

    /**
     * Pour les listes serrées : « 14 novembre, 16 h 15 » (l'année seulement si ce n'est pas l'année en cours).
     */
    public static function short(?CarbonInterface $moment): string
    {
        if (! $moment) {
            return '';
        }
        $lome = self::lome($moment)->locale('fr');
        $jour = $lome->year === now(config('kekeli.display_timezone'))->year ? $lome->translatedFormat('j F') : $lome->translatedFormat('j F Y');

        return str_replace(' ', self::INSECABLE, $jour).', '.self::time($moment);
    }

    /**
     * Une durée : « 6 s », puis « 8 min 33 s » au-delà d'une minute.
     */
    public static function duration(int $secondes): string
    {
        $secondes = max(0, $secondes);
        if ($secondes < 60) {
            return $secondes.self::INSECABLE.'s';
        }

        return intdiv($secondes, 60).self::INSECABLE.'min '.($secondes % 60).self::INSECABLE.'s';
    }

    /**
     * Un nombre à la française : « 1 250 », « 0,000014 » ; décimales seulement si on en demande.
     */
    public static function number(float|int $nombre, int $decimales = 0): string
    {
        return number_format((float) $nombre, $decimales, ',', self::FINE);
    }

    private static function lome(CarbonInterface $moment): CarbonInterface
    {
        return $moment->copy()->setTimezone(config('kekeli.display_timezone'));
    }
}
