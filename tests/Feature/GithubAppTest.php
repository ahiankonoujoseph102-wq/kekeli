<?php

namespace Tests\Feature;

use App\Agent\FirstResponder;
use App\Agent\ProjectBackup;
use App\Agent\RunnerClient;
use App\Agent\RunnerSignature;
use App\Github\GithubClient;
use App\Github\GithubFiles;
use App\Jobs\PushProjectToGithub;
use App\Jobs\PushRepository;
use App\Models\AgentRun;
use App\Models\Project;
use App\Models\User;
use App\Support\ProductionCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Illuminate\Testing\TestResponse;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * GitHub, comme Claude Code : la GitHub App de Kekeli (le client choisit ses dépôts), « dépôt d'abord » et « projet
 * d'abord », envoi après chaque travail (pull request par défaut ou branche principale), rien de secret envoyé,
 * secrets du dépôt chiffrés. GitHub est simulé.
 */
class GithubAppTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'secret-de-test-tres-long-pour-les-signatures-0123456789';

    private const REPO = 'ama-mensah/conseils-du-matin';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config([
            'kekeli.runner.secret' => self::SECRET,
            'kekeli.runner.url' => 'http://runner.test',
            'services.github.app_slug' => 'kekeli-essai',
            'services.github.client_id' => 'numero-application',
            'services.github.client_secret' => 'secret-application',
        ]);
    }

    public function test_linking_github_installs_the_app_keeps_the_tokens_encrypted_and_creates_the_private_repository(): void
    {
        Bus::fake([PushProjectToGithub::class]);
        $user = User::factory()->create();
        $project = $this->project($user);

        // Première fois : la page d'installation de la GitHub App (le client y choisit ses dépôts).
        $redirect = $this->actingAs($user)->get(route('github.connect', ['projet' => $project->id]));
        $this->assertStringStartsWith('https://github.com/apps/kekeli-essai/installations/new?', $redirect->headers->get('Location'));
        parse_str((string) parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $query);

        Http::fake([
            'github.com/login/oauth/access_token' => Http::response(['access_token' => 'ghu_jeton_invente', 'expires_in' => 28800, 'refresh_token' => 'ghr_renouvellement_invente', 'refresh_token_expires_in' => 15897600]),
            'api.github.com/user/installations' => Http::response(['installations' => [['id' => 4242, 'account' => ['login' => 'ama-mensah']]]]),
            'api.github.com/user' => Http::response(['login' => 'ama-mensah']),
        ]);
        $this->actingAs($user)->get(route('github.callback', ['code' => 'code-unique', 'state' => $query['state'], 'installation_id' => 4242, 'setup_action' => 'install']))
            ->assertRedirect(route('projects.show', $project));

        $user->refresh();
        $this->assertSame('ama-mensah', $user->github_login);
        $this->assertSame(4242, $user->github_installation_id);
        $this->assertSame('ghu_jeton_invente', $user->github_token);
        $this->assertStringNotContainsString('ghu_jeton_invente', (string) $user->getRawOriginal('github_token'));
        $this->assertStringNotContainsString('ghr_renouvellement_invente', (string) $user->getRawOriginal('github_refresh_token'));
        $this->assertTrue($user->github_token_expires_at->isAfter(now()->addHours(7)));
        Bus::assertDispatched(PushProjectToGithub::class);

        // La fois suivante : seulement l'accord du client.
        $again = $this->actingAs($user)->get(route('github.connect'));
        $this->assertStringStartsWith('https://github.com/login/oauth/authorize?', $again->headers->get('Location'));
    }

    public function test_a_return_that_kekeli_cannot_check_asks_the_client_to_confirm_before_linking(): void
    {
        // Sur un téléphone, GitHub s'ouvre parfois dans son application : la session de départ est perdue. Plus de refus
        // muet : Kekeli demande de confirmer d'un geste (formulaire protégé), et rien n'est relié ni envoyé avant.
        $user = User::factory()->create();
        Http::fake([
            'github.com/login/oauth/access_token' => Http::response(['access_token' => 'ghu_jeton_invente', 'expires_in' => 28800, 'refresh_token' => 'ghr_invente']),
            'api.github.com/user/installations/4242/repositories*' => Http::response(['repositories' => [['full_name' => 'ama-mensah/boutique'], ['full_name' => 'ama-mensah/site']]]),
            'api.github.com/user/installations' => Http::response(['installations' => [['id' => 4242, 'account' => ['login' => 'ama-mensah']]]]),
            'api.github.com/user' => Http::response(['login' => 'ama-mensah']),
        ]);
        $this->actingAs($user)->get(route('github.callback', ['code' => 'x', 'setup_action' => 'install', 'installation_id' => 4242]))
            ->assertOk()->assertSee('data-github-confirm', false)->assertSee('Relier mon compte GitHub');
        $this->actingAs($user)->get(route('github.callback', ['code' => 'x', 'state' => 'faux']))->assertOk()->assertSee('Terminer la connexion à GitHub');
        $this->assertNull($user->fresh()->github_token);
        Http::assertNothingSent();

        // Le client confirme : le compte est relié, et le message dit combien de dépôts Kekeli peut lire.
        $this->actingAs($user)->post(route('github.confirm'))->assertRedirect(route('projects.index'))
            ->assertSessionHas('github', 'connected')->assertSessionHas('github_repositories', 2);
        $this->assertSame('ama-mensah', $user->fresh()->github_login);

        // Un code trop vieux (plus de 10 minutes) n'est jamais échangé.
        $this->actingAs($user)->get(route('github.callback', ['code' => 'y']));
        $this->travel(11)->minutes();
        $this->actingAs($user)->post(route('github.confirm'))->assertSessionHas('github', 'refused');
    }

    public function test_after_the_connection_the_client_comes_back_to_the_discussion_with_a_clear_message(): void
    {
        $user = User::factory()->create();
        $chat = $user->projects()->create(['name' => 'Discussion', 'kind' => Project::KIND_CHAT]);
        $return = route('projects.show', $chat);
        $redirect = $this->actingAs($user)->get(route('github.connect', ['retour' => $return]));
        parse_str((string) parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $query);
        Http::fake([
            'github.com/login/oauth/access_token' => Http::response(['access_token' => 'ghu_jeton_invente', 'expires_in' => 28800]),
            'api.github.com/user/installations/4242/repositories*' => Http::response(['repositories' => [['full_name' => 'ama-mensah/boutique'], ['full_name' => 'ama-mensah/site'], ['full_name' => 'ama-mensah/agent']]]),
            'api.github.com/user/installations' => Http::response(['installations' => [['id' => 4242, 'account' => ['login' => 'ama-mensah']]]]),
            'api.github.com/user' => Http::response(['login' => 'ama-mensah']),
        ]);

        $this->actingAs($user)->get(route('github.callback', ['code' => 'c', 'state' => $query['state'], 'installation_id' => 4242, 'setup_action' => 'install']))
            ->assertRedirect($return);
        $this->actingAs($user)->get($return)->assertOk()
            ->assertSee('data-github-notice="connected"', false)
            ->assertSee('GitHub est connecté : 3 dépôts accessibles (compte ama-mensah).');

        // Un refus sur GitHub (ou une adresse de retour fausse) : un message clair, jamais une page muette.
        $this->actingAs($user)->get(route('github.connect', ['retour' => $return]));
        $this->actingAs($user)->get(route('github.callback', ['error' => 'access_denied']))->assertRedirect($return);
        $this->actingAs($user)->get($return)->assertSee('La connexion à GitHub n’a pas abouti. Recommencez depuis Kekeli.');
    }

    public function test_an_installation_without_authorisation_goes_on_to_the_authorisation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('github.connect'));
        $next = $this->actingAs($user)->get(route('github.callback', ['installation_id' => 4242, 'setup_action' => 'install']));
        $this->assertStringStartsWith('https://github.com/login/oauth/authorize?', $next->headers->get('Location'));
        $this->assertStringContainsString(urlencode(route('github.callback')), $next->headers->get('Location'));
    }

    public function test_the_discussion_menu_offers_to_connect_github_and_shows_the_linked_account(): void
    {
        config(['kekeli.runner.url' => 'http://runner.test', 'kekeli.runner.secret' => 'secret-de-test']);
        $user = User::factory()->create();
        $chat = $user->projects()->create(['name' => 'Discussion', 'kind' => Project::KIND_CHAT]);
        $this->actingAs($user)->get(route('projects.show', $chat))->assertSee('data-prompt-github="a-relier"', false)->assertSee('Connecter GitHub');
        $linked = $this->linkedUser();
        $chat2 = $linked->projects()->create(['name' => 'Discussion', 'kind' => Project::KIND_CHAT]);
        $this->actingAs($linked)->get(route('projects.show', $chat2))->assertSee('data-prompt-github="relie"', false)->assertSee('GitHub · ama-mensah');
    }

    public function test_the_production_check_gives_the_callback_url_and_spots_a_wrong_slug_or_client_id(): void
    {
        config(['app.url' => 'https://kekeli.laravel.cloud', 'services.github.app_slug' => 'Kekeli Lome', 'services.github.client_id' => '123456']);
        $lines = collect(app(ProductionCheck::class)->run())->keyBy('label');
        $this->assertSame('erreur', $lines['GITHUB_APP_SLUG']['level']);
        $this->assertSame('erreur', $lines['GITHUB_CLIENT_ID']['level']);
        $this->assertStringContainsString('https://kekeli.laravel.cloud/github/retour', $lines['GitHub · Callback URL']['detail']);
        config(['services.github.app_slug' => 'kekeli-lome', 'services.github.client_id' => 'Iv23liInvente']);
        $lines = collect(app(ProductionCheck::class)->run())->keyBy('label');
        $this->assertSame('ok', $lines['GITHUB_APP_SLUG']['level']);
        $this->assertSame('ok', $lines['GITHUB_CLIENT_ID']['level']);
        $this->assertStringNotContainsString('Iv23liInvente', json_encode($lines->all()));
    }

    public function test_an_expired_token_is_renewed_with_the_refresh_token(): void
    {
        $user = $this->linkedUser(['github_token_expires_at' => now()->subMinute()]);
        Http::fake(['github.com/login/oauth/access_token' => Http::response(['access_token' => 'ghu_nouveau', 'expires_in' => 28800, 'refresh_token' => 'ghr_nouveau'])]);

        $this->assertSame('ghu_nouveau', app(GithubClient::class)->token($user));
        $this->assertSame('ghr_nouveau', $user->fresh()->github_refresh_token);
        Http::assertSent(fn (Request $r) => $r['grant_type'] === 'refresh_token' && $r['refresh_token'] === 'ghr_invente');
    }

    public function test_project_first_creates_a_private_repository_and_sends_a_pull_request_without_any_secret(): void
    {
        $user = $this->linkedUser();
        $project = $this->project($user);
        $project->messages()->create(['role' => 'user', 'content' => 'Chaque matin à 7 h, envoie-moi trois conseils du jour par e-mail']);
        $project->secrets()->create(['name' => 'RESEND_API_KEY', 'value' => 're_cle_inventee_pour_le_test_0123456789']);
        $this->fakeGithub();

        app()->call([new PushProjectToGithub($project), 'handle']);

        $project->refresh();
        $this->assertSame(self::REPO, $project->repository);
        $this->assertSame(Project::GITHUB_DONE, $project->github_status);
        $this->assertSame(7, $project->pr_number);
        $this->assertStringStartsWith('kekeli/', $project->work_branch);

        // Dépôt privé, ajouté à l'installation (le client a choisi certains dépôts).
        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.github.com/user/repos' && $r['private'] === true);
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/user/installations/4242/repositories/99'));
        // Aucun .env, aucune base d'essai, aucun fichier avec une clé.
        $sent = collect(Http::recorded())->map(fn ($pair) => $pair[0]);
        $tree = $sent->first(fn (Request $r) => str_ends_with($r->url(), '/git/trees') && $r->method() === 'POST');
        $paths = collect($tree['tree'])->pluck('path')->all();
        $this->assertContains('src/automatisation.js', $paths);
        $this->assertContains('.github/workflows/automatisation.yml', $paths);
        $this->assertContains('.env.example', $paths);
        $this->assertNotContains('.env', $paths);
        $this->assertNotContains('database/essai.sqlite', $paths);
        $this->assertNotContains('config/cle.js', $paths);
        foreach ($sent->filter(fn (Request $r) => str_ends_with($r->url(), '/git/blobs')) as $blob) {
            $this->assertStringNotContainsString('re_cle_inventee', base64_decode($blob['content']));
        }
        // Message de commit clair, en français.
        $commit = $sent->first(fn (Request $r) => str_ends_with($r->url(), '/git/commits') && $r->method() === 'POST');
        $this->assertStringStartsWith('Kekeli : chaque matin à 7 h', $commit['message']);
        // Pull request par défaut, comme Claude Code.
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/pulls') && $r->method() === 'POST' && $r['base'] === 'main');
        // Automatisation : la clé devient un secret chiffré du dépôt (jamais en clair).
        $secret = $sent->first(fn (Request $r) => str_contains($r->url(), '/actions/secrets/RESEND_API_KEY'));
        $this->assertSame('cle-numero-1', $secret['key_id']);
        $this->assertStringNotContainsString('re_cle_inventee', $secret->body());
    }

    public function test_the_secrets_are_sealed_with_the_public_key_of_the_repository(): void
    {
        $pair = sodium_crypto_box_keypair();
        $sealed = GithubClient::seal('valeur-secrete', sodium_crypto_box_publickey($pair));

        $this->assertNotSame('valeur-secrete', $sealed);
        $this->assertSame('valeur-secrete', sodium_crypto_box_seal_open(base64_decode($sealed), $pair));
    }

    public function test_the_direct_mode_sends_on_the_main_branch_without_pull_request(): void
    {
        $user = $this->linkedUser();
        $project = $this->project($user, ['repository' => self::REPO, 'repository_base_branch' => 'main', 'github_push_mode' => Project::PUSH_DIRECT]);
        $this->fakeGithub();

        app()->call([new PushProjectToGithub($project), 'handle']);

        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/git/refs/heads/main'));
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/pulls') && $r->method() === 'POST');
        Http::assertNotSent(fn (Request $r) => $r->url() === 'https://api.github.com/user/repos');
        $this->assertNull($project->fresh()->pr_number);
    }

    public function test_the_client_chooses_the_mode_in_the_project_and_sees_the_github_badge(): void
    {
        $user = $this->linkedUser();
        $project = $this->project($user, ['repository' => self::REPO, 'repository_base_branch' => 'main', 'pr_number' => 7, 'pr_url' => 'https://github.com/'.self::REPO.'/pull/7', 'pr_status' => 'open', 'github_status' => Project::GITHUB_DONE]);

        $this->actingAs($user)->get(route('projects.show', $project))->assertOk()
            ->assertSee('data-github-badge', false)
            ->assertSee(self::REPO)
            ->assertSee('https://github.com/'.self::REPO.'/pull/7', false)
            ->assertSee('Dans une pull request (conseillé)')
            ->assertSee('Directement sur la branche principale');

        Volt::actingAs($user)->test('projects.show', ['project' => $project])->call('setPushMode', 'direct');
        $this->assertSame(Project::PUSH_DIRECT, $project->fresh()->github_push_mode);
        Volt::actingAs($user)->test('projects.show', ['project' => $project])->call('setPushMode', 'nimporte');
        $this->assertSame(Project::PUSH_DIRECT, $project->fresh()->github_push_mode);
    }

    public function test_after_each_successful_work_kekeli_sends_the_changes(): void
    {
        Bus::fake([PushProjectToGithub::class, PushRepository::class]);
        $user = $this->linkedUser();
        $linked = $this->project($user, ['repository' => self::REPO, 'repository_base_branch' => 'main']);
        $run = $linked->agentRuns()->create(['message_id' => $linked->messages()->create(['role' => 'user', 'content' => 'ajoute une étape'])->id, 'status' => AgentRun::STATUS_RUNNING]);
        $this->event($run, ['type' => 'terminee', 'reponse' => 'C\'est fait.'])->assertOk();
        Bus::assertDispatched(PushProjectToGithub::class);

        // Dépôt d'abord, avec le droit d'écrire : la pull request de Kekeli.
        $repository = $user->projects()->make(['name' => 'Boutique']);
        $repository->forceFill(['kind' => Project::KIND_REPOSITORY, 'repository' => 'ama/boutique', 'repository_access' => 'write', 'repository_base_branch' => 'main', 'repository_base_sha' => 'commit-0', 'work_branch' => 'kekeli/1', 'backup_at' => now()])->save();
        $run = $repository->agentRuns()->create(['message_id' => $repository->messages()->create(['role' => 'user', 'content' => 'corrige le bouton'])->id, 'status' => AgentRun::STATUS_RUNNING]);
        $this->event($run, ['type' => 'terminee', 'reponse' => 'Corrigé.'])->assertOk();
        Bus::assertDispatched(PushRepository::class);

        // Une simple question ne pousse rien.
        Bus::fake([PushProjectToGithub::class]);
        $question = $linked->agentRuns()->create(['message_id' => $linked->messages()->create(['role' => 'user', 'content' => 'que fait-elle ?'])->id, 'status' => AgentRun::STATUS_RUNNING, 'purpose' => AgentRun::PURPOSE_QUESTION]);
        $this->event($question, ['type' => 'terminee', 'reponse' => 'Elle envoie un e-mail.'])->assertOk();
        Bus::assertNotDispatched(PushProjectToGithub::class);
    }

    public function test_repository_first_lists_only_the_repositories_chosen_for_kekeli(): void
    {
        $user = $this->linkedUser();
        Http::fake(['api.github.com/user/installations/4242/repositories*' => Http::response(['repositories' => [
            ['full_name' => 'ama-mensah/boutique', 'private' => true, 'default_branch' => 'main', 'permissions' => ['push' => true]],
        ]])]);

        $this->actingAs($user)->get(route('projects.open-repository'))->assertOk()
            ->assertSee('ama-mensah/boutique')
            ->assertSee('data-choose-repositories', false)
            ->assertSee('https://github.com/settings/installations/4242', false);
    }

    public function test_a_question_on_a_linked_repository_reads_the_files_without_changing_anything(): void
    {
        $user = $this->linkedUser();
        $project = $this->project($user, ['repository' => self::REPO, 'repository_base_branch' => 'main']);
        $run = $project->agentRuns()->create(['message_id' => $project->messages()->create(['role' => 'user', 'content' => 'à quelle heure part l\'e-mail ?'])->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
        Http::fake(['runner.test/*' => Http::response(['ok' => true, 'decision' => 'repondre', 'reponse' => 'Chaque jour à 7 h.'])]);

        app(RunnerClient::class)->respond($run, 'site');

        // Le modèle reçoit une adresse signée de lecture seule et aucun outil d'envoi vers GitHub.
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'runner.test') && str_contains((string) $r['lecture'], '/fichier') && $r['lieu'] === 'site');
        // Le premier envoi au Runner (Kekeli lit aussi la liste des dépôts reliés, depuis le 16 novembre 2026).
        $file = collect(Http::recorded())->map(fn ($pair) => $pair[0])->first(fn (Request $r) => str_contains($r->url(), 'runner.test'))['lecture'];
        $this->getJson($file.'&chemin=.github/workflows/automatisation.yml')->assertOk()->assertJsonPath('chemin', '.github/workflows/automatisation.yml');
        $this->getJson($file.'&chemin=.env')->assertNotFound();
        $this->assertSame(['modifier_site'], array_keys(FirstResponder::TOOLS['site']));
    }

    public function test_kekeli_knows_its_real_github_connection_and_reads_a_linked_repository_read_only(): void
    {
        // Pas relié : Kekeli le sait et donne le geste (jamais une réponse générique).
        $alone = User::factory()->create();
        $chat = $alone->projects()->create(['name' => 'Discussion', 'kind' => Project::KIND_CHAT]);
        $run = $chat->agentRuns()->create(['message_id' => $chat->messages()->create(['role' => 'user', 'content' => 'Mon GitHub est connecté ?'])->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
        Http::fake(['runner.test/*' => Http::response(['ok' => true, 'decision' => 'repondre', 'reponse' => 'Pas encore.'])]);
        app(RunnerClient::class)->respond($run, 'discussion');
        Http::assertSent(fn (Request $r) => $r['contexte']['connexions'] === ['github' => ['relie' => false]] && $r['lecture_depots'] === null);

        // Relié : le compte, les dépôts choisis pour Kekeli et une adresse de lecture signée (jamais le jeton).
        $user = $this->linkedUser();
        $chat2 = $user->projects()->create(['name' => 'Discussion', 'kind' => Project::KIND_CHAT]);
        $run2 = $chat2->agentRuns()->create(['message_id' => $chat2->messages()->create(['role' => 'user', 'content' => 'Lis mon dépôt et dis-moi ce que c’est'])->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
        Http::fake([
            'api.github.com/user/installations/4242/repositories*' => Http::response(['repositories' => [['full_name' => 'ama-mensah/boutique-pagnes', 'default_branch' => 'main']]]),
            'api.github.com/repos/ama-mensah/boutique-pagnes/git/trees/*' => Http::response(['tree' => [['path' => 'README.md', 'type' => 'blob'], ['path' => 'routes/web.php', 'type' => 'blob'], ['path' => '.env', 'type' => 'blob']]]),
            'api.github.com/repos/ama-mensah/boutique-pagnes/readme' => Http::response('# Ama Pagnes'.PHP_EOL.'Site Laravel de la boutique.'),
            'api.github.com/repos/ama-mensah/boutique-pagnes/languages' => Http::response(['PHP' => 1200, 'Blade' => 300]),
            'api.github.com/repos/ama-mensah/boutique-pagnes/contents/routes/web.php' => Http::response("<?php\nRoute::view('/', 'accueil');"),
            'api.github.com/repos/ama-mensah/boutique-pagnes' => Http::response(['full_name' => 'ama-mensah/boutique-pagnes', 'default_branch' => 'main', 'description' => 'Boutique de pagnes']),
            'runner.test/*' => Http::response(['ok' => true, 'decision' => 'repondre', 'reponse' => 'C’est le site de votre boutique.']),
        ]);
        app(RunnerClient::class)->respond($run2, 'discussion');
        $sent = collect(Http::recorded())->map(fn ($pair) => $pair[0])->first(fn (Request $r) => str_contains($r->url(), 'runner.test'));
        $this->assertSame(['relie' => true, 'compte' => 'ama-mensah', 'depots' => ['ama-mensah/boutique-pagnes'], 'depots_total' => 1], $sent['contexte']['connexions']['github']);
        $this->assertStringNotContainsString('ghu_jeton_invente', $sent->body());
        $read = $sent['lecture_depots'];

        // Le Runner lit le dépôt : description, langages, README et fichiers, jamais .env ; puis un fichier.
        $this->getJson($read.'&depot=ama-mensah/boutique-pagnes')->assertOk()
            ->assertJsonPath('langages', ['PHP', 'Blade'])
            ->assertJsonPath('fichiers', ['README.md', 'routes/web.php'])
            ->assertJsonPath('lisez_moi', '# Ama Pagnes'.PHP_EOL.'Site Laravel de la boutique.');
        $this->getJson($read.'&depot=ama-mensah/boutique-pagnes&chemin=routes/web.php')->assertOk()->assertJsonPath('chemin', 'routes/web.php');
        $this->getJson($read.'&depot=ama-mensah/boutique-pagnes&chemin=.env')->assertNotFound();
        // Seulement les dépôts choisis pour Kekeli ; jamais sans signature.
        $this->getJson($read.'&depot=quelquun/autre')->assertNotFound()->assertJsonPath('erreur', 'depot_inconnu');
        $this->getJson(route('runner.github.read', ['user' => $user]).'?depot=ama-mensah/boutique-pagnes')->assertForbidden();
    }

    public function test_github_appears_in_the_connectors_only_when_the_app_is_set(): void
    {
        $user = $this->linkedUser();
        $this->actingAs($user)->get(route('settings.connectors'))->assertOk()
            ->assertSee('data-connector="github"', false)
            ->assertSee('Relié au compte ama-mensah')
            ->assertSee('https://github.com/settings/installations/4242', false);

        // Sans la GitHub App, GitHub disparaît ; les autres connecteurs restent (quand le Runner est réglé).
        config(['services.github.app_slug' => null]);
        $this->actingAs($user)->get(route('settings.connectors'))->assertDontSee('data-connector="github"', false);
        config(['kekeli.runner.secret' => null]);
        $this->actingAs($user)->get(route('settings.connectors'))->assertNotFound();
    }

    public function test_the_device_flow_links_an_account_with_a_short_code(): void
    {
        Sleep::fake();
        $user = User::factory()->create(['email' => 'kofi@exemple.tg']);
        Http::fake([
            'github.com/login/device/code' => Http::response(['device_code' => 'appareil', 'user_code' => 'ABCD-1234', 'verification_uri' => 'https://github.com/login/device', 'interval' => 0, 'expires_in' => 900]),
            'github.com/login/oauth/access_token' => Http::sequence()->push(['error' => 'authorization_pending'])->push(['access_token' => 'ghu_appareil', 'expires_in' => 28800, 'refresh_token' => 'ghr_appareil']),
            'api.github.com/user/installations' => Http::response(['installations' => [['id' => 77, 'account' => ['login' => 'kofi']]]]),
            'api.github.com/user' => Http::response(['login' => 'kofi']),
        ]);

        $this->artisan('kekeli:github-appareil', ['email' => 'kofi@exemple.tg'])->expectsOutputToContain('ABCD-1234')->assertSuccessful();

        $user->refresh();
        $this->assertSame(['kofi', 77, 'ghu_appareil'], [$user->github_login, $user->github_installation_id, $user->github_token]);
        Http::assertSent(fn (Request $r) => ($r->data()['grant_type'] ?? null) === 'urn:ietf:params:oauth:grant-type:device_code');
    }

    public function test_never_sent_files(): void
    {
        foreach (['.env', '.env.local', 'database/database.sqlite', 'node_modules/a/index.js', 'vendor/x.php', 'cle.pem', '.venv/lib/x.py'] as $path) {
            $this->assertTrue(GithubFiles::forbidden($path), $path);
        }
        foreach (['.env.example', '.github/workflows/automatisation.yml', 'src/automatisation.js', 'LISEZ-MOI.md'] as $path) {
            $this->assertFalse(GithubFiles::forbidden($path), $path);
        }
        $clean = GithubFiles::clean(['a.js' => 'const cle = "sk-proj-abcdefghijklmnopqrstuvwxyz0123456789"', 'b.js' => 'ok']);
        $this->assertSame(['b.js'], array_keys($clean['files']));
        $this->assertSame(['a.js'], $clean['skipped']);
    }

    private function linkedUser(array $attributes = []): User
    {
        return User::factory()->create($attributes + [
            'country' => 'TG',
            'github_login' => 'ama-mensah',
            'github_token' => 'ghu_jeton_invente',
            'github_refresh_token' => 'ghr_invente',
            'github_token_expires_at' => now()->addHours(8),
            'github_installation_id' => 4242,
        ]);
    }

    private function project(User $user, array $attributes = []): Project
    {
        $project = $user->projects()->make(['name' => 'Conseils du matin']);
        $project->forceFill($attributes + ['kind' => Project::KIND_WEBAPP, 'is_automation' => true, 'app_stack' => 'automatisation', 'app_template' => 'socle'])->save();

        $folder = sys_get_temp_dir().'/kekeli-github-'.bin2hex(random_bytes(4));
        foreach ([
            'src/automatisation.js' => 'console.log("bonjour")',
            '.github/workflows/automatisation.yml' => "on:\n  schedule:\n    - cron: '0 7 * * *'\n      timezone: 'Africa/Lome'\n",
            '.env.example' => "RESEND_API_KEY=\n",
            '.env' => 'RESEND_API_KEY=re_cle_inventee_pour_le_test_0123456789',
            'database/essai.sqlite' => 'base',
            'config/cle.js' => 'export default "re_cle_inventee_pour_le_test_0123456789"',
        ] as $path => $content) {
            @mkdir(dirname($folder.'/'.$path), 0777, true);
            file_put_contents($folder.'/'.$path, $content);
        }
        exec('tar -czf '.escapeshellarg($folder.'.tar.gz').' -C '.escapeshellarg($folder).' .');
        $backups = app(ProjectBackup::class);
        $backups->disk()->put($backups->path($project->id), file_get_contents($folder.'.tar.gz'));
        exec('rm -rf '.escapeshellarg($folder).' '.escapeshellarg($folder.'.tar.gz'));
        $project->forceFill(['backup_at' => now()])->save();

        return $project;
    }

    private function fakeGithub(): void
    {
        $repo = 'api.github.com/repos/'.self::REPO;
        $pair = sodium_crypto_box_keypair();
        Http::fake([
            'api.github.com/user/repos' => Http::response(['id' => 99, 'full_name' => self::REPO, 'default_branch' => 'main'], 201),
            'api.github.com/user/installations/4242/repositories/99' => Http::response(null, 204),
            $repo.'/git/ref/heads/main' => Http::response(['object' => ['sha' => 'commit-initial']]),
            $repo.'/git/ref/heads/*' => Http::response(['message' => 'Not Found'], 404),
            $repo.'/git/commits/commit-initial' => Http::response(['tree' => ['sha' => 'arbre-initial']]),
            $repo.'/git/trees/arbre-initial*' => Http::response(['tree' => [['path' => 'README.md', 'type' => 'blob', 'sha' => 'x', 'mode' => '100644']]]),
            $repo.'/git/blobs' => Http::response(['sha' => 'blob']),
            $repo.'/git/trees' => Http::response(['sha' => 'arbre-nouveau']),
            $repo.'/git/commits' => Http::response(['sha' => 'commit-nouveau']),
            $repo.'/git/refs*' => Http::response([]),
            $repo.'/pulls*' => Http::sequence()->push([])->push(['number' => 7, 'html_url' => 'https://github.com/'.self::REPO.'/pull/7']),
            $repo.'/actions/secrets/public-key' => Http::response(['key_id' => 'cle-numero-1', 'key' => base64_encode(sodium_crypto_box_publickey($pair))]),
            $repo.'/actions/secrets/*' => Http::response(null, 201),
        ]);
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
