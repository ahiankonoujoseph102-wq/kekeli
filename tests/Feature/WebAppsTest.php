<?php

namespace Tests\Feature;

use App\Agent\AgentPlaces;
use App\Agent\ProjectFiles;
use App\Agent\ProviderList;
use App\Agent\RunnerClient;
use App\Agent\RunnerSignature;
use App\Jobs\SendAgentRun;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\ProviderUsage;
use App\Models\User;
use App\Support\Settings;
use App\WebApps\WebAppTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Volt\Volt;
use Tests\TestCase;

class WebAppsTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'secret-de-test-tres-long-pour-les-signatures-0123456789';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'kekeli.runner.secret' => self::SECRET,
            'kekeli.runner.url' => 'http://runner.test',
            'kekeli.providers_order' => 'gemini',
            'kekeli.backups.disk' => 'local',
        ]);
        Storage::fake('local');
    }

    public function test_the_old_addresses_lead_to_the_single_field(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/projets/nouvelle-application?modele=commandes-boutique')->assertRedirect('/projets');
        $this->actingAs($user)->get('/projets/nouveau-site')->assertRedirect('/projets');
        $this->get('/modeles')->assertRedirect('/');
        $this->get('/modeles/restaurant')->assertRedirect('/');
        $this->get('/modeles/restaurant/apercu/index.html')->assertRedirect('/');
    }

    public function test_kekeli_guesses_a_site_with_a_database_and_its_technology_without_showing_them(): void
    {
        Bus::fake([SendAgentRun::class]);
        $user = User::factory()->create();

        Volt::actingAs($user)->test('site-prompt')
            ->set('description', 'Les parents inscrivent leurs enfants à l’école « Les Lumières », l’école accepte ou refuse et voit la liste par classe.')
            ->call('build')
            ->assertHasNoErrors();
        $project = Project::sole();
        $this->assertTrue($project->isWebApp());
        $this->assertSame(['laravel', 'socle', 'École Les Lumières'], [$project->app_stack, $project->app_template, $project->name]);
        $run = $project->agentRuns()->whereNull('purpose')->sole();
        $this->assertStringContainsString('site de départ', $run->instructions);
        $this->assertStringContainsString('« École Les Lumières »', $run->instructions);

        // Ni le type de site ni la technologie ne sont montrés au client.
        $this->actingAs($user)->get(route('projects.show', $project))->assertOk()
            ->assertDontSee('Laravel (PHP)')
            ->assertDontSee('MySQL')
            ->assertDontSee('Next.js');
        $this->actingAs($user)->get(route('projects.index'))->assertOk()
            ->assertDontSee('Site avec base de données')
            ->assertDontSee('Site vitrine');

        // Le Runner reçoit la technologie et le site de départ.
        Http::fake(['runner.test/*' => Http::response(['accepte' => true], 202)]);
        app(RunnerClient::class)->send($run);
        Http::assertSent(fn ($request) => $request['application'] === ['pile' => 'laravel', 'modele' => 'socle'] && $request['depot'] === null);

        Volt::actingAs($user)->test('site-prompt')
            ->set('description', 'Une boutique en Next.js sur Vercel : les clients créent un compte et suivent leurs commandes.')
            ->call('build');
        $this->assertSame('nextjs', Project::latest('id')->where('id', '!=', $project->id)->sole()->app_stack);

        $templates = app(WebAppTemplates::class);
        $this->assertSame('nextjs', $templates->suggestStack('Je veux le mettre sur Vercel'));
        $this->assertSame('nextjs', $templates->suggestStack('un site en Node.js'));
        $this->assertSame('laravel', $templates->suggestStack('Une boutique avec des commandes'));
    }

    public function test_the_test_administrator_is_kept_encrypted_and_shown_to_the_owner(): void
    {
        [$user, $project, $run] = $this->webApp();
        $this->event($run, ['type' => 'application', 'pile' => 'laravel', 'identifiant' => 'admin@essai.kekeli.test', 'mot_de_passe' => 'essai-0123456789abcd'])->assertOk();

        $project->refresh();
        $this->assertSame('essai-0123456789abcd', $project->app_admin_password);
        $this->assertStringNotContainsString('essai-0123456789abcd', (string) \DB::table('projects')->value('app_admin_password'));

        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertDontSee('Laravel (PHP)')
            ->assertDontSee('Application web')
            ->assertSee('admin@essai.kekeli.test')
            ->assertSee('essai-0123456789abcd')
            ->assertSee('Récupérer le code')
            ->assertDontSee('Publier mon site');
    }

    public function test_the_report_shows_the_tests_security_and_the_site_answering(): void
    {
        [, , $run] = $this->webApp();
        $this->event($run, ['type' => 'etape', 'genre' => 'tests_echec', 'cible' => 'php artisan test : 1 échoué(s), 15 réussi(s)'])->assertOk();
        $this->event($run, ['type' => 'etape', 'genre' => 'securite_ok', 'cible' => ''])->assertOk();
        $this->event($run, ['type' => 'etape', 'genre' => 'application_ok', 'cible' => 'code 200'])->assertOk();
        $this->event($run, ['type' => 'terminee', 'reponse' => "## Ce que j'ai fait\nJ'ai ajouté le menu du jour."])->assertOk();

        $report = $run->fresh()->report;
        $this->assertSame([false, true, true], array_column($report['tests'], 'ok'));
        $this->assertSame('Les tests du site échouent', $report['tests'][0]['text']);
        $this->assertStringContainsString('1 échoué(s)', $report['problems'][0]);
        // Le client lit une phrase simple et une phrase polie, sans détail technique.
        $this->assertSame("J'ai ajouté le menu du jour.", $report['done']);
        $this->assertNotNull($report['note']);
    }

    public function test_the_code_given_to_the_client_never_contains_secrets_nor_the_test_database(): void
    {
        [, $project] = $this->webApp();
        $tar = $this->tar([
            '.env' => 'APP_KEY=base64:secret',
            '.env.example' => 'APP_KEY=',
            '.htaccess' => 'RewriteEngine On',
            'database/database.sqlite' => 'SQLite format 3',
            'app/Models/User.php' => '<?php',
        ]);
        Storage::disk('local')->put('sauvegardes/'.$project->id.'.tar.gz', gzencode($tar));
        $project->forceFill(['backup_at' => now()])->save();

        $this->assertSame(['.env.example', '.htaccess', 'app/Models/User.php'], array_keys(app(ProjectFiles::class)->read($project)));
    }

    public function test_the_deploy_guide_no_longer_exists_and_its_old_address_leads_to_the_project(): void
    {
        [$user, $project] = $this->webApp();

        $this->assertFalse(Route::has('projects.deploy-guide'));
        $this->assertFalse(Route::has('projects.hosting-package'));
        $this->assertFalse(Route::has('runner.package.store'));
        $this->actingAs(User::factory()->create())->get('/projets/'.$project->id.'/mise-en-ligne')->assertNotFound();
        $this->actingAs($user)->get('/projets/'.$project->id.'/mise-en-ligne')->assertRedirect(route('projects.show', $project));

        // Plus aucun détail technique d'hébergement dans la page du projet.
        $project->forceFill(['backup_at' => now()])->save();
        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertDontSee('Hostinger')
            ->assertDontSee('cPanel')
            ->assertDontSee('TRUSTED_PROXIES')
            ->assertDontSee('Guide pas à pas');
    }

    public function test_a_site_with_a_database_does_not_promise_a_publication_and_its_code_can_be_taken(): void
    {
        config(['services.github.client_id' => null, 'services.github.client_secret' => null]);
        [$user, $project] = $this->webApp();
        $project->forceFill(['backup_at' => now()])->save();

        $html = $this->actingAs($user)->get(route('projects.show', $project))->assertOk()->getContent();
        $start = strpos($html, 'data-project-bar');
        $bar = substr($html, $start, strpos($html, '</header>', $start) - $start);
        $this->assertStringContainsString('Récupérer le code', $bar);
        $this->assertStringNotContainsString('Mettre en ligne', $bar);

        $sheet = substr($html, strpos($html, 'data-publish-sheet'));
        $this->assertStringContainsString('Ce site a besoin d&#039;un hébergeur avec base de données. Son code vous appartient', $sheet);
        $this->assertStringContainsString('Télécharger le code', $sheet);
        $this->assertStringContainsString(route('projects.code.zip', $project), $sheet);
        $this->assertStringNotContainsString('Publier mon site', $sheet);
        $this->assertStringNotContainsString('wire:click="publish"', $sheet);
        // GitHub n'est pas réglé : rien n'est montré, ni bouton ni message « bientôt ».
        $this->assertStringNotContainsString('data-github', $html);
        $this->assertStringNotContainsString('bientôt', $html);

        // Dès que l'accès à GitHub est réglé, « Relier à GitHub » apparaît tout seul.
        config(['services.github.client_id' => 'numero-application', 'services.github.client_secret' => 'secret-application']);
        $sheet = $this->actingAs($user)->get(route('projects.show', $project))->getContent();
        $sheet = substr($sheet, strpos($sheet, 'data-publish-sheet'));
        $this->assertStringContainsString('Relier à GitHub', $sheet);
        $this->assertStringContainsString(e(route('github.connect', ['projet' => $project->id])), $sheet);
    }

    public function test_paid_clients_pass_first_and_free_requests_use_part_of_the_places_when_the_free_quota_is_low(): void
    {
        $settings = app(Settings::class);
        $settings->set('max_concurrent_runs', 4);
        $places = app(AgentPlaces::class);

        $busy = $this->runFor('pro', ['status' => AgentRun::STATUS_RUNNING, 'sent_at' => now()]);
        $free = $this->runFor('gratuite');
        $this->travel(1)->seconds();
        $paid = $this->runFor('pro');

        // La demande payante, envoyée après, passe devant la demande gratuite.
        $this->assertSame(0, $places->ahead($paid));
        $this->assertSame(1, $places->ahead($free));

        // Quota gratuit encore large : la demande gratuite peut partir.
        $this->assertFalse($places->freeQuotaLow());
        $this->assertTrue($places->canStart($free->fresh()));

        // Quota gratuit presque épuisé (85 % de la limite du jour) : la moitié des places pour les demandes gratuites.
        ProviderUsage::forceCreate(['day' => app(ProviderList::class)->quotaDay('gemini'), 'provider' => 'gemini', 'model' => config('kekeli.providers.gemini.model'), 'requests' => 430]);
        $this->assertTrue($places->freeQuotaLow());
        $this->runFor('gratuite', ['status' => AgentRun::STATUS_RUNNING, 'sent_at' => now()]);
        $this->assertTrue($places->canStart($free->fresh()));
        $this->runFor('gratuite', ['status' => AgentRun::STATUS_RUNNING, 'sent_at' => now()]);
        $this->assertFalse($places->canStart($free->fresh()), 'les demandes gratuites ont déjà leurs deux places');
        $this->assertTrue($places->canStart($paid->fresh()), 'une demande payante passe encore');
        $this->assertNotNull($busy);

        $settings->set('paid_priority_quota_percent', 0);
        $this->assertFalse($places->freeQuotaLow());
    }

    /**
     * @return array{0: User, 1: Project, 2: AgentRun}
     */
    private function webApp(): array
    {
        $user = User::factory()->create();
        $project = $user->projects()->create(['name' => 'Restaurant Chez Ama']);
        $project->forceFill(['kind' => Project::KIND_WEBAPP, 'app_stack' => 'laravel', 'app_template' => 'reservations-restaurant'])->save();
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Ajoute un menu du jour']);
        $run = $project->agentRuns()->create(['message_id' => $message->id, 'status' => AgentRun::STATUS_RUNNING, 'started_at' => now()]);

        return [$user, $project, $run];
    }

    private function runFor(string $plan, array $attributes = []): AgentRun
    {
        $user = User::factory()->create();
        $user->forceFill(['plan' => $plan, 'plan_expires_at' => $plan === 'gratuite' ? null : now()->addDays(30)])->save();
        $project = $user->projects()->create(['name' => 'Projet']);
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Crée mon application']);
        $run = $project->agentRuns()->create(['message_id' => $message->id]);
        $run->forceFill($attributes)->save();

        return $run;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function event(AgentRun $run, array $event): TestResponse
    {
        return $this->signed('POST', '/runner/evenements', json_encode(['tache' => $run->id, 'evenement' => $event], JSON_UNESCAPED_UNICODE), 'application/json');
    }

    private function signed(string $method, string $uri, string $body, string $type = 'application/octet-stream'): TestResponse
    {
        $server = ['CONTENT_TYPE' => $type, 'HTTP_ACCEPT' => 'application/json'];
        foreach (RunnerSignature::headers(self::SECRET, $body) as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call($method, $uri, server: $server, content: $body);
    }

    /**
     * Small « tar » archive (ustar format).
     *
     * @param  array<string, string>  $files
     */
    private function tar(array $files): string
    {
        $tar = '';
        foreach ($files as $name => $content) {
            $header = str_pad($name, 100, "\0").str_pad('0000644', 8, "\0").str_pad('0000000', 8, "\0").str_pad('0000000', 8, "\0")
                .str_pad(decoct(strlen($content)), 11, '0', STR_PAD_LEFT)."\0".str_pad(decoct(time()), 11, '0', STR_PAD_LEFT)."\0"
                .'        0'.str_repeat("\0", 100).'ustar'."\0".'00'.str_repeat("\0", 247);
            $sum = array_sum(array_map('ord', str_split($header)));
            $header = substr_replace($header, str_pad(decoct($sum), 6, '0', STR_PAD_LEFT)."\0 ", 148, 8);
            $tar .= $header.str_pad($content, (int) ceil(strlen($content) / 512) * 512, "\0");
        }

        return $tar.str_repeat("\0", 1024);
    }
}
