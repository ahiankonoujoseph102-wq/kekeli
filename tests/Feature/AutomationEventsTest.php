<?php

namespace Tests\Feature;

use App\Agent\ProjectFiles;
use App\Agent\RunnerClient;
use App\Automations\AutomationTrigger;
use App\Automations\FormWiring;
use App\Jobs\LaunchAutomation;
use App\Jobs\SendAgentRun;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;
use Mockery;
use Tests\TestCase;

/**
 * Automatisations déclenchées par un événement : le déclencheur est lu dans le code écrit par Kekeli, le secret du
 * point d'entrée est créé une fois et jamais affiché, « Lancer maintenant » simule l'événement avec des données
 * d'essai, le journal des déclenchements vient de GitHub Actions, et Kekeli branche le formulaire d'un site du client
 * avec son accord. Tout tourne sur les comptes du client (GitHub, Cloudflare), jamais chez Kekeli.
 */
class AutomationEventsTest extends TestCase
{
    use RefreshDatabase;

    private const EVENT_WORKFLOW = "name: Automatisation\non:\n  repository_dispatch:\n    types: [evenement]\n  workflow_dispatch:\n";

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake([SendAgentRun::class, LaunchAutomation::class]);
        config(['kekeli.runner.secret' => 'secret-de-test-tres-long-pour-les-signatures-0123456789', 'kekeli.runner.url' => 'http://runner.test']);
    }

    private function automation(User $user, array $attributes = []): Project
    {
        $project = $user->projects()->make(['name' => 'Résumé des contacts']);
        $project->forceFill($attributes + ['kind' => Project::KIND_WEBAPP, 'is_automation' => true, 'app_stack' => 'automatisation', 'app_template' => 'socle', 'backup_at' => now()])->save();

        return $project;
    }

    private function filesAre(array $files): void
    {
        $mock = Mockery::mock(ProjectFiles::class);
        $mock->shouldReceive('read')->andReturn($files);
        $this->app->instance(ProjectFiles::class, $mock);
    }

    public function test_the_trigger_is_read_in_the_code_written_by_kekeli(): void
    {
        $event = AutomationTrigger::detect([
            '.github/workflows/automatisation.yml' => self::EVENT_WORKFLOW."  # schedule:\n  #   - cron: '0 7 * * *'\n",
            'point-entree/worker.js' => 'export default {}',
            'evenement-essai.json' => '{"type":"formulaire","donnees":{"nom":"Afi Mensah","message":"Je voudrais un devis"}}',
        ]);
        $this->assertSame([AutomationTrigger::EVENT, null, true], [$event['kind'], $event['cron'], $event['entry']]);
        $this->assertSame(['nom' => 'Afi Mensah', 'message' => 'Je voudrais un devis'], $event['trial']['donnees']);

        $schedule = AutomationTrigger::detect(['.github/workflows/automatisation.yml' => "on:\n  schedule:\n    - cron: '0 7 * * *'\n      timezone: 'Africa/Lome'\n"]);
        $this->assertSame([AutomationTrigger::SCHEDULE, '0 7 * * *', 'Africa/Lome', null], [$schedule['kind'], $schedule['cron'], $schedule['timezone'], $schedule['trial']]);
        $this->assertSame('À heure fixe : chaque jour à 7 h 00.', AutomationTrigger::describe($schedule));

        $polling = AutomationTrigger::detect(['.github/workflows/automatisation.yml' => "on:\n  schedule:\n    - cron: '*/15 * * * *'\njobs:\n  a:\n    steps:\n      - uses: actions/cache@v4\n"]);
        $this->assertSame(AutomationTrigger::POLLING, $polling['kind']);
        $this->assertSame('En vérifiant régulièrement : toutes les 15 minutes.', AutomationTrigger::describe($polling));
    }

    public function test_after_a_work_the_entry_point_secret_is_created_once_and_never_changed(): void
    {
        $project = $this->automation(User::factory()->create());
        $this->filesAre(['.github/workflows/automatisation.yml' => self::EVENT_WORKFLOW, 'point-entree/worker.js' => 'x', 'evenement-essai.json' => '{"donnees":{"nom":"Essai"}}']);

        AutomationTrigger::refresh($project);
        $first = $project->secrets()->where('name', 'ENTREE_SECRET')->sole()->value;
        $this->assertSame(64, strlen($first));
        AutomationTrigger::refresh($project->fresh());
        $this->assertSame($first, $project->secrets()->where('name', 'ENTREE_SECRET')->sole()->value);
        $this->assertSame(AutomationTrigger::EVENT, $project->fresh()->automation_trigger['kind']);

        // Le secret n'est jamais affiché, même dans les Variables secrètes.
        $this->actingAs($project->user)->get(route('projects.show', $project))->assertOk()->assertDontSee($first);
    }

    public function test_run_now_simulates_the_event_with_the_test_data(): void
    {
        $user = User::factory()->create();
        $project = $this->automation($user, ['automation_trigger' => ['kind' => 'evenement', 'cron' => null, 'entry' => true, 'trial' => ['type' => 'formulaire', 'donnees' => ['nom' => 'Afi Mensah', 'telephone' => '+228 90 00 00 00']]]]);

        $this->actingAs($user)->get(route('projects.show', $project))->assertOk()
            ->assertSee('Déclencheur :')
            ->assertSee('Dès que l’événement arrive (par votre point d’entrée).')
            ->assertSee('« Lancer maintenant » simule l’événement avec des données d’essai')
            ->assertSee('Afi Mensah')
            ->assertSee('Brancher l’événement');

        Volt::actingAs($user)->test('projects.show', ['project' => $project])->call('launchNow');
        Http::fake(['runner.test/*' => Http::response(['accepte' => true], 202)]);
        (new LaunchAutomation($project->agentRuns()->sole()))->handle(app(RunnerClient::class));
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/lancer') && $request['evenement_essai'] === true);
    }

    public function test_the_journal_shows_the_github_launches_and_the_trials_without_their_content(): void
    {
        config(['services.github.api_url' => 'https://api.github.com']);
        $user = User::factory()->create(['github_token' => 'ghu_jeton_invente']);
        $project = $this->automation($user, ['repository' => 'ama/resume-contacts', 'automation_trigger' => ['kind' => 'evenement', 'entry' => true]]);
        Http::fake(['api.github.com/repos/ama/resume-contacts/actions/runs*' => Http::response(['workflow_runs' => [
            ['name' => 'Automatisation', 'event' => 'repository_dispatch', 'status' => 'completed', 'conclusion' => 'success', 'created_at' => '2026-11-13T09:02:00Z', 'html_url' => 'https://github.com/ama/resume-contacts/actions/runs/1'],
            ['name' => 'Automatisation', 'event' => 'repository_dispatch', 'status' => 'completed', 'conclusion' => 'failure', 'created_at' => '2026-11-13T08:40:00Z', 'html_url' => 'https://github.com/ama/resume-contacts/actions/runs/2'],
            ['name' => 'Point d\'entrée', 'event' => 'push', 'status' => 'completed', 'conclusion' => 'success', 'created_at' => '2026-11-13T08:00:00Z', 'html_url' => 'https://github.com/ama/resume-contacts/actions/runs/3'],
        ]])]);

        $this->actingAs($user)->get(route('projects.show', $project))->assertOk()
            ->assertSee('Déclenchements')
            ->assertSee('Événement reçu')
            ->assertSee('13 novembre à 09 h 02')
            ->assertSee('Réussi')
            ->assertSee('Échec')
            ->assertSee('https://github.com/ama/resume-contacts/actions/runs/1')
            ->assertDontSee('actions/runs/3');
    }

    public function test_kekeli_plugs_the_form_of_a_showcase_site_with_the_clients_agreement(): void
    {
        $user = User::factory()->create();
        $automation = $this->automation($user, ['automation_trigger' => ['kind' => 'evenement', 'entry' => true]]);
        $site = $user->projects()->create(['name' => 'Ama Pagnes']);
        $site->forceFill(['kind' => Project::KIND_SITE, 'backup_at' => now()])->save();

        Volt::actingAs($user)->test('projects.show', ['project' => $automation])
            ->set('wireSite', $site->id)->set('entryUrl', 'http://127.0.0.1/interne')->call('wireForm')->assertHasErrors('entryUrl')
            ->set('entryUrl', 'https://resume.ama.workers.dev?jeton=secret')->call('wireForm')->assertHasErrors('entryUrl')
            ->set('entryUrl', 'https://resume-contacts.ama.workers.dev')->call('wireForm')->assertHasNoErrors();

        $run = $site->agentRuns()->sole();
        $this->assertStringContainsString('https://resume-contacts.ama.workers.dev', $run->instructions);
        $this->assertStringContainsString('site_web', $run->instructions);
        $this->assertSame('Branche le formulaire de ce site sur mon automatisation « Résumé des contacts ».', $run->message->content);
        Bus::assertDispatched(SendAgentRun::class);
        // L'adresse du site (même avant sa publication) peut appeler le point d'entrée ; aucun secret dans la page.
        $this->assertSame('https://'.$site->pagesName().'.pages.dev', $automation->secrets()->where('name', 'FORMULAIRE_ORIGINES')->sole()->value);
        $this->assertStringNotContainsString(AutomationTrigger::entrySecret($automation), $run->instructions);
        $this->assertSame('Ama Pagnes', $automation->fresh()->automation_trigger['branched'][0]['name']);
    }

    public function test_a_site_with_a_server_signs_its_calls_with_the_secret_copied_in_its_secret_variables(): void
    {
        $user = User::factory()->create();
        $automation = $this->automation($user, ['automation_trigger' => ['kind' => 'evenement', 'entry' => true]]);
        $site = $user->projects()->create(['name' => 'Boutique Ama']);
        $site->forceFill(['kind' => Project::KIND_WEBAPP, 'app_stack' => 'laravel', 'backup_at' => now()])->save();

        app(FormWiring::class)->wire($automation, $site, 'https://commandes.ama.workers.dev/');

        $this->assertSame('https://commandes.ama.workers.dev', $site->secrets()->where('name', 'AUTOMATISATION_ADRESSE')->sole()->value);
        $this->assertSame($automation->secrets()->where('name', 'ENTREE_SECRET')->sole()->value, $site->secrets()->where('name', 'AUTOMATISATION_SECRET')->sole()->value);
        $this->assertStringContainsString('X-Kekeli-Signature', $site->agentRuns()->sole()->instructions);
        $this->assertSame(0, $automation->secrets()->where('name', 'FORMULAIRE_ORIGINES')->count());
    }

    public function test_only_the_clients_own_sites_can_be_plugged(): void
    {
        $user = User::factory()->create();
        $automation = $this->automation($user, ['automation_trigger' => ['kind' => 'evenement', 'entry' => true]]);
        $other = User::factory()->create()->projects()->create(['name' => 'Site d’un autre']);
        $other->forceFill(['kind' => Project::KIND_SITE, 'backup_at' => now()])->save();

        Volt::actingAs($user)->test('projects.show', ['project' => $automation])->set('wireSite', $other->id)->set('entryUrl', 'https://a.b.workers.dev')->call('wireForm')->assertStatus(404);
        $this->assertSame(0, $other->agentRuns()->count());
    }
}
