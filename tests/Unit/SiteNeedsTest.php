<?php

namespace Tests\Unit;

use App\WebApps\SiteNeeds;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SiteNeedsTest extends TestCase
{
    /**
     * @return array<string, array{string, bool}>
     */
    public static function descriptions(): array
    {
        return [
            'restaurant vitrine' => ['Un site pour mon restaurant à Lomé avec le menu, les horaires et un bouton WhatsApp.', false],
            'réservation par WhatsApp' => ['Site de mon hôtel avec les chambres, les prix et les réservations par WhatsApp.', false],
            'salon de coiffure' => ['Présenter mon salon de coiffure, mes prix et mes photos.', false],
            'association' => ['Le site de notre association : nos activités, les membres du bureau et nos partenaires.', false],
            'vitrine demandée' => ['Un site vitrine pour mon école avec les inscriptions et les comptes rendus des réunions.', false],
            'inscriptions avec validation' => ['Les parents inscrivent leurs enfants en ligne, l’école accepte ou refuse chaque inscription et voit la liste par classe.', true],
            'comptes clients' => ['Une boutique où les clients créent un compte et suivent leurs commandes.', true],
            'administration des commandes' => ['Les commandes sont enregistrées et je les gère dans un tableau de bord.', true],
            'base de données demandée' => ['Un annuaire des artisans de Kara avec une base de données.', true],
            'connexion' => ['Mes élèves se connectent pour voir leurs notes.', true],
        ];
    }

    #[DataProvider('descriptions')]
    public function test_kekeli_understands_whether_a_site_needs_a_database(string $description, bool $database): void
    {
        $this->assertSame($database, app(SiteNeeds::class)->detect($description)['database'], $description);
    }

    public function test_the_summary_names_what_kekeli_understood(): void
    {
        $needs = app(SiteNeeds::class);

        $detection = $needs->detect('Les clients créent un compte, et l’administrateur valide les commandes enregistrées.');
        $this->assertSame(['accounts', 'admin', 'data'], $detection['reasons']);
        $this->assertSame('un site avec base de données, pour les comptes des utilisateurs, un espace administrateur, les informations enregistrées (formulaires, commandes, réservations…).', $needs->summary($detection));
        $this->assertStringStartsWith('un site vitrine', $needs->summary($needs->detect('Mon restaurant et son menu.')));
    }
}
