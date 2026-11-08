<?php

namespace Tests\Feature;

use App\Agent\RunnerSignature;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PreviewTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'secret-de-test-tres-long-pour-les-signatures-0123456789';

    private const URL = 'https://4100-iabc123def456.e2b.app';

    protected function setUp(): void
    {
        parent::setUp();

        config(['kekeli.runner.secret' => self::SECRET]);
    }

    public function test_the_runner_sends_the_link_of_the_live_preview(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-08 10:00', 'UTC'));
        $run = $this->agentRun();
        $updatedAt = $run->project->updated_at;

        $this->travel(5)->minutes();
        $this->event($run, ['type' => 'apercu', 'url' => self::URL, 'expire_a' => '2026-11-08T10:35:00Z'])->assertOk();

        $project = $run->project->fresh();
        $this->assertSame(self::URL, $project->preview_url);
        $this->assertTrue($project->preview_expires_at->equalTo(CarbonImmutable::parse('2026-11-08 10:35', 'UTC')));
        $this->assertTrue($project->hasLivePreview());
        $this->assertTrue($project->updated_at->equalTo($updatedAt), 'Le lien de l’aperçu ne change pas la date de modification du projet.');

        // L'heure de mise en veille est repoussée à la fin de la tâche.
        $this->event($run, ['type' => 'apercu', 'url' => self::URL, 'expire_a' => '2026-11-08T11:05:00Z'])->assertOk();
        $this->assertTrue($run->project->fresh()->preview_expires_at->equalTo(CarbonImmutable::parse('2026-11-08 11:05', 'UTC')));
    }

    public function test_only_https_links_to_the_virtual_computers_are_accepted(): void
    {
        $run = $this->agentRun();

        foreach ([
            'http://4100-abc.e2b.app',
            'https://exemple.com',
            'https://e2b.app.exemple.com',
            'https://faux-e2b.app',
            'javascript:alert(1)',
            'https://utilisateur:motdepasse@4100-abc.e2b.app',
            'https://4100-abc.e2b.app/'.str_repeat('a', 300),
            '',
        ] as $url) {
            $this->event($run, ['type' => 'apercu', 'url' => $url, 'expire_a' => now()->addMinutes(30)->toIso8601String()])->assertOk();
            $this->assertNull($run->project->fresh()->preview_url, $url);
        }

        $this->event($run, ['type' => 'apercu', 'url' => 'https://4100-abc.e2b.dev', 'expire_a' => now()->addMinutes(30)->toIso8601String()]);
        $this->assertSame('https://4100-abc.e2b.dev', $run->project->fresh()->preview_url);
    }

    public function test_an_unsigned_preview_event_is_refused(): void
    {
        $run = $this->agentRun();
        $body = json_encode(['tache' => $run->id, 'evenement' => ['type' => 'apercu', 'url' => self::URL]]);

        $this->call('POST', '/runner/evenements', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: $body)
            ->assertUnauthorized();

        $this->assertNull($run->project->fresh()->preview_url);
    }

    public function test_the_project_page_has_a_discussion_tab_and_a_preview_tab(): void
    {
        [$user, $project] = $this->project();

        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Discussion')
            ->assertSee('Aperçu')
            ->assertSee('data-panel="discussion"', false)
            ->assertSee('data-panel="apercu"', false)
            ->assertSee('data-preview="empty"', false)
            ->assertSee('L&#039;aperçu apparaîtra ici', false)
            ->assertDontSee('<iframe', false);
    }

    public function test_the_live_preview_is_shown_in_a_frame(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-08 10:00', 'UTC'));
        [$user, $project] = $this->project();
        $project->forceFill(['preview_url' => self::URL, 'preview_expires_at' => now()->addMinutes(30)])->save();

        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('data-preview="live"', false)
            ->assertSee('<iframe', false)
            ->assertSee('src="'.self::URL.'"', false)
            ->assertSee('sandbox="allow-scripts', false)
            ->assertSee('Aperçu en direct')
            ->assertSee('Ouvrir l&#039;aperçu dans un nouvel onglet', false)
            // 10 h 30 en temps universel, c'est 10 h 30 à Lomé.
            ->assertSee('jusqu&#039;à 10:30 (heure de Lomé)', false);
    }

    public function test_the_page_checks_every_minute_while_the_preview_is_on(): void
    {
        [$user, $project] = $this->project();

        $this->actingAs($user)->get(route('projects.show', $project))->assertDontSee('wire:poll', false);

        $project->forceFill(['preview_url' => self::URL, 'preview_expires_at' => now()->addMinutes(30)])->save();
        $this->actingAs($user)->get(route('projects.show', $project))->assertSee('wire:poll.60s', false);

        // Une fois l'ordinateur éteint, la page arrête de vérifier.
        $this->travel(31)->minutes();
        $this->actingAs($user)->get(route('projects.show', $project))->assertDontSee('wire:poll', false);
    }

    public function test_the_preview_is_paused_once_the_computer_is_turned_off(): void
    {
        [$user, $project] = $this->project();
        $project->forceFill(['preview_url' => self::URL, 'preview_expires_at' => now()->subMinute()])->save();

        $this->assertFalse($project->fresh()->hasLivePreview());

        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('data-preview="asleep"', false)
            ->assertSee('L&#039;aperçu est en pause', false)
            ->assertDontSee('<iframe', false);
    }

    public function test_the_preview_is_being_prepared_while_kekeli_starts(): void
    {
        $run = $this->agentRun(['status' => AgentRun::STATUS_RUNNING]);
        $run->project->forceFill(['preview_url' => self::URL, 'preview_expires_at' => now()->subMinute()])->save();

        Volt::actingAs($run->project->user)->test('projects.show', ['project' => $run->project])
            ->assertSee('data-preview="starting"', false)
            ->assertSee('Kekeli prépare l&#039;aperçu…', false);
    }

    public function test_a_finished_request_offers_to_see_the_preview(): void
    {
        $run = $this->agentRun(['status' => AgentRun::STATUS_COMPLETED]);
        $run->project->forceFill(['preview_url' => self::URL, 'preview_expires_at' => now()->addMinutes(20)])->save();

        Volt::actingAs($run->project->user)->test('projects.show', ['project' => $run->project])
            ->assertSee('data-show-preview', false)
            ->assertSee('Voir l&#039;aperçu', false);
    }

    public function test_nobody_else_can_see_the_preview_of_a_project(): void
    {
        [, $project] = $this->project();
        $project->forceFill(['preview_url' => self::URL, 'preview_expires_at' => now()->addMinutes(30)])->save();

        $this->actingAs(User::factory()->create())->get(route('projects.show', $project))->assertNotFound();
    }

    /**
     * @return array{0: User, 1: Project}
     */
    private function project(): array
    {
        $user = User::factory()->create();

        return [$user, Project::factory()->for($user)->create()];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function agentRun(array $attributes = []): AgentRun
    {
        [, $project] = $this->project();
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Crée une page d’accueil']);

        return tap($project->agentRuns()->create(['message_id' => $message->id]), fn (AgentRun $run) => $run->forceFill($attributes)->save());
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function event(AgentRun $run, array $event): TestResponse
    {
        $body = json_encode(['tache' => $run->id, 'evenement' => $event], JSON_UNESCAPED_UNICODE);
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

        foreach (RunnerSignature::headers(self::SECRET, $body) as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call('POST', '/runner/evenements', server: $server, content: $body);
    }
}
