<?php

namespace App\WebApps;

use App\Models\Project;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Web applications (sites with a database): the starting point (« socle ») and the templates of the gallery.
 * They are described once, in runner/src/applications/modeles.json, and built by the Runner in Laravel or Next.js.
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
     * Templates shown in the gallery.
     *
     * @return list<array<string, mixed>>
     */
    public function gallery(): array
    {
        return array_values(array_filter($this->all(), fn ($template) => $template['galerie'] ?? false));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(?string $slug): ?array
    {
        return collect($this->all())->firstWhere('slug', $slug);
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
     * Instructions given to the agent (with the client's message) for the first request of a web application.
     *
     * @param  array<string, mixed>|null  $template
     */
    public function firstInstructions(string $stack, ?array $template, array $answers = []): string
    {
        $config = $stack === 'laravel' ? 'config/application.php (nom et accroche) et le fichier .env.example (APP_NAME)' : 'lib/config.js (nom et accroche) et le fichier .env.example (NEXT_PUBLIC_APP_NAME)';

        if ($template) {
            $lines = [
                '[Consignes de Kekeli] Le client a choisi le modèle « '.$template['nom'].' » (déjà en place et testé). Personnalise-le pour lui :',
                '- nom de l\'établissement : '.($answers['name'] ?? '').(filled($answers['city'] ?? null) ? ' ; ville ou quartier : '.$answers['city'] : '').' ;',
                '- change le nom affiché et la phrase d\'accueil dans '.$config.' ;',
                '- adapte les textes de la page d\'accueil, et les choix proposés dans les listes si le client les donne ;',
                '- garde les comptes, l\'administration, la sécurité et les tests ; les tests doivent passer.',
            ];
            if (filled($answers['changes'] ?? null)) {
                $lines[] = '- changements demandés par le client : '.Str::limit((string) $answers['changes'], 2000);
            }

            return implode("\n", $lines);
        }

        return implode("\n", [
            '[Consignes de Kekeli] Le client veut une application web faite sur mesure, à partir de l\'application de départ déjà en place (comptes, formulaire de demande, administration, tests).',
            '- Transforme le formulaire de départ (« demandes ») pour qu\'il corresponde exactement au besoin du client (nouveaux champs, nouveau nom, nouvelles pages), ou ajoute les tables et les pages nécessaires.',
            '- Change le nom affiché et la phrase d\'accueil dans '.$config.'.',
            '- Garde les comptes, l\'administration et la sécurité ; ajoute les tests des nouvelles fonctions et fais-les passer.',
            '- Si la demande est très grande, construis d\'abord l\'essentiel qui fonctionne, puis dis clairement dans le compte rendu ce qui reste à faire.',
        ]);
    }

    /**
     * @return list<string>
     */
    public function stacks(): array
    {
        return Project::STACKS;
    }
}
