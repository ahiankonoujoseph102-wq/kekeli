<?php

namespace App\WebApps;

use Illuminate\Support\Str;

/**
 * Kekeli understands from the client's description whether the site needs a database (accounts, saved data,
 * administrator area) or is a showcase site (« site vitrine »). Free and instant: no request to a model.
 * The client can still correct the choice on the « New site » page before Kekeli starts.
 */
class SiteNeeds
{
    /**
     * Words of the description (lowercase, without accents) that point to a need, with the reason shown to the client.
     *
     * @var array<string, list<string>>
     */
    private const SIGNS = [
        'accounts' => [
            'compte', 'comptes', 'connexion', 'se connecter', 'connecter', 'connecte', 'connectent', 'inscrivent', 'mot de passe', 'mots de passe', 's\'inscrire',
            'inscrire', 'inscription', 'inscriptions', 'espace client', 'espace membre', 'espace membres', 'espace eleve',
            'espace parent', 'espace parents', 'espace personnel', 'mon espace', 'login', 'utilisateurs', 'abonnes',
        ],
        'admin' => [
            'administrateur', 'administration', 'admin', 'tableau de bord', 'back-office', 'back office', 'gerer', 'gestion',
            'valider', 'accepter', 'refuser', 'accepte ou refuse', 'valide ou refuse', 'acceptent ou refusent', 'moderer', 'statistiques',
        ],
        'data' => [
            'base de donnees', 'enregistrer', 'enregistre', 'enregistrees', 'enregistres', 'sauvegarder', 'suivre', 'suivi',
            'historique', 'stock', 'stocks', 'inventaire', 'commandes', 'reservations', 'rendez-vous', 'demandes',
            'dossiers', 'factures', 'notes des eleves', 'quiz', 'tickets', 'annonces',
        ],
    ];

    /** Words that make the client's wish explicit: these win over every other sign. */
    private const SHOWCASE_WORDS = ['site vitrine', 'simple vitrine', 'sans base de donnees', 'une seule page', 'page de presentation'];

    private const DATABASE_WORDS = ['base de donnees', 'application web', 'plateforme', 'logiciel', 'saas'];

    /**
     * @return array{database: bool, reasons: list<string>}
     *                                                      reasons: keys among « accounts », « admin », « data »
     */
    public function detect(?string $description): array
    {
        $text = ' '.Str::of((string) $description)->lower()->ascii()->replaceMatches('/[^a-z0-9\'\- ]+/', ' ')->squish().' ';

        if ($this->contains($text, self::SHOWCASE_WORDS)) {
            return ['database' => false, 'reasons' => []];
        }

        $reasons = [];
        foreach (self::SIGNS as $reason => $words) {
            if ($this->contains($text, $words)) {
                $reasons[] = $reason;
            }
        }

        // Une seule indication faible (« commandes », « réservations » sur un site de restaurant) ne suffit pas :
        // un site vitrine peut proposer de réserver par WhatsApp. Il faut des comptes, ou deux indications.
        $database = $this->contains($text, self::DATABASE_WORDS)
            || in_array('accounts', $reasons, true)
            || count($reasons) >= 2;

        return ['database' => $database, 'reasons' => $database ? ($reasons ?: ['data']) : []];
    }

    /**
     * Sentence shown to the client: what Kekeli understood.
     */
    public function summary(array $detection): string
    {
        if (! $detection['database']) {
            return __('Showcase site: pages that present your activity, with your photos, your prices and a WhatsApp button.');
        }

        $parts = [
            'accounts' => __('user accounts'),
            'admin' => __('an administrator area'),
            'data' => __('saved information (forms, orders, bookings…)'),
        ];
        $listed = array_values(array_intersect_key($parts, array_flip($detection['reasons'])));

        return __('Site with a database: :needs.', ['needs' => implode(', ', $listed)]);
    }

    /**
     * @param  list<string>  $words
     */
    private function contains(string $text, array $words): bool
    {
        foreach ($words as $word) {
            if (preg_match('/(?<![a-z])'.preg_quote($word, '/').'(?![a-z])/', $text)) {
                return true;
            }
        }

        return false;
    }
}
