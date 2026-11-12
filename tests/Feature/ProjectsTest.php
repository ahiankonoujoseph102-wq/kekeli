<?php

namespace Tests\Feature;

use App\Jobs\SendAgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ProjectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/projets')->assertRedirect('/login');
    }

    public function test_the_old_dashboard_address_leads_to_the_projects(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/dashboard')->assertRedirect('/projets');
    }

    public function test_a_new_user_sees_an_empty_project_list_in_french(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/projets')
            ->assertOk()
            ->assertSee('Mes projets')
            ->assertSee('Que voulez-vous créer aujourd’hui ?')
            ->assertSee('Vos sites et vos discussions apparaîtront ici.')
            ->assertDontSee('Partir d\'un modèle')
            ->assertDontSee('Nouveau site');
    }

    public function test_users_only_see_their_own_projects(): void
    {
        $user = User::factory()->create();
        Project::factory()->for($user)->create(['name' => 'Site de mon restaurant']);
        Project::factory()->create(['name' => 'Projet d’un autre utilisateur']);

        $this->actingAs($user);

        $this->get('/projets')
            ->assertOk()
            ->assertSee('Site de mon restaurant')
            ->assertDontSee('Projet d’un autre utilisateur');
    }

    public function test_a_showcase_site_that_asks_for_accounts_gets_a_suggestion(): void
    {
        Bus::fake([SendAgentRun::class]);
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Boutique Ama', 'description' => 'Ma boutique de pagnes.']);
        $this->actingAs($user);

        $page = Volt::test('projects.show', ['project' => $project])
            ->set('content', 'Ajoute des comptes clients avec connexion et un espace administrateur pour gérer les commandes.')
            ->call('send')
            ->assertSee('Cette demande a besoin d\'un site avec base de données');
        // Le premier répondant a choisi « modifier_site », mais aucun travail ne part avant le choix du client.
        $this->assertSame(0, $project->agentRuns()->whereNull('purpose')->count());

        // « Envoyer quand même » : la même demande part vers le site vitrine, et la proposition disparaît.
        $page->call('sendAnyway')->assertDontSee('Cette demande a besoin d\'un site avec base de données');
        $this->assertSame(1, $project->agentRuns()->whereNull('purpose')->count());
        $this->assertSame(1, $project->messages()->count());
    }

    public function test_the_suggestion_creates_a_new_site_with_a_database(): void
    {
        Bus::fake([SendAgentRun::class]);
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Boutique Ama']);
        $this->actingAs($user);

        Volt::test('projects.show', ['project' => $project])
            ->set('content', 'Ajoute des comptes clients avec connexion pour suivre leurs commandes.')
            ->call('send')
            ->call('createDatabaseSite');

        $site = $user->projects()->where('kind', Project::KIND_WEBAPP)->sole();
        $this->assertSame('Boutique Ama', $site->name);
        $this->assertSame('laravel', $site->app_stack);
        $this->assertStringContainsString('site de départ', $site->agentRuns()->sole()->instructions);
        $this->assertSame(0, $project->agentRuns()->whereNull('purpose')->count());
    }

    public function test_the_first_message_of_a_site_with_a_database_carries_the_instructions(): void
    {
        Bus::fake([SendAgentRun::class]);
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Club Sport']);
        $project->forceFill(['kind' => Project::KIND_WEBAPP, 'app_stack' => 'laravel', 'app_template' => 'socle'])->save();
        $this->actingAs($user);

        Volt::test('projects.show', ['project' => $project])
            ->set('content', 'Les membres réservent un créneau.')
            ->call('send')
            ->assertHasNoErrors();

        $this->assertStringContainsString('config/site.php', $project->agentRuns()->whereNull('purpose')->sole()->instructions);
    }

    public function test_a_user_can_rename_their_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Ancien nom']);
        $this->actingAs($user);

        Volt::test('projects.index')
            ->call('startRenaming', $project->id)
            ->assertSet('newName', 'Ancien nom')
            ->set('newName', 'Nouveau nom')
            ->call('renameProject')
            ->assertHasNoErrors();

        $this->assertSame('Nouveau nom', $project->fresh()->name);
    }

    public function test_a_renamed_project_still_needs_a_name(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Ancien nom']);
        $this->actingAs($user);

        Volt::test('projects.index')
            ->call('startRenaming', $project->id)
            ->set('newName', '')
            ->call('renameProject')
            ->assertHasErrors(['newName' => 'required']);

        $this->assertSame('Ancien nom', $project->fresh()->name);
    }

    public function test_a_user_cannot_rename_the_project_of_someone_else(): void
    {
        $project = Project::factory()->create(['name' => 'Pas à moi']);
        $this->actingAs(User::factory()->create());

        Volt::test('projects.index')
            ->call('startRenaming', $project->id)
            ->assertNotFound();

        $this->assertSame('Pas à moi', $project->fresh()->name);
    }

    public function test_a_user_can_delete_their_project_with_its_conversation(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Message::factory()->for($project)->count(2)->create();
        $this->actingAs($user);

        Volt::test('projects.index')
            ->call('confirmDeletion', $project->id)
            ->call('deleteProject');

        $this->assertModelMissing($project);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_a_user_cannot_delete_the_project_of_someone_else(): void
    {
        $project = Project::factory()->create();
        $this->actingAs(User::factory()->create());

        Volt::test('projects.index')
            ->call('confirmDeletion', $project->id)
            ->assertNotFound();

        $this->assertModelExists($project);
    }

    public function test_a_user_can_open_their_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create([
            'name' => 'Site de la boulangerie',
            'description' => 'Pain frais tous les matins.',
        ]);
        $this->actingAs($user);

        $this->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Site de la boulangerie')
            ->assertSee('Brouillon')
            ->assertSee('La conversation commencera ici');
    }

    public function test_the_project_page_shows_its_messages(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Message::factory()->for($project)->create(['content' => 'Bonjour Kekeli']);
        $this->actingAs($user);

        $this->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Bonjour Kekeli')
            ->assertDontSee('La conversation commencera ici');
    }

    public function test_a_user_cannot_open_the_project_of_someone_else(): void
    {
        $project = Project::factory()->create();
        $this->actingAs(User::factory()->create());

        $this->get(route('projects.show', $project))->assertNotFound();
    }

    public function test_deleting_an_account_deletes_its_projects(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $user->delete();

        $this->assertModelMissing($project);
    }
}
