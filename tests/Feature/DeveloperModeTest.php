<?php

namespace Tests\Feature;

use App\Agent\ProjectBackup;
use App\Agent\RunnerSignature;
use App\Github\RepositoryClient;
use App\Jobs\PushRepository;
use App\Jobs\SendAgentRun;
use App\Jobs\WatchRepositoryChecks;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Volt\Volt;
use Tests\TestCase;

class DeveloperModeTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'secret-de-test-tres-long-pour-les-signatures-0123456789';

    private const BASE = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config([
            'kekeli.runner.secret' => self::SECRET,
            'kekeli.runner.url' => 'http://runner.test',
            'kekeli.providers_order' => 'gemini',
            'services.github.app_slug' => 'kekeli-essai',
            'services.github.client_id' => 'numero-application',
            'services.github.client_secret' => 'secret-application',
        ]);
    }

    // Ouvrir un dépôt

    public function test_a_public_repository_opens_in_read_only_without_github(): void
    {
        Http::fake([
            'api.github.com/repos/laravel/laravel' => Http::response(['full_name' => 'laravel/laravel', 'default_branch' => '12.x', 'private' => false]),
            'api.github.com/repos/laravel/laravel/commits/12.x' => Http::response(['sha' => self::BASE]),
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('projects.open-repository'))
            ->assertSee('Ouvrir un dépôt GitHub')
            ->assertSee('Connecter GitHub')
            ->assertSee('Un dépôt public');

        Volt::actingAs($user)->test('projects.open-repository')
            ->set('publicAddress', 'pas une adresse')->call('openPublic')->assertHasErrors('publicAddress')
            ->set('publicAddress', 'https://github.com/laravel/laravel')->call('openPublic')->assertHasNoErrors();

        $project = $user->projects()->sole();
        $this->assertTrue($project->isRepository());
        $this->assertFalse($project->canWriteRepository());
        $this->assertSame('laravel/laravel', $project->repository);
        $this->assertSame('12.x', $project->repository_base_branch);
        $this->assertSame(self::BASE, $project->repository_base_sha);
        $this->assertMatchesRegularExpression('#^kekeli/\d{8}-\d{6}$#', $project->work_branch);
        // Sans clé, aucune requête n'est signée avec une clé GitHub.
        Http::assertSent(fn (Request $request) => ! $request->hasHeader('Authorization'));

        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertSee('laravel/laravel')
            ->assertSee('Lecture seule : Kekeli peut travailler sur ce dépôt dans son ordinateur')
            ->assertDontSee('data-push-repository', false)
            ->assertDontSee('data-publication', false)
            ->assertDontSee('data-open-photos', false);
    }

    public function test_the_client_sees_their_repositories_and_opens_one_with_write_access(): void
    {
        Http::fake([
            'api.github.com/user/repos*' => Http::response([
                ['full_name' => 'ama/boutique', 'private' => true, 'default_branch' => 'main', 'permissions' => ['push' => true], 'description' => 'Ma boutique'],
                ['full_name' => 'ama/ancien', 'private' => false, 'default_branch' => 'main', 'archived' => true],
            ]),
            'api.github.com/repos/ama/boutique' => Http::response(['full_name' => 'ama/boutique', 'default_branch' => 'main', 'private' => true, 'permissions' => ['push' => true]]),
            'api.github.com/repos/ama/boutique/commits/main' => Http::response(['sha' => self::BASE]),
        ]);
        $user = User::factory()->create(['github_login' => 'ama', 'github_token' => 'cle-du-client']);

        Volt::actingAs($user)->test('projects.open-repository')
            ->assertSee('ama/boutique')
            ->assertDontSee('ama/ancien')
            ->call('open', 'ama/boutique');

        $project = $user->projects()->sole();
        $this->assertTrue($project->canWriteRepository());
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.github.com/repos/ama/boutique' && $request->header('Authorization')[0] === 'Bearer cle-du-client');
    }

    // Le Runner reçoit le dépôt, jamais la clé

    public function test_the_runner_receives_the_repository_but_never_the_client_key(): void
    {
        Http::fake([
            'runner.test/*' => Http::response(['accepte' => true], 202),
            'api.github.com/repos/ama/boutique/tarball/*' => Http::response('octets-de-l-archive', 200, ['Content-Type' => 'application/gzip']),
        ]);
        $user = User::factory()->create(['github_token' => 'cle-du-client', 'country' => 'TG']);
        $project = $this->repositoryProject($user);

        Volt::actingAs($user)->test('projects.show', ['project' => $project])->set('content', 'Ajoute une page de contact')->call('send');

        Http::assertSent(fn (Request $request) => $request->url() === 'http://runner.test/taches'
            && $request['depot'] === [
                'depot' => 'ama/boutique',
                'branche_base' => 'main',
                'branche_travail' => 'kekeli/20261110-090000',
                'archive' => route('runner.repository.archive', $project),
                'lecture_seule' => false,
            ]
            && ! str_contains($request->body(), 'cle-du-client'));

        // L'archive n'est donnée qu'au Runner (requête signée), avec la clé du client gardée dans le site.
        $this->get(route('runner.repository.archive', $project))->assertUnauthorized();
        $response = $this->call('GET', route('runner.repository.archive', $project), server: $this->signed(''));
        $response->assertOk();
        $this->assertSame('octets-de-l-archive', $response->getContent());
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/tarball/'.self::BASE) && $request->header('Authorization')[0] === 'Bearer cle-du-client');
    }

    public function test_the_state_of_the_repository_is_kept_after_each_request(): void
    {
        $user = User::factory()->create();
        $project = $this->repositoryProject($user);
        $run = $project->agentRuns()->create(['message_id' => $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'x'])->id, 'status' => AgentRun::STATUS_RUNNING]);

        $this->event($run, ['type' => 'depot_detection', 'detection' => ['nom' => 'Laravel (PHP)', 'tester' => ['php artisan test'], 'instructions' => ['CLAUDE.md']]])->assertOk();
        $this->event($run, ['type' => 'depot', 'branche' => 'kekeli/20261110-090000', 'commits' => ['Ajoute la page de contact'], 'fichiers' => [['etat' => 'ajoute', 'chemin' => 'resources/views/contact.blade.php'], ['etat' => 'pirate', 'chemin' => 'x']], 'nombre_fichiers' => 2])->assertOk();
        $this->event($run, ['type' => 'etape', 'genre' => 'installation', 'cible' => 'composer install (15 s)'])->assertOk();

        $state = $project->fresh()->repository_state;
        $this->assertSame('Laravel (PHP)', $state['detection']['nom']);
        $this->assertSame(['Ajoute la page de contact'], $state['commits']);
        $this->assertSame('modifie', $state['fichiers'][1]['etat']);
        $this->assertSame('Outils installés', $run->steps()->sole()->label());

        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertSee('Projet reconnu : Laravel (PHP).')
            ->assertSee('2 fichiers modifiés par Kekeli')
            ->assertSee('resources/views/contact.blade.php')
            ->assertSee('Envoyer sur GitHub et ouvrir une pull request');
    }

    // Envoi sur GitHub et pull request (faux GitHub)

    public function test_only_changed_files_are_sent_to_kekeli_branch_then_a_pull_request_is_opened(): void
    {
        Queue::fake();
        $user = User::factory()->create(['github_token' => 'cle-du-client']);
        $project = $this->repositoryProject($user);
        $project->forceFill(['repository_state' => ['commits' => ['Ajoute la page de contact', 'Travail de Kekeli'], 'fichiers' => [['etat' => 'ajoute', 'chemin' => 'contact.html']]]])->save();
        $this->backup($project, ['README.md' => "# Boutique\n", 'index.html' => '<h1>Nouveau</h1>', 'contact.html' => '<h1>Contact</h1>', '.github/workflows/tests.yml' => "name: tests\n"]);

        $repo = 'api.github.com/repos/ama/boutique';
        Http::fake([
            "$repo/git/commits/".self::BASE => Http::response(['tree' => ['sha' => 'arbre-depart']]),
            "$repo/git/trees/arbre-depart*" => Http::response(['truncated' => false, 'tree' => [
                ['path' => 'README.md', 'type' => 'blob', 'mode' => '100644', 'sha' => RepositoryClient::blobSha("# Boutique\n")],
                ['path' => 'index.html', 'type' => 'blob', 'mode' => '100644', 'sha' => RepositoryClient::blobSha('<h1>Ancien</h1>')],
                ['path' => 'ancien.html', 'type' => 'blob', 'mode' => '100644', 'sha' => 'quelconque'],
                ['path' => '.github/workflows/tests.yml', 'type' => 'blob', 'mode' => '100644', 'sha' => RepositoryClient::blobSha("name: tests\n")],
                ['path' => '.github', 'type' => 'tree', 'mode' => '040000', 'sha' => 'dossier'],
            ]]),
            "$repo/git/blobs" => Http::sequence()->push(['sha' => 'blob-contact'])->push(['sha' => 'blob-index']),
            "$repo/git/trees" => Http::response(['sha' => 'nouvel-arbre']),
            "$repo/git/ref/heads/*" => Http::response(['message' => 'Not Found'], 404),
            "$repo/git/commits" => Http::response(['sha' => 'nouveau-commit']),
            "$repo/git/refs" => Http::response(['ref' => 'refs/heads/kekeli/20261110-090000']),
            "$repo/pulls?*" => Http::response([]),
            "$repo/pulls" => Http::response(['number' => 7, 'html_url' => 'https://github.com/ama/boutique/pull/7']),
        ]);

        Volt::actingAs($user)->test('projects.show', ['project' => $project])->call('pushRepository');
        $this->assertSame(Project::PUSH_IN_PROGRESS, $project->fresh()->push_status);
        Queue::assertPushed(PushRepository::class);
        app()->call([new PushRepository($project), 'handle']);

        // Deux fichiers changés seulement (index.html modifié, contact.html ajouté) et un fichier supprimé.
        Http::assertSentCount(10);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/git/trees') && $request['base_tree'] === 'arbre-depart'
            && collect($request['tree'])->map(fn ($e) => $e['path'].'='.($e['sha'] ?? 'supprime'))->sort()->values()->all() === ['ancien.html=supprime', 'contact.html=blob-contact', 'index.html=blob-index']);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/git/commits') && $request->method() === 'POST' && $request['parents'] === [self::BASE] && $request['message'] === 'Ajoute la page de contact');
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/git/refs') && $request['ref'] === 'refs/heads/kekeli/20261110-090000' && $request['sha'] === 'nouveau-commit');
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/pulls') && $request->method() === 'POST' && $request['head'] === 'kekeli/20261110-090000' && $request['base'] === 'main' && str_contains($request['body'], 'Kekeli'));
        Http::assertSent(fn (Request $request) => $request->header('Authorization')[0] === 'Bearer cle-du-client');
        // Jamais de fusion sans la demande du client.
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/merge'));

        $project->refresh();
        $this->assertSame(Project::PUSH_DONE, $project->push_status);
        $this->assertSame(7, $project->pr_number);
        $this->assertSame(Project::CI_PENDING, $project->ci_status);
        $this->assertStringContainsString('https://github.com/ama/boutique/pull/7', $project->messages()->pluck('content')->implode(' | '));
        Queue::assertPushed(WatchRepositoryChecks::class, fn ($job) => $job->sha === 'nouveau-commit');
    }

    public function test_nothing_is_sent_when_nothing_changed(): void
    {
        $user = User::factory()->create(['github_token' => 'cle-du-client']);
        $project = $this->repositoryProject($user);
        $this->backup($project, ['README.md' => "# Boutique\n"]);
        $repo = 'api.github.com/repos/ama/boutique';
        Http::fake([
            "$repo/git/commits/".self::BASE => Http::response(['tree' => ['sha' => 'arbre-depart']]),
            "$repo/git/trees/arbre-depart*" => Http::response(['tree' => [['path' => 'README.md', 'type' => 'blob', 'mode' => '100644', 'sha' => RepositoryClient::blobSha("# Boutique\n")]]]),
            "$repo/git/ref/heads/*" => Http::response([], 404),
        ]);

        app()->call([new PushRepository($project), 'handle']);

        $this->assertSame(Project::PUSH_NOTHING, $project->fresh()->push_status);
        Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
    }

    public function test_a_read_only_repository_never_sends_anything(): void
    {
        Queue::fake();
        $user = User::factory()->create(['github_token' => 'cle-du-client']);
        $project = $this->repositoryProject($user, 'read');

        Volt::actingAs($user)->test('projects.show', ['project' => $project])->call('pushRepository');
        Queue::assertNothingPushed();
    }

    // Surveillance de la vérification automatique de GitHub

    public function test_green_checks_are_announced_in_simple_french(): void
    {
        $project = $this->pushedProject();
        Http::fake(['api.github.com/repos/ama/boutique/commits/commit-1/check-runs*' => Http::sequence()
            ->push(['check_runs' => [['name' => 'tests', 'status' => 'in_progress']]])
            ->push(['check_runs' => [['name' => 'tests', 'status' => 'completed', 'conclusion' => 'success'], ['name' => 'pint', 'status' => 'completed', 'conclusion' => 'success']]]),
        ]);

        $job = (new WatchRepositoryChecks($project, 'commit-1'))->withFakeQueueInteractions();
        app()->call([$job, 'handle']);
        $job->assertReleased(WatchRepositoryChecks::WAIT_SECONDS);

        app()->call([(new WatchRepositoryChecks($project, 'commit-1'))->withFakeQueueInteractions(), 'handle']);
        $project->refresh();
        $this->assertSame(Project::CI_SUCCESS, $project->ci_status);
        $this->assertStringContainsString('La vérification automatique de GitHub est verte (2 vérification(s))', $project->messages()->pluck('content')->implode(' | '));
        $this->assertStringContainsString('ne la fusionne que si vous le demandez', $project->messages()->pluck('content')->implode(' | '));
    }

    public function test_red_checks_are_fixed_by_kekeli_then_sent_again_to_the_same_pull_request(): void
    {
        Queue::fake();
        $project = $this->pushedProject();
        Http::fake([
            'api.github.com/repos/ama/boutique/commits/commit-1/check-runs*' => Http::response(['check_runs' => [
                ['id' => 55, 'name' => 'tests', 'status' => 'completed', 'conclusion' => 'failure', 'app' => ['slug' => 'github-actions'], 'output' => ['title' => '1 test a échoué', 'summary' => 'ContactTest']],
            ]]),
            'api.github.com/repos/ama/boutique/actions/jobs/55/logs' => Http::response("2026-11-10T09:00:00.0000000Z FAILED Tests\\Feature\\ContactTest\n2026-11-10T09:00:01.0000000Z Expected status 200, got 500"),
        ]);

        app()->call([(new WatchRepositoryChecks($project, 'commit-1'))->withFakeQueueInteractions(), 'handle']);

        $project->refresh();
        $this->assertSame(Project::CI_FAILURE, $project->ci_status);
        $this->assertSame(1, $project->ci_fix_attempts);
        $run = AgentRun::sole();
        $this->assertSame(AgentRun::PURPOSE_CI_FIX, $run->purpose);
        $this->assertStringContainsString('La vérification automatique de GitHub a échoué (tests)', $run->message->content);
        $this->assertStringContainsString('Expected status 200, got 500', $run->instructions);
        $this->assertStringContainsString('jamais en désactivant un test', $run->instructions);
        Queue::assertPushed(SendAgentRun::class);

        // La correction terminée part dans la même pull request.
        $run->update(['status' => AgentRun::STATUS_RUNNING]);
        $this->event($run, ['type' => 'terminee', 'reponse' => 'J\'ai corrigé la page de contact.'])->assertOk();
        Queue::assertPushed(PushRepository::class, fn ($job) => $job->project->is($project));
    }

    public function test_after_the_maximum_of_fixes_kekeli_asks_the_client(): void
    {
        Queue::fake();
        $project = $this->pushedProject();
        $project->forceFill(['ci_fix_attempts' => 2])->save();
        Http::fake(['api.github.com/repos/ama/boutique/commits/commit-1/check-runs*' => Http::response(['check_runs' => [['name' => 'lint', 'status' => 'completed', 'conclusion' => 'failure']]])]);

        app()->call([(new WatchRepositoryChecks($project, 'commit-1'))->withFakeQueueInteractions(), 'handle']);

        $this->assertSame(0, AgentRun::count());
        $this->assertStringContainsString('Kekeli a déjà essayé de corriger 2 fois', $project->messages()->pluck('content')->implode(' | '));
    }

    // Fusion : seulement à la demande du client

    public function test_merging_only_when_the_client_asks_and_never_while_red(): void
    {
        $project = $this->pushedProject();
        Http::fake(['api.github.com/repos/ama/boutique/pulls/7/merge' => Http::response(['merged' => true])]);

        $project->forceFill(['ci_status' => Project::CI_FAILURE])->save();
        Volt::actingAs($project->user)->test('projects.show', ['project' => $project])->call('mergePullRequest')->assertHasErrors('repository');
        Http::assertNothingSent();

        $project->forceFill(['ci_status' => Project::CI_SUCCESS])->save();
        $this->actingAs($project->user)->get(route('projects.show', $project))->assertSee('Fusionner la pull request')->assertSee('Vérification automatique verte');
        Volt::actingAs($project->user)->test('projects.show', ['project' => $project])->call('mergePullRequest')->assertHasNoErrors();

        Http::assertSent(fn (Request $request) => $request->method() === 'PUT' && str_ends_with($request->url(), '/pulls/7/merge'));
        $this->assertSame('merged', $project->fresh()->pr_status);
        $this->assertStringContainsString('fusionnée dans main, comme vous l\'avez demandé', $project->messages()->pluck('content')->implode(' | '));
    }

    public function test_the_work_is_counted_in_the_client_energy_and_a_fix_waits_when_it_is_used_up(): void
    {
        Queue::fake();
        $project = $this->pushedProject();
        $project->user->forceFill(['energy_window_started_at' => now()])->save();
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Gros travail']);
        $project->agentRuns()->create(['message_id' => $message->id, 'status' => AgentRun::STATUS_COMPLETED])->forceFill(['model_cost_usd' => 5])->save();
        Http::fake(['api.github.com/repos/ama/boutique/commits/commit-1/check-runs*' => Http::response(['check_runs' => [['name' => 'tests', 'status' => 'completed', 'conclusion' => 'failure']]])]);

        app()->call([(new WatchRepositoryChecks($project, 'commit-1'))->withFakeQueueInteractions(), 'handle']);

        $this->assertSame(1, AgentRun::count());
        $this->assertStringContainsString('énergie épuisée', $project->messages()->pluck('content')->implode(' | '));
    }

    private function repositoryProject(User $user, string $access = 'write'): Project
    {
        $project = $user->projects()->create(['name' => 'boutique']);
        $project->forceFill([
            'kind' => Project::KIND_REPOSITORY,
            'repository' => 'ama/boutique',
            'repository_base_branch' => 'main',
            'repository_base_sha' => self::BASE,
            'repository_access' => $access,
            'work_branch' => 'kekeli/20261110-090000',
        ])->save();

        return $project;
    }

    private function pushedProject(): Project
    {
        $project = $this->repositoryProject(User::factory()->create(['github_token' => 'cle-du-client']));
        $project->forceFill(['pushed_sha' => 'commit-1', 'pr_number' => 7, 'pr_url' => 'https://github.com/ama/boutique/pull/7', 'pr_status' => 'open', 'ci_status' => Project::CI_PENDING, 'push_status' => Project::PUSH_DONE, 'backup_at' => now()])->save();

        return $project;
    }

    /**
     * @param  array<string, string>  $files
     */
    private function backup(Project $project, array $files): void
    {
        $folder = sys_get_temp_dir().'/kekeli-test-depot-'.bin2hex(random_bytes(4));
        foreach ($files as $name => $content) {
            @mkdir(dirname($folder.'/'.$name), 0777, true);
            file_put_contents($folder.'/'.$name, $content);
        }
        exec('tar -czf '.escapeshellarg($folder.'.tar.gz').' -C '.escapeshellarg($folder).' .');
        $backups = app(ProjectBackup::class);
        $backups->disk()->put($backups->path($project->id), file_get_contents($folder.'.tar.gz'));
        exec('rm -rf '.escapeshellarg($folder).' '.escapeshellarg($folder.'.tar.gz'));
        $project->forceFill(['backup_at' => now()])->save();
    }

    /**
     * @return array<string, string>
     */
    private function signed(string $body): array
    {
        $server = [];
        foreach (RunnerSignature::headers(self::SECRET, $body) as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $server;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function event(AgentRun $run, array $event): TestResponse
    {
        $body = json_encode(['tache' => $run->id, 'evenement' => $event], JSON_UNESCAPED_UNICODE);

        return $this->call('POST', '/runner/evenements', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', ...$this->signed($body)], content: $body);
    }
}
