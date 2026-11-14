<?php

namespace App\Support;

/**
 * Les couleurs du système de design de Kekeli pour ce qui ne peut pas lire le CSS : les e-mails (styles écrits dans
 * chaque balise) et le reçu PDF. La source est resources/css/app.css (jetons « --k-… ») ; tests/Feature/DesignTest
 * vérifie que ces valeurs sont exactement les mêmes. On change une couleur aux deux endroits, jamais ailleurs.
 */
class Design
{
    /** Mode clair (bloc « :root » de app.css). */
    public const LIGHT = [
        'fond' => '#faf8f4',
        'surface' => '#ffffff',
        'surface-douce' => '#f3f0ea',
        'bord' => '#e7e2d9',
        'bord-fort' => '#d6d0c4',
        'texte' => '#1c1a17',
        'texte-doux' => '#6b655c',
        'accent' => '#e3a008',
        'accent-texte' => '#9a5b06',
        'accent-fond' => '#fbf5e4',
        'bouton' => '#1c1a17',
        'bouton-texte' => '#ffffff',
    ];

    /** Mode sombre (bloc « .dark » de app.css). */
    public const DARK = [
        'fond' => '#171513',
        'surface' => '#201d1a',
        'surface-douce' => '#262320',
        'bord' => '#34302b',
        'bord-fort' => '#3f3b35',
        'texte' => '#f3efe8',
        'texte-doux' => '#a8a196',
        'accent' => '#f5b83d',
        'accent-texte' => '#f5b83d',
        'accent-fond' => '#2c2413',
        'bouton' => '#f3f0ea',
        'bouton-texte' => '#1c1a17',
    ];

    /** Police des e-mails : Inter si la messagerie l'a, sinon les polices du système. */
    public const EMAIL_FONT = "Inter,-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";

    public static function light(string $token): string
    {
        return self::LIGHT[$token];
    }

    public static function dark(string $token): string
    {
        return self::DARK[$token];
    }

    /** Une couleur sans « # », pour le moteur de documents (reçu PDF). */
    public static function hex(string $token): string
    {
        return strtoupper(ltrim(self::LIGHT[$token], '#'));
    }
}
