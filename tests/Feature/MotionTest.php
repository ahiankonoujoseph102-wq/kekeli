<?php

namespace Tests\Feature;

use App\Agent\RunnerClient;
use App\Agent\RunnerSignature;
use App\Agent\Tools\CreateAnimation;
use App\Agent\Tools\CreateDocument;
use App\Jobs\GenerateDocument;
use App\Jobs\SendAgentRun;
use App\Models\AgentRun;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\User;
use App\Motion\AnimationIntegration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Kekeli Motion : des animations SVG interactives à partir d'une phrase. L'outil « creer_animation », les fichiers de la
 * version (page, script, image SVG, code TypeScript), l'aperçu isolé, la bibliothèque et l'intégration dans un site.
 * Le modèle et le Runner sont simulés ; le moteur lui-même est vérifié dans runner/tests/motion.test.js.
 */
class MotionTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'secret-de-test-tres-long-pour-les-signatures-0123456789';

    private const SCENE = ['version' => 1, 'titre' => 'Jardin au coucher du soleil', 'format' => 'paysage', 'decor' => 'jardin', 'nom' => 'jardin-au-coucher-du-soleil', 'personnages' => [['genre' => 'homme', 'action' => 'marche']], 'elements' => []];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Bus::fake([GenerateDocument::class, SendAgentRun::class]);
        config(['kekeli.runner.secret' => self::SECRET, 'kekeli.runner.url' => 'http://runner.test']);
    }

    private function animation(User $user, bool $withVersion = true): Project
    {
        $project = $user->projects()->make(['name' => 'Jardin au coucher du soleil']);
        $project->forceFill(['kind' => Project::KIND_DOCUMENT, 'document_type' => 'animation'])->save();
        if ($withVersion) {
            $run = $project->agentRuns()->create(['purpose' => AgentRun::PURPOSE_DOCUMENT]);
            $folder = DocumentVersion::folder($project, $run->id);
            Storage::disk('local')->put($folder.'/animation.html', '<!doctype html><title>Jardin</title><div data-kekeli-motion="jardin"></div>');
            Storage::disk('local')->put($folder.'/animation.js', 'window.KekeliMotion.enregistrer("jardin-au-coucher-du-soleil", {});');
            Storage::disk('local')->put($folder.'/animation.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
            Storage::disk('local')->put($folder.'/animation.zip', 'PK');
            $project->documentVersions()->create(['agent_run_id' => $run->id, 'number' => 1, 'title' => 'Jardin au coucher du soleil', 'files' => ['html' => 'animation.html', 'js' => 'animation.js', 'svg' => 'animation.svg', 'zip' => 'animation.zip'], 'previews' => [], 'pages' => 1, 'structure' => self::SCENE]);
        }

        return $project;
    }

    private function site(User $user, string $kind = Project::KIND_SITE): Project
    {
        $site = $user->projects()->make(['name' => 'Restaurant Le Palmier']);
        $site->forceFill(['kind' => $kind, 'app_stack' => $kind === Project::KIND_WEBAPP ? 'laravel' : null])->save();

        return $site;
    }

    /**
     * @return array<string, string>
     */
    private function signed(string $body = ''): array
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

        return $this->call('POST', '/runner/evenements', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'] + $this->signed($body), content: $body);
    }

    public function test_an_animation_request_uses_creer_animation_at_once_with_its_format(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->callsToolWith('creer_animation', ['format' => 'vertical', 'nom' => 'Jardin au coucher du soleil']);
        Volt::actingAs($user)->test('site-prompt')->set('description', 'Crée un personnage animé qui marche dans un jardin avec un soleil qui se couche.')->call('build');

        $project = $user->projects()->latest('id')->firstOrFail();
        $this->assertTrue($project->isAnimation());
        $this->assertSame('animation', $project->typeKey());
        $this->assertSame('cursor-arrow-rays', $project->typeIcon());
        $this->assertSame('Jardin au coucher du soleil', $project->name);
        $run = $project->agentRuns()->where('purpose', AgentRun::PURPOSE_DOCUMENT)->sole();
        $this->assertSame(['type' => 'animation', 'format' => 'vertical'], array_intersect_key($run->document_options, ['type' => 1, 'format' => 1]));
        Bus::assertDispatched(GenerateDocument::class);
        $this->assertFalse(CreateAnimation::isLong(['taille' => 'long']), 'jamais de plan pour une animation');
    }

    public function test_the_runner_gets_the_format_and_the_previous_scene_for_a_change(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $project = $this->animation($user);
        $message = $project->messages()->create(['role' => 'user', 'content' => 'ajoute un chat et fais-le la nuit']);
        $run = CreateDocument::start($project, $message, ['type' => 'animation', 'action' => 'modifier', 'formats' => [], 'fichier' => null, 'recherche' => '']);

        Http::fake(['runner.test/*' => Http::response(['accepte' => true], 202)]);
        app(RunnerClient::class)->document($run);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/documents')
            && $r['document']['type'] === 'animation'
            && $r['document']['action'] === 'modifier'
            && $r['document']['format'] === 'auto'
            && $r['document']['precedente']['decor'] === 'jardin'
            && ! str_contains($r->body(), self::SECRET));
    }

    public function test_a_version_keeps_the_page_the_script_the_image_and_the_code(): void
    {
        $user = User::factory()->create();
        $project = $this->animation($user, withVersion: false);
        $run = $project->agentRuns()->create(['purpose' => AgentRun::PURPOSE_DOCUMENT, 'status' => AgentRun::STATUS_RUNNING]);
        foreach (['animation.html' => '<!doctype html>', 'animation.js' => 'x', 'animation.svg' => '<svg/>', 'animation.zip' => 'PK'] as $name => $content) {
            $this->call('POST', '/runner/projets/'.$project->id.'/documents/'.$run->id.'?nom='.$name, server: ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json'] + $this->signed($content), content: $content)->assertOk();
        }
        // Jamais un autre nom, jamais un chemin.
        $this->call('POST', '/runner/projets/'.$project->id.'/documents/'.$run->id.'?nom=../animation.js', server: ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json'] + $this->signed('x'), content: 'x')->assertStatus(422);

        $this->event($run, ['type' => 'document', 'titre' => 'Jardin au coucher du soleil', 'message' => 'Votre animation est prête.', 'fichiers' => ['html' => 'animation.html', 'js' => 'animation.js', 'svg' => 'animation.svg', 'zip' => 'animation.zip'], 'apercus' => [], 'pages' => 1, 'structure' => self::SCENE])->assertOk();

        $version = $project->documentVersions()->sole();
        $this->assertSame(['html' => 'animation.html', 'js' => 'animation.js', 'svg' => 'animation.svg', 'zip' => 'animation.zip'], $version->files);
        // Le script à poser dans un site n'est pas proposé au téléchargement.
        $this->assertSame(['html' => 'Page web', 'svg' => 'Image SVG', 'zip' => 'Code TypeScript'], $version->formats());
        $this->assertSame('jardin-au-coucher-du-soleil', AnimationIntegration::nameOf($version));
    }

    public function test_a_version_without_its_script_is_not_kept(): void
    {
        $user = User::factory()->create();
        $project = $this->animation($user, withVersion: false);
        $run = $project->agentRuns()->create(['purpose' => AgentRun::PURPOSE_DOCUMENT, 'status' => AgentRun::STATUS_RUNNING]);
        Storage::disk('local')->put(DocumentVersion::folder($project, $run->id).'/animation.html', 'x');
        $this->event($run, ['type' => 'document', 'titre' => 'Jardin', 'fichiers' => ['html' => 'animation.html'], 'structure' => self::SCENE])->assertOk();
        $this->assertSame(0, $project->documentVersions()->count());
    }

    public function test_the_preview_is_isolated_from_kekeli_and_only_for_the_owner(): void
    {
        $user = User::factory()->create();
        $project = $this->animation($user);

        $page = $this->actingAs($user)->get(route('projects.versions.animation', [$project, 1, 'page']))->assertOk();
        $this->assertStringStartsWith('text/html', $page->headers->get('Content-Type'));
        $policy = (string) $page->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('sandbox allow-scripts', $policy);
        $this->assertStringNotContainsString('allow-same-origin', $policy);
        $this->assertStringContainsString("default-src 'none'", $policy);
        $this->assertSame('nosniff', $page->headers->get('X-Content-Type-Options'));

        $image = $this->get(route('projects.versions.animation', [$project, 1, 'image']))->assertOk();
        $this->assertSame('image/svg+xml', $image->headers->get('Content-Type'));
        $this->get(route('projects.versions.download', [$project, 1, 'zip']))->assertOk()->assertDownload('jardin-au-coucher-du-soleil.zip');
        $this->get('/projets/'.$project->id.'/versions/1/animation/script')->assertNotFound();

        $this->actingAs(User::factory()->create())->get(route('projects.versions.animation', [$project, 1, 'page']))->assertNotFound();
    }

    public function test_the_project_shows_the_live_animation_its_downloads_and_the_sites(): void
    {
        $user = User::factory()->create();
        $project = $this->animation($user);

        Volt::actingAs($user)->test('projects.show', ['project' => $project])
            ->set('view', 'apercu')
            ->assertSee('data-animation-frame', false)
            ->assertSee('sandbox="allow-scripts"', false)
            ->assertSee('Paysage (bandeau d’un site)')
            ->assertSee('Créez d’abord un site avec Kekeli', false)
            ->assertDontSee('data-animation-integrate-open', false);

        $this->site($user);
        Volt::actingAs($user)->test('projects.show', ['project' => $project])
            ->set('view', 'apercu')
            ->assertSee('data-animation-integrate-open', false)
            ->assertSee('Restaurant Le Palmier');
    }

    public function test_the_library_shows_animations_with_their_image(): void
    {
        $user = User::factory()->create();
        $project = $this->animation($user);

        Volt::actingAs($user)->test('library')
            ->assertSee('Animations')
            ->assertSee(route('projects.versions.animation', [$project, 1, 'image']), false)
            ->call('setType', 'animation')
            ->assertSee('Jardin au coucher du soleil');
        $this->assertSame(1, $user->projects()->ofType('animation')->count());
        $this->assertSame(0, $user->projects()->ofType('document')->count());
    }

    public function test_putting_the_animation_in_a_site_starts_the_work_of_the_site_with_the_script(): void
    {
        $user = User::factory()->create();
        $project = $this->animation($user);
        $site = $this->site($user);

        Volt::actingAs($user)->test('projects.show', ['project' => $project])
            ->set('animationSite', $site->id)
            ->call('integrateAnimation', 1)
            ->assertHasNoErrors()
            ->assertRedirect(route('projects.show', $site));

        $run = $site->agentRuns()->sole();
        $this->assertSame('Ajoute l’animation « Jardin au coucher du soleil » à mon site.', $run->message->content);
        $this->assertStringContainsString('<div data-kekeli-motion="jardin-au-coucher-du-soleil"', $run->instructions);
        $this->assertStringContainsString('<script src="animations/jardin-au-coucher-du-soleil.js" defer></script>', $run->instructions);
        $this->assertStringContainsString('Ne modifie jamais ce fichier JavaScript', $run->instructions);
        Bus::assertDispatched(SendAgentRun::class);

        // Le Runner reçoit le script à déposer, lu avec une signature seulement.
        $animations = app(AnimationIntegration::class)->forRunner($site);
        $this->assertSame([['nom' => 'jardin-au-coucher-du-soleil', 'dossier' => 'animations', 'url' => route('runner.animations.show', [$site, $site->animations()->sole()])]], $animations);
        $this->get($animations[0]['url'])->assertUnauthorized();
        $script = $this->call('GET', $animations[0]['url'], server: $this->signed());
        $script->assertOk();
        $this->assertStringContainsString('KekeliMotion.enregistrer', $script->streamedContent());

        Http::fake(['runner.test/*' => Http::response(['accepte' => true], 202)]);
        app(RunnerClient::class)->send($run);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/taches') && $r['animations'] === $animations);
    }

    public function test_an_application_gets_the_script_in_its_public_folder(): void
    {
        $user = User::factory()->create();
        $version = $this->animation($user)->documentVersions()->sole();
        $app = $this->site($user, Project::KIND_WEBAPP);

        $this->assertNotNull(app(AnimationIntegration::class)->integrate($version, $app));
        $this->assertStringContainsString('<script src="/animations/jardin-au-coucher-du-soleil.js" defer></script>', $app->agentRuns()->sole()->instructions);
        $this->assertSame('public/animations', app(AnimationIntegration::class)->forRunner($app)[0]['dossier']);
    }

    public function test_never_in_a_site_of_someone_else_an_agent_or_a_busy_site(): void
    {
        $user = User::factory()->create();
        $project = $this->animation($user);
        $version = $project->documentVersions()->sole();
        $other = $this->site(User::factory()->create());

        Volt::actingAs($user)->test('projects.show', ['project' => $project])
            ->set('animationSite', $other->id)
            ->call('integrateAnimation', 1)
            ->assertHasErrors('animationSite');
        $this->assertSame(0, $other->agentRuns()->count());
        $this->assertNull(app(AnimationIntegration::class)->integrate($version, $other));

        $agent = $this->site($user, Project::KIND_WEBAPP);
        $agent->forceFill(['is_agent' => true])->save();
        $this->assertNull(app(AnimationIntegration::class)->integrate($version, $agent));
        $this->assertFalse(app(AnimationIntegration::class)->sitesFor($user)->contains('id', $agent->id));

        // Un site déjà au travail : rien ne change, le client réessaie plus tard.
        $busy = $this->site($user);
        $busy->agentRuns()->create(['status' => AgentRun::STATUS_RUNNING]);
        $this->assertNull(app(AnimationIntegration::class)->integrate($version, $busy));
        $this->assertSame(0, $busy->animations()->count());
    }
}
