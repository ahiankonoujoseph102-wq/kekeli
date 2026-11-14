<?php

namespace Tests\Feature;

use App\Jobs\EraseProject;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * La nouvelle organisation, comme les grands outils (docs/design-etude.md, partie B) : « Projets » pour les sites,
 * applications, agents et automatisations ; « Bibliothèque » pour les documents, présentations, images et vidéos ;
 * « Récents » pour les discussions. Rien n'est perdu : chaque création existante est à sa place.
 */
class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, Project> */
    private function creations(User $user): array
    {
        $all = [];
        foreach ([
            'site' => ['Restaurant Le Palmier', ['kind' => Project::KIND_SITE]],
            'depot' => ['Dépôt boutique', ['kind' => Project::KIND_REPOSITORY]],
            'base' => ['Annuaire des anciens', ['kind' => Project::KIND_WEBAPP]],
            'application' => ['Rendez-vous coiffure', ['kind' => Project::KIND_WEBAPP, 'is_application' => true]],
            'agent' => ['Assistante boutique', ['kind' => Project::KIND_WEBAPP, 'is_agent' => true]],
            'automation' => ['Rapport du matin', ['kind' => Project::KIND_WEBAPP, 'is_automation' => true]],
            'document' => ['Lettre de motivation', ['kind' => Project::KIND_DOCUMENT, 'document_type' => 'document']],
            'presentation' => ['Pitch investisseurs', ['kind' => Project::KIND_DOCUMENT, 'document_type' => 'presentation']],
            'image' => ['Affiche promotion', ['kind' => Project::KIND_DOCUMENT, 'document_type' => 'image']],
            'video' => ['Publicité pagnes', ['kind' => Project::KIND_DOCUMENT, 'document_type' => 'video']],
            'chat' => ['Prix du ciment', ['kind' => Project::KIND_CHAT]],
        ] as $key => [$name, $attributes]) {
            $project = $user->projects()->make(['name' => $name]);
            $project->forceFill($attributes)->save();
            $all[$key] = $project;
        }

        return $all;
    }

    public function test_every_existing_creation_is_in_its_place(): void
    {
        $user = User::factory()->create();
        $this->creations($user);

        $projects = $this->actingAs($user)->get(route('projects.all'))->assertOk();
        foreach (['Restaurant Le Palmier', 'Dépôt boutique', 'Annuaire des anciens', 'Rendez-vous coiffure', 'Assistante boutique', 'Rapport du matin'] as $name) {
            $projects->assertSee($name);
        }
        // (La fenêtre « Rechercher » de chaque page montre les dernières créations : la liste est vérifiée seule.)
        Volt::actingAs($user)->test('projects.all')->assertDontSee('Lettre de motivation')->assertDontSee('Pitch investisseurs')->assertDontSee('Prix du ciment');

        $library = $this->actingAs($user)->get(route('library'))->assertOk();
        foreach (['Lettre de motivation', 'Pitch investisseurs', 'Affiche promotion', 'Publicité pagnes'] as $name) {
            $library->assertSee($name);
        }
        Volt::actingAs($user)->test('library')->assertDontSee('Restaurant Le Palmier')->assertDontSee('Prix du ciment');

        // La discussion est dans « Récents », sur chaque page.
        $library->assertSee('Prix du ciment');

        // Chaque création se compte une seule fois, dans un seul endroit (sauf les discussions, seulement dans « Récents »).
        $this->assertSame(
            $user->projects()->count(),
            $user->projects()->inProjects()->count() + $user->projects()->inLibrary()->count() + $user->projects()->ofType('chat')->count()
        );
    }

    public function test_the_filters_show_a_fade_on_the_edge_when_they_scroll(): void
    {
        $user = User::factory()->create();
        $this->creations($user);

        // Sur téléphone, les filtres défilent sur le côté : le fondu (classe « kekeli-defile ») n'apparaît que du côté
        // où il reste des choix cachés, d'après la position de la rangée.
        foreach (['projects.all' => 'data-projects-filters', 'library' => 'data-library-filters'] as $route => $filters) {
            $html = $this->actingAs($user)->get(route($route))->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/<div class="kekeli-defile[^"]*"[^\n]*x-bind:data-suite="suite"[^\n]*'.$filters.'/', $html);
        }
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('.kekeli-defile[data-suite]', $css);
        $this->assertStringContainsString('.kekeli-defile[data-debut]', $css);
    }

    public function test_projects_search_and_sort(): void
    {
        $user = User::factory()->create();
        $old = $user->projects()->create(['name' => 'Boulangerie Sika']);
        $old->forceFill(['updated_at' => now()->subDays(3)])->saveQuietly();
        $user->projects()->create(['name' => 'Agence Ama Voyages']);

        $page = Volt::actingAs($user)->test('projects.all')
            ->assertSeeInOrder(['Agence Ama Voyages', 'Boulangerie Sika']);
        $page->set('sort', 'old')->assertSeeInOrder(['Boulangerie Sika', 'Agence Ama Voyages']);
        $page->set('sort', 'name')->assertSeeInOrder(['Agence Ama Voyages', 'Boulangerie Sika']);
        $page->set('query', 'sika')->assertSee('Boulangerie Sika')->assertDontSee('Agence Ama Voyages');
        $page->set('query', '100%')->assertSee('Aucun projet ne correspond.');
        $page->call('setType', 'nimporte-quoi')->assertSet('type', 'all');
    }

    public function test_empty_pages_say_what_to_do(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('projects.all'))->assertOk()
            ->assertSee('data-projects-empty', false)->assertSee('Aucun projet pour le moment')->assertSee('Nouvelle discussion');
        $this->actingAs($user)->get(route('library'))->assertOk()
            ->assertSee('data-library-empty', false)->assertSee('Votre bibliothèque est vide');
    }

    public function test_library_shows_the_preview_and_downloads_the_main_format(): void
    {
        $user = User::factory()->create();
        $all = $this->creations($user);
        $all['document']->documentVersions()->create(['number' => 1, 'title' => 'Lettre', 'files' => ['docx' => 'document.docx', 'pdf' => 'document.pdf'], 'previews' => ['apercu-1.jpg'], 'pages' => 1]);
        $all['document']->documentVersions()->create(['number' => 2, 'title' => 'Lettre', 'files' => ['docx' => 'document.docx', 'pdf' => 'document.pdf'], 'previews' => ['apercu-1.jpg'], 'pages' => 1]);
        $all['video']->documentVersions()->create(['number' => 1, 'title' => 'Publicité', 'files' => ['mp4' => 'video.mp4', 'srt' => 'sous-titres.srt'], 'previews' => ['apercu-1.jpg']]);

        $html = Volt::actingAs($user)->test('library')->html();

        // La dernière version : aperçu de sa première page, téléchargement direct du Word, le PDF dans « ⋯ ».
        $this->assertStringContainsString(route('projects.versions.page', [$all['document'], 2, 1]), $html);
        $this->assertStringContainsString(route('projects.versions.download', [$all['document'], 2, 'docx']), $html);
        $this->assertStringContainsString(route('projects.versions.download', [$all['document'], 2, 'pdf']), $html);
        $this->assertStringContainsString(route('projects.versions.download', [$all['video'], 1, 'mp4']), $html);
        $this->assertStringContainsString('data-library-download="mp4"', $html);
        // Une création encore sans fichier reste visible, sans bouton de téléchargement.
        $this->assertStringContainsString('Pitch investisseurs', $html);
    }

    public function test_rename_and_delete_from_projects_and_library(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $all = $this->creations($user);

        Volt::actingAs($user)->test('projects.all')
            ->call('startRenaming', $all['site']->id)->assertSet('newName', 'Restaurant Le Palmier')
            ->set('newName', '  Le Palmier  ')->call('renameProject')->assertDispatched('projet-renomme')
            ->assertSee('Le Palmier');
        $this->assertSame('Le Palmier', $all['site']->fresh()->name);

        Volt::actingAs($user)->test('library')
            ->call('confirmDeletion', $all['image']->id)->call('deleteProject')
            ->assertDontSee('Affiche promotion');
        $this->assertNull(Project::find($all['image']->id));
        Queue::assertPushed(EraseProject::class);
    }

    public function test_never_the_creations_of_another_client(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $theirs = $this->creations($other);

        $this->actingAs($user)->get(route('projects.all'))->assertOk()->assertDontSee('Restaurant Le Palmier');
        $this->actingAs($user)->get(route('library'))->assertOk()->assertDontSee('Lettre de motivation');

        Volt::actingAs($user)->test('library')->call('confirmDeletion', $theirs['document']->id)->assertNotFound();
        Volt::actingAs($user)->test('projects.all')->call('startRenaming', $theirs['site']->id)->assertNotFound();
        $this->assertModelExists($theirs['document']);
    }

    public function test_the_pages_need_an_account(): void
    {
        $this->get(route('projects.all'))->assertRedirect(route('login'));
        $this->get(route('library'))->assertRedirect(route('login'));
    }
}
