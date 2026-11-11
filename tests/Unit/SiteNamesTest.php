<?php

namespace Tests\Unit;

use App\WebApps\SiteNames;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SiteNamesTest extends TestCase
{
    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function descriptions(): array
    {
        return [
            'activité suivie du nom' => ['Un site pour mon restaurant Le Palmier à Lomé, avec le menu et un bouton WhatsApp.', 'Restaurant Le Palmier', true],
            'nom entre guillemets' => ['Le site de l\'école « Les Petits Génies » à Kara.', 'École Les Petits Génies', true],
            's\'appelle' => ['Ma boutique de pagnes s\'appelle Chez Ama, je veux vendre en ligne.', 'Boutique Chez Ama', true],
            'association et ville' => ['Site pour l\'association Femmes Solidaires de Kpalimé', 'Association Femmes Solidaires de Kpalimé', true],
            'nom qui contient déjà l\'activité' => ['Mon hôtel « Hôtel La Paix » à Aného', 'Hôtel La Paix', true],
            'activité seule' => ['Un site pour mon restaurant à Lomé avec le menu et WhatsApp.', 'Mon restaurant', false],
            'activité féminine' => ['Une boutique en ligne de pagnes à Lomé.', 'Ma boutique', false],
            'activité qui commence par une voyelle' => ['Un site pour mon école à Lomé', 'Mon école', false],
            'aucune activité' => ['Je veux présenter mes services de couture.', 'Mon site web', false],
            'une ville n\'est pas un nom' => ['Un pressing Lomé, les clients suivent leurs commandes.', 'Mon pressing', false],
        ];
    }

    #[DataProvider('descriptions')]
    public function test_kekeli_guesses_the_name_of_the_site_from_the_description(string $description, string $name, bool $sure): void
    {
        $this->assertSame(['name' => $name, 'sure' => $sure], app(SiteNames::class)->guess($description));
    }
}
