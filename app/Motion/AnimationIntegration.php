<?php

namespace App\Motion;

use App\Agent\RunStarter;
use App\Models\AgentRun;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\ProjectAnimation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Kekeli Motion: puts an animation in a site or an application of the client. The link is kept, the Runner places
 * the script of the animation in the project (« animations/<name>.js », or « public/animations » for an application)
 * before each work, and the agent of the site adds two lines of HTML where the animation belongs. The script is made
 * by Kekeli from the checked scene: the agent never writes nor changes it.
 */
class AnimationIntegration
{
    public function __construct(private RunStarter $starter) {}

    /**
     * The sites and applications of the client where an animation can be put, the latest first.
     *
     * @return Collection<int, Project>
     */
    public function sitesFor(User $user): Collection
    {
        return $user->projects()
            ->whereIn('kind', [Project::KIND_SITE, Project::KIND_WEBAPP])
            ->latest('updated_at')
            ->get()
            ->filter(fn (Project $project) => $project->canReceiveAnimation())
            ->values();
    }

    /** The name of the animation in the site: « jardin-au-coucher-du-soleil » (the one given by the Runner). */
    public static function nameOf(DocumentVersion $version): string
    {
        $name = (string) data_get($version->structure, 'nom');

        return preg_match('/^[a-z0-9][a-z0-9-]{0,47}$/', $name) ? $name : (Str::slug((string) $version->title) ?: 'animation');
    }

    /** Where the script lives in the project, and the address the page uses to load it. */
    public static function folderOf(Project $site): string
    {
        return $site->kind === Project::KIND_WEBAPP ? 'public/animations' : 'animations';
    }

    public static function srcOf(Project $site, string $name): string
    {
        return ($site->kind === Project::KIND_WEBAPP ? '/animations/' : 'animations/').$name.'.js';
    }

    /**
     * Links the version to the site and starts the work of the site. Null when the work cannot start (energy used up,
     * or another work already running on the site): nothing is changed then.
     */
    public function integrate(DocumentVersion $version, Project $site): ?AgentRun
    {
        $animation = $version->project;
        if (! $animation->isAnimation() || ! $site->canReceiveAnimation() || $animation->user_id !== $site->user_id || blank(data_get($version->files, 'js'))) {
            return null;
        }
        if ($site->agentRuns()->whereIn('status', AgentRun::ACTIVE_STATUSES)->exists()) {
            return null;
        }

        $name = self::nameOf($version);
        $link = $site->animations()->firstOrNew(['name' => $name]);
        $previous = $link->exists ? $link->document_version_id : null;
        $link->document_version_id = $version->id;
        $link->save();

        $run = $this->starter->start(
            $site,
            (string) __('Add the animation « :title » to my site.', ['title' => $version->title]),
            $this->instructions($site, $name, (string) $version->title),
        );
        if (! $run) {
            // Le travail n'a pas pu commencer : le site reste comme avant.
            $previous ? $link->forceFill(['document_version_id' => $previous])->save() : $link->delete();
        }

        return $run;
    }

    /** What the agent of the site must do (in French, like all its instructions). */
    public function instructions(Project $site, string $name, string $title): string
    {
        $folder = self::folderOf($site);
        $src = self::srcOf($site, $name);
        $html = '<div data-kekeli-motion="'.$name.'" style="max-width:960px;margin:0 auto"></div>'."\n".'<script src="'.$src.'" defer></script>';
        $next = $site->app_stack === 'nextjs'
            ? ' Dans une application Next.js, charge le script avec le composant Script de next/script (strategy="afterInteractive") et garde le div tel quel.'
            : '';

        return <<<TXT
        Kekeli Motion : Kekeli a déposé l'animation « {$title} » dans le projet, fichier « {$folder}/{$name}.js ». C'est un dessin animé en SVG qui bouge tout seul et réagit au toucher.
        Pose-la sur le site à l'endroit qui convient le mieux à la demande du client (par défaut, dans la page d'accueil, juste sous le titre principal), avec exactement ces deux lignes de HTML :
        {$html}
        L'animation prend toute la largeur disponible (960 pixels au plus) et garde ses proportions, sur téléphone comme sur ordinateur.{$next}
        Ne modifie jamais ce fichier JavaScript, ne le recopie pas et n'écris pas d'autre animation : il est fabriqué par Kekeli. Ajoute dans LICENCES.md la ligne « {$folder}/{$name}.js : animation Kekeli Motion (contient du code de line-art-animation, licence MIT) ».
        TXT;
    }

    /**
     * The animations of a project for the Runner, with the signed address of each script.
     *
     * @return list<array{nom: string, dossier: string, url: string}>
     */
    public function forRunner(Project $site): array
    {
        if (! $site->canReceiveAnimation()) {
            return [];
        }

        return $site->animations()->get()->map(fn (ProjectAnimation $animation) => [
            'nom' => $animation->name,
            'dossier' => self::folderOf($site),
            'url' => route('runner.animations.show', ['project' => $site, 'animation' => $animation]),
        ])->values()->all();
    }
}
