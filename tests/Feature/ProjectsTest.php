<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_a_user_can_create_a_project(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = Volt::test('projects.index')
            ->set('name', '  Site de mon école  ')
            ->set('description', 'Un site avec les horaires et les inscriptions.')
            ->call('createProject');

        $project = $user->projects()->sole();

        $response->assertHasNoErrors()->assertRedirect(route('projects.show', $project));

        $this->assertSame('Site de mon école', $project->name);
        $this->assertSame('Un site avec les horaires et les inscriptions.', $project->description);
        $this->assertSame(Project::STATUS_DRAFT, $project->fresh()->status);
    }

    public function test_an_empty_description_is_saved_as_nothing(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Volt::test('projects.index')
            ->set('name', 'Site vitrine')
            ->set('description', '   ')
            ->call('createProject')
            ->assertHasNoErrors();

        $this->assertNull($user->projects()->sole()->description);
    }

    public function test_a_project_needs_a_name_and_the_error_is_in_french(): void
    {
        $this->actingAs(User::factory()->create());

        Volt::test('projects.index')
            ->set('name', '')
            ->call('createProject')
            ->assertHasErrors(['name' => 'required'])
            ->assertSee('Le champ nom est obligatoire.');

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_a_project_name_cannot_exceed_one_hundred_characters(): void
    {
        $this->actingAs(User::factory()->create());

        Volt::test('projects.index')
            ->set('name', str_repeat('a', 101))
            ->call('createProject')
            ->assertHasErrors(['name' => 'max']);
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
