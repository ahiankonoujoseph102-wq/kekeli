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
            ->assertSee('Aucun projet pour le moment');
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

    public function test_a_showcase_site_is_created_and_kekeli_starts_right_away(): void
    {
        Bus::fake([SendAgentRun::class]);
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = Volt::test('projects.create')
            ->set('name', '  Site de mon restaurant  ')
            ->set('description', 'Un site pour mon restaurant avec le menu, les horaires et un bouton WhatsApp.')
            ->assertSee('Kekeli a compris')
            ->assertSee('site vitrine')
            ->call('create');

        $project = $user->projects()->sole();
        $response->assertHasNoErrors()->assertRedirect(route('projects.show', $project));

        $this->assertSame('Site de mon restaurant', $project->name);
        $this->assertSame(Project::KIND_SITE, $project->kind);
        $run = $project->agentRuns()->sole();
        $this->assertSame('Un site pour mon restaurant avec le menu, les horaires et un bouton WhatsApp.', $run->message->content);
        $this->assertStringContainsString('« Site de mon restaurant »', $run->instructions);
        Bus::assertDispatched(SendAgentRun::class);
    }

    public function test_the_client_can_correct_what_kekeli_understood(): void
    {
        Bus::fake([SendAgentRun::class]);
        $user = User::factory()->create();
        $this->actingAs($user);

        Volt::test('projects.create')
            ->set('name', 'Club de lecture')
            ->set('description', 'Les membres se connectent avec leur compte pour voir les livres du mois.')
            ->set('kind', 'showcase')
            ->call('create')
            ->assertHasNoErrors();

        $this->assertSame(Project::KIND_SITE, $user->projects()->sole()->kind);

        Volt::test('projects.create')
            ->set('name', 'Atelier couture')
            ->set('description', 'Présentation de mon atelier et de mes modèles.')
            ->set('kind', 'database')
            ->set('stack', 'nextjs')
            ->call('create')
            ->assertHasNoErrors();

        $site = $user->projects()->where('name', 'Atelier couture')->sole();
        $this->assertTrue($site->isWebApp());
        $this->assertSame('nextjs', $site->app_stack);
    }

    public function test_without_a_country_the_description_waits_in_the_project_page(): void
    {
        Bus::fake([SendAgentRun::class]);
        $user = User::factory()->create(['country' => null]);
        $this->actingAs($user);

        Volt::test('projects.create')
            ->set('name', 'Salon Belle')
            ->set('description', 'Un site pour mon salon de coiffure avec mes prix.')
            ->call('create')
            ->assertHasNoErrors();

        $project = $user->projects()->sole();
        $this->assertSame(0, $project->agentRuns()->count());
        Bus::assertNotDispatched(SendAgentRun::class);

        Volt::test('projects.show', ['project' => $project])
            ->assertSet('content', 'Un site pour mon salon de coiffure avec mes prix.');
    }

    public function test_a_site_needs_a_name_and_a_description_and_the_errors_are_in_french(): void
    {
        $this->actingAs(User::factory()->create());

        Volt::test('projects.create')
            ->set('name', '')
            ->set('description', '')
            ->call('create')
            ->assertHasErrors(['name' => 'required', 'description' => 'required'])
            ->assertSee('Le champ nom est obligatoire.');

        Volt::test('projects.create')
            ->set('name', str_repeat('a', 101))
            ->set('description', 'Un site pour mon atelier.')
            ->call('create')
            ->assertHasErrors(['name' => 'max']);

        $this->assertDatabaseCount('projects', 0);
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
        $this->assertSame(0, $project->agentRuns()->count());

        // « Envoyer quand même » : la même demande part vers le site vitrine.
        $page->call('send');
        $this->assertSame(1, $project->agentRuns()->count());
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
        $this->assertSame(0, $project->agentRuns()->count());
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

        $this->assertStringContainsString('config/application.php', $project->agentRuns()->sole()->instructions);
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
            ->assertSee('Pain frais tous les matins.')
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
