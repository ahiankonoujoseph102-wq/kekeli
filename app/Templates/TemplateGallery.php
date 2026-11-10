<?php

namespace App\Templates;

use App\Agent\ProjectBackup;
use App\Agent\ProjectFiles;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Phar;
use PharData;
use RuntimeException;

/**
 * Gallery of ready-made site templates (resources/modeles-sites, made by scripts/modeles/fabriquer.mjs).
 * A template becomes a normal project: its files are put in the project's backup, then Kekeli asks the client a few
 * simple questions and personalizes the whole site. The example content (name, number, address, testimonials) must be
 * entirely replaced: publishing is refused while some of it remains.
 */
class TemplateGallery
{
    /** The questions asked to the client, one at a time, in this order. */
    public const QUESTIONS = ['nom', 'ville', 'whatsapp', 'offre', 'couleurs', 'photos'];

    /** Colours the client can choose (the template's own colours by default), shown with __(). */
    public const COLOURS = [
        'modele' => 'Keep the colours of the template',
        'vert' => 'Green',
        'bleu' => 'Blue',
        'orange' => 'Orange',
        'rouge' => 'Red',
        'violet' => 'Purple',
        'rose' => 'Pink',
        'noir-or' => 'Black and gold',
    ];

    public function directory(): string
    {
        return resource_path('modeles-sites');
    }

    /**
     * Every template, in the order of the gallery.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return Cache::store('array')->rememberForever('kekeli.templates', function () {
            $order = (array) config('kekeli.templates');
            $templates = [];

            foreach ($order as $slug) {
                $file = $this->directory().'/'.$slug.'/modele.json';
                if (is_file($file)) {
                    $templates[$slug] = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
                }
            }

            return $templates;
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(?string $slug): ?array
    {
        return $slug === null ? null : ($this->all()[$slug] ?? null);
    }

    public function siteDirectory(string $slug): string
    {
        return $this->directory().'/'.$slug.'/site';
    }

    /**
     * Absolute path of a file of a template's site, or null if it does not exist or is outside the site.
     */
    public function file(string $slug, string $path): ?string
    {
        if (! $this->find($slug)) {
            return null;
        }

        $root = realpath($this->siteDirectory($slug));
        $file = realpath($this->siteDirectory($slug).'/'.ltrim($path === '' ? 'index.html' : $path, '/'));

        return $root && $file && is_file($file) && str_starts_with($file, $root.DIRECTORY_SEPARATOR) ? $file : null;
    }

    /**
     * Create a project for the user from a template: the template's files become the project's first backup, and the
     * first question is asked.
     */
    public function createProject(User $user, string $slug): Project
    {
        $template = $this->find($slug) ?? throw new RuntimeException('Modèle inconnu.');

        return DB::transaction(function () use ($user, $slug, $template) {
            $project = $user->projects()->create([
                'name' => __('My site – :template', ['template' => $template['nom']]),
                'description' => $template['description'],
            ]);
            $project->forceFill(['template' => $slug, 'template_answers' => [], 'template_step' => 0])->save();

            app(ProjectBackup::class)->storePart($project, 1, 1, $this->archive($slug));

            return $project->refresh();
        });
    }

    /**
     * The template's site in an archive « .tar.gz », like the backups made by the Runner.
     */
    public function archive(string $slug): string
    {
        $base = sys_get_temp_dir().'/kekeli-modele-'.bin2hex(random_bytes(6));
        $tar = $base.'.tar';

        try {
            $archive = new PharData($tar);
            $archive->buildFromDirectory($this->siteDirectory($slug));
            $archive->compress(Phar::GZ);
            unset($archive);

            return (string) file_get_contents($tar.'.gz');
        } finally {
            @unlink($tar);
            @unlink($tar.'.gz');
        }
    }

    /**
     * The instructions given to the agent to personalize the template with the client's answers.
     */
    public function instructions(Project $project): string
    {
        $template = $this->find($project->template) ?? [];
        $answers = (array) $project->template_answers;
        $examples = collect($template['exemples'] ?? []);
        $colour = $answers['couleurs'] ?? 'modele';
        $photos = $project->photos()->count();

        $lines = [
            'Ce projet a été créé à partir du modèle « '.($template['nom'] ?? $project->template).' » de la galerie de Kekeli : ses fichiers sont déjà dans le projet (index.html, css/base.css, css/modele.css, images, LICENCES.md). Personnalise TOUT le site avec les informations du client, en gardant la mise en page, le menu et les icônes.',
            '',
            'Règles obligatoires :',
            '1. Remplace absolument tout le contenu d\'exemple, partout (textes, titres, balise title, liens « wa.me » et « tel: », pied de page) : '.$examples->whereIn('genre', ['nom', 'adresse', 'telephone', 'courriel'])->map(fn ($e) => '« '.$e['valeur'].' »')->implode(', ').'. Le numéro WhatsApp du client remplace le numéro d\'exemple, au format international dans les liens (wa.me/228…). S\'il n\'a pas donné d\'adresse électronique, retire la ligne du courriel.',
            '2. Supprime la section des témoignages d\'exemple : n\'invente jamais de témoignage, d\'avis de client, de chiffre, de date de création ni de diplôme. Réécris les textes de présentation simplement, uniquement à partir de ce que le client a donné.',
            '3. Remplace la liste des prix ou des services du modèle par celle du client, sans rien ajouter.',
            '4. Couleurs : '.($colour === 'modele' ? 'garde celles du modèle.' : 'change les variables --couleur-principale, --couleur-principale-foncee et --couleur-accent dans css/modele.css pour des couleurs « '.__(self::COLOURS[$colour] ?? $colour).' » harmonieuses et bien lisibles.'),
            '5. Images : '.($photos > 0 ? 'utilise d\'abord les '.$photos.' photo(s) du client (node /home/user/.kekeli/images.mjs client) à la place des images d\'exemple qui correspondent.' : 'garde les images du modèle (ce sont des illustrations créées, sans texte), sauf si une image ne correspond plus à l\'activité.').' Ne présente jamais une illustration comme une vraie photo des produits du client.',
            '6. Écris en français correct, avec la typographie française.',
        ];

        return implode("\n", $lines);
    }

    /**
     * Example content still in the project's last backup (cached until the next backup).
     *
     * @return list<array{genre: string, valeur: string, fichiers: list<string>}>
     */
    public function remainingExamples(Project $project): array
    {
        $template = $this->find($project->template);
        if (! $template || ! $project->backup_at) {
            return [];
        }

        return Cache::remember('kekeli.exemples.'.$project->id.'.'.$project->backup_at->timestamp, now()->addDay(), function () use ($project, $template) {
            try {
                $files = app(ProjectFiles::class)->read($project);
            } catch (RuntimeException) {
                return [];
            }

            $pages = collect($files)->filter(fn ($content, $path) => (bool) preg_match('/\.(html?|js)$/i', $path))
                ->map(fn ($content) => $this->normalize(html_entity_decode(strip_tags((string) $content).' '.(string) $content, ENT_QUOTES | ENT_HTML5)));

            $remaining = [];
            foreach ($template['exemples'] as $example) {
                $found = $pages->filter(fn ($text) => $this->contains($text, $example))->keys()->values()->all();
                if ($found !== []) {
                    $remaining[] = ['genre' => $example['genre'], 'valeur' => $example['valeur'], 'fichiers' => $found];
                }
            }

            return $remaining;
        });
    }

    /**
     * Simple French description of an example still to replace, for the client.
     *
     * @param  array{genre: string, valeur: string}  $example
     */
    public function describe(array $example): string
    {
        $short = mb_strlen($example['valeur']) > 60 ? mb_substr($example['valeur'], 0, 57).'…' : $example['valeur'];

        return match ($example['genre']) {
            'nom' => __('the example name « :value »', ['value' => $short]),
            'adresse' => __('the example address « :value »', ['value' => $short]),
            'telephone' => __('the example number :value', ['value' => $short]),
            'courriel' => __('the example email address :value', ['value' => $short]),
            'temoignage' => __('an example testimonial (« :value »)', ['value' => $short]),
            default => __('an example presentation text (« :value »)', ['value' => $short]),
        };
    }

    private function contains(string $text, array $example): bool
    {
        if ($example['genre'] === 'telephone') {
            // Le numéro, quelle que soit la façon de l'écrire : +228 90 12 34 56, 22890123456, 90-12-34-56…
            $all = (string) preg_replace('/\D/', '', $example['valeur']);
            $digits = substr($all, -8);
            $country = substr($all, 0, -8);
            $pattern = implode('[^0-9]{0,3}', str_split($digits));

            // Avec ou sans l'indicatif du pays (dans les liens « wa.me/228… » par exemple).
            return (bool) preg_match('/(?<![0-9])(?:\+?'.$country.'[^0-9]{0,3})?'.$pattern.'(?![0-9])/', $text);
        }

        $needle = $this->normalize($example['valeur']);
        if (in_array($example['genre'], ['temoignage', 'presentation'], true)) {
            $needle = mb_substr($needle, 0, 40);
        }

        return $needle !== '' && str_contains($text, $needle);
    }

    private function normalize(string $text): string
    {
        $text = str_replace(["\u{00A0}", "\u{202F}", '’', '‘', '«', '»'], [' ', ' ', "'", "'", '"', '"'], $text);

        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $text)));
    }
}
