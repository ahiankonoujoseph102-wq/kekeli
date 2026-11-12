<?php

namespace Tests\Feature;

use App\Jobs\SendAgentRun;
use App\Models\Project;
use App\Models\User;
use App\WebApps\NewSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * « Que voulez-vous créer aujourd'hui ? » : une seule zone de saisie, sans formulaire, sur l'accueil et dans
 * « Mes projets ». Kekeli devine le nom, le type de site et la technologie, puis commence tout de suite.
 */
class SitePromptTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_showcase_site_is_created_from_the_field_and_kekeli_starts_right_away(): void
    {
        Bus::fake([SendAgentRun::class]);
        $user = User::factory()->create();

        $response = Volt::actingAs($user)->test('site-prompt')
            ->set('description', '  Un site pour mon restaurant Le Palmier à Lomé, avec le menu et un bouton WhatsApp.  ')
            ->call('build');

        $project = $user->projects()->sole();
        $response->assertHasNoErrors()->assertRedirect(route('projects.show', $project));
        $this->assertSame('Restaurant Le Palmier', $project->name);
        $this->assertSame(Project::KIND_SITE, $project->kind);
        // Le premier répondant (modèle simulé) a appelé « creer_site » : la discussion est devenue un site.
        $this->assertSame('creer_site', $project->agentRuns()->where('purpose', 'question')->sole()->report['tool']);
        $run = $project->agentRuns()->whereNull('purpose')->sole();
        $this->assertSame('Un site pour mon restaurant Le Palmier à Lomé, avec le menu et un bouton WhatsApp.', $run->message->content);
        $this->assertSame(1, $project->messages()->count());
        $this->assertStringContainsString('« Restaurant Le Palmier »', $run->instructions);
        Bus::assertDispatched(SendAgentRun::class);
    }

    public function test_without_a_proper_name_kekeli_gives_a_simple_name_and_does_not_impose_it(): void
    {
        Bus::fake([SendAgentRun::class]);
        $user = User::factory()->create();

        Volt::actingAs($user)->test('site-prompt')
            ->set('description', 'Un site pour mon salon de coiffure avec mes prix et mes photos.')
            ->call('build')
            ->assertHasNoErrors();

        $project = $user->projects()->sole();
        $this->assertSame('Mon salon de coiffure', $project->name);
        $this->assertNull($project->agentRuns()->whereNull('purpose')->sole()->instructions);
    }

    public function test_an_idea_fills_the_field_and_the_field_needs_a_few_words_in_french(): void
    {
        $user = User::factory()->create();

        Volt::actingAs($user)->test('site-prompt')
            ->assertSee('Que voulez-vous créer aujourd’hui ?')
            ->assertSee('Demandez à Kekeli de créer un site web pour…')
            ->call('build')
            ->assertHasErrors(['description' => 'required'])
            ->assertSee('Quelques mots suffisent.')
            ->set('description', 'x')
            ->call('build')
            ->assertHasErrors(['description' => 'min'])
            ->call('useIdea', 0)
            ->assertSet('description', 'Un site web pour mon restaurant à Lomé, avec le menu, les prix en FCFA et un bouton WhatsApp pour réserver une table.');

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_photos_sent_with_the_text_go_to_the_new_site(): void
    {
        Bus::fake([SendAgentRun::class]);
        Storage::fake('local');
        config(['kekeli.backups.disk' => 'local']);
        $user = User::factory()->create();

        Volt::actingAs($user)->test('site-prompt')
            ->set('description', 'Un site pour ma boutique de pagnes, avec mes produits.')
            ->set('photos', [UploadedFile::fake()->image('Pagne bleu.jpg'), UploadedFile::fake()->image('vitrine.png')])
            ->call('build')
            ->assertHasNoErrors();

        $project = $user->projects()->sole();
        $this->assertSame(['pagne-bleu.jpg', 'vitrine.png'], $project->photos()->orderBy('name')->pluck('name')->all());
        // Elles apparaissent dans le premier message de la discussion.
        $this->assertSame([$project->messages()->sole()->id], $project->photos()->pluck('message_id')->unique()->values()->all());
        foreach ($project->photos as $photo) {
            Storage::disk('local')->assertExists($photo->path);
        }
    }

    public function test_without_a_country_the_text_waits_in_the_project_page(): void
    {
        Bus::fake([SendAgentRun::class]);
        $user = User::factory()->create(['country' => null]);

        Volt::actingAs($user)->test('site-prompt')
            ->set('description', 'Un site pour mon salon de coiffure avec mes prix.')
            ->call('build')
            ->assertHasNoErrors();

        $project = $user->projects()->sole();
        $this->assertSame(0, $project->agentRuns()->count());
        Bus::assertNotDispatched(SendAgentRun::class);

        Volt::actingAs($user)->test('projects.show', ['project' => $project])
            ->assertSet('content', 'Un site pour mon salon de coiffure avec mes prix.');
    }

    public function test_a_visitor_keeps_the_text_during_the_registration_then_kekeli_starts(): void
    {
        Bus::fake([SendAgentRun::class]);

        // Sur l'accueil, sans compte : le texte est gardé et la personne va à l'inscription.
        Volt::test('site-prompt', ['main' => true])
            ->set('description', 'Un site pour mon restaurant Chez Ama à Kara, avec le menu.')
            ->call('build')
            ->assertRedirect(route('register'));
        $this->assertSame('Un site pour mon restaurant Chez Ama à Kara, avec le menu.', session(NewSite::PENDING));
        $this->assertDatabaseCount('projects', 0);

        $this->get(route('register'))->assertOk()
            ->assertSee('Votre idée est gardée')
            ->assertSee('Un site pour mon restaurant Chez Ama à Kara, avec le menu.');

        // Après l'inscription, « Mes projets » crée le site et Kekeli commence.
        Volt::test('auth.register')
            ->set('name', 'Ama Essai')
            ->set('email', 'ama@exemple.tg')
            ->set('password', 'motdepasse-solide-2026')
            ->set('password_confirmation', 'motdepasse-solide-2026')
            ->set('terms', true)
            ->call('register')
            ->assertHasNoErrors();

        $user = User::where('email', 'ama@exemple.tg')->sole();
        $response = $this->actingAs($user)->get(route('projects.index'));
        $project = $user->projects()->sole();
        $response->assertRedirect(route('projects.show', $project));
        $this->assertSame('Restaurant Chez Ama', $project->name);
        $this->assertSame('Un site pour mon restaurant Chez Ama à Kara, avec le menu.', $project->agentRuns()->whereNull('purpose')->sole()->message->content);
        $this->assertNull(session(NewSite::PENDING));
        Bus::assertDispatched(SendAgentRun::class);

        // Le texte ne sert qu'une fois.
        $this->actingAs($user)->get(route('projects.index'))->assertOk();
        $this->assertSame(1, $user->projects()->count());
    }

    public function test_the_welcome_page_asks_the_question_in_one_field_without_badge_nor_gallery(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Que voulez-vous créer aujourd’hui ?')
            ->assertSee('data-site-prompt-input', false)
            ->assertDontSee('Version d\'essai en construction')
            ->assertDontSee('modèle')
            ->assertDontSeeText('application');
    }
}
