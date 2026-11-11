<?php

namespace App\WebApps;

use App\Agent\RunStarter;
use App\Models\Message;
use App\Models\Project;
use App\Models\ProjectPhoto;
use App\Models\User;
use App\Support\Availability;
use Illuminate\Http\UploadedFile;

/**
 * A new site from what the client typed in « Que voulez-vous créer aujourd'hui ? » (like Bolt.new or Lovable).
 * Kekeli guesses by itself the name, the kind of site (showcase or with a database) and the technology: nothing
 * technical is asked or shown to the client. Then Kekeli starts building right away.
 */
class NewSite
{
    /** What a visitor typed before creating an account: kept in the session during the registration. */
    public const PENDING = 'kekeli.site_en_attente';

    public function __construct(
        private SiteNeeds $needs,
        private SiteNames $names,
        private WebAppTemplates $templates,
        private RunStarter $starter,
        private Availability $availability,
    ) {}

    /**
     * Creates the project and gives the description to Kekeli. When Kekeli cannot start yet (country missing,
     * country not open yet, energy used up), the description waits in the project page: nothing is lost.
     *
     * @param  list<UploadedFile>  $photos  photos sent with the description (the client's own photos come first)
     */
    public function create(User $user, string $description, array $photos = []): Project
    {
        $description = trim($description);
        $guess = $this->names->guess($description);
        $name = $guess['name'];

        if ($this->needs->detect($description)['database']) {
            $stack = $this->templates->suggestStack($description);
            $project = $user->projects()->make(['name' => $name, 'description' => null]);
            $project->forceFill(['kind' => Project::KIND_WEBAPP, 'app_stack' => $stack, 'app_template' => 'socle'])->save();
            $instructions = $this->templates->firstInstructions($stack, ['name' => $guess['sure'] ? $name : null]);
        } else {
            $project = $user->projects()->create(['name' => $name, 'description' => null]);
            $instructions = $guess['sure'] ? $this->templates->showcaseInstructions($name) : null;
        }

        $run = $user->countryDetails() && ! $this->availability->comingSoon($user)
            ? $this->starter->start($project, $description, $instructions)
            : null;

        // Les photos jointes apparaissent dans le premier message (ou partent avec le premier envoi, s'il attend).
        $this->savePhotos($project, $photos, $run?->message);

        if (! $run) {
            session()->flash('brouillon.'.$project->id, $description);
        }

        return $project;
    }

    /**
     * @param  list<UploadedFile>  $photos
     */
    private function savePhotos(Project $project, array $photos, ?Message $message): void
    {
        foreach (array_slice($photos, 0, (int) config('kekeli.client_photos.max_per_project')) as $upload) {
            ProjectPhoto::store($project, $upload, $message);
        }
    }
}
