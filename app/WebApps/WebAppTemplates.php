<?php

namespace App\WebApps;

use App\Models\Project;
use RuntimeException;

/**
 * Sites with a database (« applications » in the code): the starting point (« socle »), in Laravel or Next.js.
 * It is described once, in runner/src/applications/modeles.json, and built by the Runner in Laravel or Next.js.
 */
class WebAppTemplates
{
    /** Words that point to Node and Next.js in a description (otherwise Kekeli suggests Laravel). */
    private const NODE_WORDS = '/\b(node(\.?js)?|next(\.?js)?|vercel|javascript|react|typescript)\b/i';

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $file = base_path('runner/src/applications/modeles.json');
        $data = json_decode((string) @file_get_contents($file), true);

        if (! is_array($data['modeles'] ?? null)) {
            throw new RuntimeException('Les modèles d\'applications web sont introuvables.');
        }

        return $data['modeles'];
    }

    /**
     * Technology suggested by Kekeli when the client does not choose: Next.js if the description speaks of Node,
     * Next.js, Vercel or JavaScript; otherwise Laravel (the simplest to host, also on shared hosting with cPanel).
     */
    public function suggestStack(?string $description): string
    {
        return preg_match(self::NODE_WORDS, (string) $description) ? 'nextjs' : 'laravel';
    }

    /**
     * Instructions given to the agent (with the client's message) for the first request of a site with a database.
     *
     * @param  array{name?: string|null}  $answers  « name » only when the client wrote the name of the site.
     */
    public function firstInstructions(string $stack, array $answers = []): string
    {
        $config = $stack === 'laravel' ? 'config/site.php (nom et accroche) et le fichier .env.example (APP_NAME)' : 'lib/config.js (nom et accroche) et le fichier .env.example (NEXT_PUBLIC_APP_NAME)';

        return implode("\n", [
            '[Consignes de Kekeli] Le client veut un site avec base de données fait sur mesure, à partir du site de départ déjà en place (comptes, formulaire de demande, administration, tests).',
            '- Transforme le formulaire de départ (« demandes ») pour qu\'il corresponde exactement au besoin du client (nouveaux champs, nouveau nom, nouvelles pages), ou ajoute les tables et les pages nécessaires.',
            '- Change le nom affiché'.(filled($answers['name'] ?? null) ? ' (le site s\'appelle exactement « '.$answers['name'].' »)' : '').' et la phrase d\'accueil dans '.$config.', et adapte au besoin du client tous les textes de la page d\'accueil (étapes, boutons) et du menu.',
            '- Garde les comptes, l\'administration et la sécurité ; ajoute les tests des nouvelles fonctions et fais-les passer.',
            '- Si la demande est très grande, construis d\'abord l\'essentiel qui fonctionne, puis dis clairement dans le compte rendu ce qui reste à faire.',
        ]);
    }

    /**
     * Instructions for the first request of a showcase site, when the client wrote the name of the site.
     */
    public function showcaseInstructions(string $name): string
    {
        return '[Consignes de Kekeli] Le site s\'appelle exactement « '.$name.' » : garde ce nom tel quel dans le titre, le logo et les textes.';
    }

    /**
     * @return list<string>
     */
    public function stacks(): array
    {
        return Project::STACKS;
    }
}
