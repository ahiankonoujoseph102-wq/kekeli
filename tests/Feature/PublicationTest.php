<?php

namespace Tests\Feature;

use App\Agent\RunnerClient;
use App\Agent\RunnerSignature;
use App\Jobs\PublishProject;
use App\Models\AdminAlert;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PublicationTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'secret-de-test-tres-long-pour-les-signatures-0123456789';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'kekeli.runner.secret' => self::SECRET,
            'kekeli.runner.url' => 'http://runner.test',
        ]);
    }

    public function test_the_pages_dev_name_comes_from_the_project_name_and_stays_the_same(): void
    {
        $project = User::factory()->create()->projects()->create(['name' => 'Restaurant « Le Palmier » à Bè']);

        $name = $project->pagesName();
        $this->assertMatchesRegularExpression('/^restaurant-le-palmier-a-be-[a-z0-9]{6}$/', $name);
        $this->assertMatchesRegularExpression('/^[a-z0-9][a-z0-9-]{0,57}$/', $name);

        // Une fois publié, le nom ne change plus, même si le projet est renommé.
        $project->forceFill(['pages_name' => $name])->save();
        $project->update(['name' => 'Autre nom']);
        $this->assertSame($name, $project->fresh()->pagesName());

        $this->assertMatchesRegularExpression('/^site-[a-z0-9]{6}$/', User::factory()->create()->projects()->create(['name' => '!!!'])->pagesName());
    }

    public function test_the_owner_publishes_in_one_tap_once_kekeli_has_built_the_site(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $project = $user->projects()->create(['name' => 'Le Palmier']);

        // Sans sauvegarde (rien de construit), pas de bouton : seulement une explication.
        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertSee('Quand Kekeli aura construit votre site, vous pourrez le publier ici en un seul geste.')
            ->assertDontSee('wire:click="publish"', false);

        $project->forceFill(['backup_at' => now()])->save();
        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertSee('Publier mon site')
            ->assertSee('pages.dev');

        Volt::actingAs($user)->test('projects.show', ['project' => $project])->call('publish');

        $this->assertSame(Project::PUBLISH_IN_PROGRESS, $project->fresh()->publish_status);
        Queue::assertPushed(PublishProject::class, fn (PublishProject $job) => $job->project->is($project));
        $this->actingAs($user)->get(route('projects.show', $project))->assertSee('Publication de votre site en cours…');
    }

    public function test_no_publication_while_kekeli_is_working_and_never_by_someone_else(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $project = $user->projects()->create(['name' => 'Le Palmier']);
        $project->forceFill(['backup_at' => now()])->save();
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Ajoute une page']);
        $project->agentRuns()->create(['message_id' => $message->id, 'status' => AgentRun::STATUS_RUNNING]);

        Volt::actingAs($user)->test('projects.show', ['project' => $project])->call('publish')->assertHasErrors('publish');
        Queue::assertNothingPushed();

        $admin = User::factory()->create(['is_admin' => true]);
        Volt::actingAs($admin)->test('projects.show', ['project' => $project])->call('publish')->assertForbidden();
    }

    public function test_the_publication_puts_the_site_online_and_shows_its_address(): void
    {
        Http::fake(['runner.test/publier' => Http::response(['ok' => true, 'adresse' => 'https://le-palmier-abc123.pages.dev', 'fichiers' => 9], 200)]);
        $user = User::factory()->create();
        $project = $user->projects()->create(['name' => 'Le Palmier']);
        $project->forceFill(['backup_at' => now(), 'publish_status' => Project::PUBLISH_IN_PROGRESS])->save();

        (new PublishProject($project))->handle(app(RunnerClient::class));

        Http::assertSent(function (Request $request) use ($project) {
            return $request->url() === 'http://runner.test/publier'
                && $request['projet'] === $project->id
                && $request['nom'] === $project->pagesName()
                && $request['sauvegarde'] === route('runner.backup.show', $project)
                && RunnerSignature::verify(self::SECRET, $request->header('X-Kekeli-Horodatage')[0], $request->header('X-Kekeli-Signature')[0], $request->body());
        });

        $project->refresh();
        $this->assertSame(Project::PUBLISH_DONE, $project->publish_status);
        $this->assertSame('https://le-palmier-abc123.pages.dev', $project->published_url);
        $this->assertSame($project->pagesName(), $project->pages_name);
        $this->assertNotNull($project->published_at);

        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertSee('Votre site est en ligne :')
            ->assertSee('https://le-palmier-abc123.pages.dev')
            ->assertSee('Publier à nouveau')
            ->assertSee("Si l'adresse ne s'ouvre pas encore, attendez quelques minutes");

        // Plus tard, la phrase sur le délai de mise en ligne disparaît.
        $project->forceFill(['published_at' => now()->subHour()])->save();
        $this->actingAs($user)->get(route('projects.show', $project))->assertDontSee('data-publish-delay', false);
    }

    public function test_a_failed_publication_is_shown_simply_and_alerts_the_administrator(): void
    {
        $user = User::factory()->create();
        $project = $user->projects()->create(['name' => 'Le Palmier']);
        $project->forceFill(['backup_at' => now(), 'publish_status' => Project::PUBLISH_IN_PROGRESS])->save();

        // Le Runner n'a pas de clé Cloudflare.
        Http::fake(['runner.test/publier' => Http::response(['erreur' => 'cloudflare_absent'], 503)]);
        (new PublishProject($project))->handle(app(RunnerClient::class));
        $this->assertSame(Project::PUBLISH_FAILED, $project->fresh()->publish_status);
        $this->assertSame('publish_failed', AdminAlert::latest('id')->value('kind'));
        $this->assertStringContainsString('la clé Cloudflare manque', AdminAlert::latest('id')->value('message'));

        // Une adresse qui n'est pas en pages.dev est refusée.
        Http::fake(['runner.test/publier' => Http::response(['ok' => true, 'adresse' => 'https://exemple.com'], 200)]);
        (new PublishProject($project))->handle(app(RunnerClient::class));
        $this->assertSame(Project::PUBLISH_FAILED, $project->fresh()->publish_status);
        $this->assertNull($project->fresh()->published_url);

        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertSee("Votre site n'a pas pu être publié cette fois-ci. Réessayez dans un moment.")
            ->assertSee('Publier mon site');
    }
}
