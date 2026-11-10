<?php

namespace Tests\Feature;

use App\Agent\AgentPlaces;
use App\Agent\ProjectFiles;
use App\Agent\ProviderList;
use App\Agent\RunnerClient;
use App\Agent\RunnerSignature;
use App\Agent\RunReport;
use App\Jobs\BuildHostingPackage;
use App\Jobs\SendAgentRun;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\ProviderUsage;
use App\Models\User;
use App\Support\Settings;
use App\WebApps\HostingPackage;
use App\WebApps\WebAppTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
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

    public function test_kekeli_offers_the_templates_first_then_an_application_made_to_measure(): void
    {
        $user = User::factory()->create();

        $page = $this->actingAs($user)->get(route('projects.new-webapp'))->assertOk();
        $page->assertSeeInOrder(['Partir d\'un modèle', 'Moins d\'énergie', 'Réservations d\'un restaurant', 'Inscriptions d\'une association', 'Sur mesure']);
        $page->assertSee('Laravel (PHP) et MySQL')->assertSee('Next.js (Node) et PostgreSQL');
        $this->actingAs($user)->get(route('projects.index'))->assertSee(route('projects.new-webapp'));
    }

    public function test_an_application_from_a_template_is_created_and_sent_to_kekeli(): void
    {
        Bus::fake([SendAgentRun::class]);
        $user = User::factory()->create();

        Volt::actingAs($user)->test('projects.new-webapp', ['template' => 'reservations-hotel'])
            ->assertSee('Nom de votre établissement')
            ->set('name', 'Hôtel Le Lac')
            ->set('city', 'Aného')
            ->set('stack', 'nextjs')
            ->call('create')
            ->assertHasNoErrors();

        $project = Project::sole();
        $this->assertTrue($project->isWebApp());
        $this->assertSame(['nextjs', 'reservations-hotel'], [$project->app_stack, $project->app_template]);
        $run = $project->agentRuns()->sole();
        $this->assertSame('Je veux l\'application « Réservations d\'un hôtel » pour Hôtel Le Lac, Aného.', $run->message->content);
        $this->assertStringContainsString('lib/config.js', $run->instructions);
        Bus::assertDispatched(SendAgentRun::class);

        // Le Runner reçoit la technologie et le modèle de départ.
        Http::fake(['runner.test/*' => Http::response(['accepte' => true], 202)]);
        app(RunnerClient::class)->send($run);
        Http::assertSent(fn ($request) => $request['application'] === ['pile' => 'nextjs', 'modele' => 'reservations-hotel'] && $request['depot'] === null);
    }

    public function test_an_application_made_to_measure_needs_a_description_and_kekeli_chooses_the_technology(): void
    {
        Bus::fake([SendAgentRun::class]);
        $user = User::factory()->create();

        Volt::actingAs($user)->test('projects.new-webapp')
            ->set('name', 'École Les Lumières')
            ->set('description', 'trop court')
            ->call('create')
            ->assertHasErrors(['description']);
        $this->assertSame(0, Project::count());

        Volt::actingAs($user)->test('projects.new-webapp')
            ->set('name', 'École Les Lumières')
            ->set('description', 'Les parents inscrivent leurs enfants, l’école accepte ou refuse et voit la liste par classe.')
            ->call('create')
            ->assertHasNoErrors();
        $project = Project::sole();
        $this->assertSame(['laravel', 'socle'], [$project->app_stack, $project->app_template]);
        $this->assertStringContainsString('application de départ', $project->agentRuns()->sole()->instructions);

        $templates = app(WebAppTemplates::class);
        $this->assertSame('nextjs', $templates->suggestStack('Je veux la mettre sur Vercel'));
        $this->assertSame('nextjs', $templates->suggestStack('une application Node.js'));
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
            ->assertSee('Application web · Laravel (PHP) et MySQL')
            ->assertSee('admin@essai.kekeli.test')
            ->assertSee('essai-0123456789abcd')
            ->assertSee(route('projects.deploy-guide', $project))
            ->assertDontSee('Publier mon site');
    }

    public function test_the_report_shows_the_tests_security_and_the_application_answering(): void
    {
        [, , $run] = $this->webApp();
        $this->event($run, ['type' => 'etape', 'genre' => 'tests_echec', 'cible' => 'php artisan test : 1 échoué(s), 15 réussi(s)'])->assertOk();
        $this->event($run, ['type' => 'etape', 'genre' => 'securite_ok', 'cible' => ''])->assertOk();
        $this->event($run, ['type' => 'etape', 'genre' => 'application_ok', 'cible' => 'code 200'])->assertOk();
        $this->event($run, ['type' => 'terminee', 'reponse' => "## Ce que j'ai fait\nJ'ai ajouté le menu du jour."])->assertOk();

        $report = $run->fresh()->report;
        $this->assertSame([false, true, true], array_column($report['tests'], 'ok'));
        $this->assertStringContainsString('1 échoué(s)', $report['tests'][0]['text']);
        $this->assertStringContainsString('Les tests de l\'application échouent', $report['problems'][0]);
        $this->assertStringContainsString('compte administrateur d\'essai', $report['check'][0]);
        $this->assertStringContainsString('Tests de l\'application', RunReport::text($run->fresh()));
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

    public function test_the_deploy_guide_is_for_the_owner_and_adapted_to_the_technology(): void
    {
        [$user, $project] = $this->webApp();

        $this->actingAs(User::factory()->create())->get(route('projects.deploy-guide', $project))->assertNotFound();
        $page = $this->actingAs($user)->get(route('projects.deploy-guide', $project).'?hebergeur=cpanel')->assertOk();
        $page->assertSee('Laravel Cloud')->assertSee('Hostinger')->assertSee('Hébergement mutualisé avec cPanel')->assertSee('Render')
            ->assertSee('Assistant de base de données MySQL')
            ->assertSee('Préparer le fichier ZIP')
            ->assertSee('Votre clé de chiffrement APP_KEY')
            ->assertSee('INSTALLATION_AUTORISEE')
            ->assertSee('Jamais de mot de passe ni de clé dans le code');
        $this->assertMatchesRegularExpression('/base64:[A-Za-z0-9+\/=]{44}/', $page->getContent());

        $project->forceFill(['app_stack' => 'nextjs'])->save();
        $this->actingAs($user)->get(route('projects.deploy-guide', $project))->assertOk()
            ->assertSee('Vercel')->assertSee('Neon')->assertDontSee('cPanel')->assertDontSee('APP_KEY');

        $site = $user->projects()->create(['name' => 'Site simple']);
        $this->actingAs($user)->get(route('projects.deploy-guide', $site))->assertNotFound();
    }

    public function test_the_zip_file_for_shared_hosting_is_prepared_by_the_runner_and_downloaded_by_the_owner(): void
    {
        Bus::fake([BuildHostingPackage::class]);
        [$user, $project] = $this->webApp();

        // Pas encore de sauvegarde : il faut d'abord une première version.
        Volt::actingAs($user)->test('projects.deploy-guide', ['project' => $project])->set('host', 'cpanel')->call('buildPackage')->assertHasErrors('package');
        Bus::assertNotDispatched(BuildHostingPackage::class);

        $project->forceFill(['backup_at' => now()])->save();
        Volt::actingAs($user)->test('projects.deploy-guide', ['project' => $project])->set('host', 'cpanel')->call('buildPackage')->assertHasNoErrors();
        Bus::assertDispatched(BuildHostingPackage::class);
        $this->assertSame(HostingPackage::BUILDING, $project->fresh()->package_status);

        // Le Runner envoie le fichier en morceaux signés ; la tâche demande au Runner et attend sa réponse.
        Http::fake(['runner.test/paquet' => function () use ($project) {
            $this->signed('POST', route('runner.package.store', $project).'?partie=1&parties=2', 'PK-debut-')->assertOk();
            $this->signed('POST', route('runner.package.store', $project).'?partie=2&parties=2', 'fin')->assertOk();

            return Http::response(['ok' => true, 'taille' => 12]);
        }]);
        (new BuildHostingPackage($project->fresh()))->handle(app(RunnerClient::class));
        Http::assertSent(fn ($request) => $request->url() === 'http://runner.test/paquet' && $request['envoi'] === route('runner.package.store', $project));

        $project->refresh();
        $this->assertSame(HostingPackage::READY, $project->package_status);
        $this->assertSame(12, $project->package_size);
        $this->actingAs(User::factory()->create())->get(route('projects.hosting-package', $project))->assertNotFound();
        $reponse = $this->actingAs($user)->get(route('projects.hosting-package', $project))->assertOk();
        $this->assertSame('PK-debut-fin', $reponse->streamedContent());

        // Sans signature, le site refuse le fichier.
        $this->call('POST', route('runner.package.store', $project).'?partie=1&parties=1', content: 'x')->assertUnauthorized();
    }

    public function test_a_failed_zip_file_is_said_clearly(): void
    {
        [$user, $project] = $this->webApp();
        $project->forceFill(['backup_at' => now()])->save();
        Http::fake(['runner.test/paquet' => Http::response(['ok' => false, 'erreur' => 'réseau'], 502)]);

        (new BuildHostingPackage($project))->handle(app(RunnerClient::class));

        $this->assertSame(HostingPackage::FAILED, $project->fresh()->package_status);
        $this->actingAs($user)->get(route('projects.deploy-guide', $project).'?hebergeur=hostinger')->assertSee('Le fichier ZIP n\'a pas pu être préparé');
    }

    public function test_the_gallery_shows_the_web_application_templates(): void
    {
        $this->get(route('templates.index'))->assertOk()
            ->assertSee('Modèles d\'applications web')
            ->assertSee(route('projects.new-webapp', ['modele' => 'commandes-boutique']), false)
            ->assertSee('Inscriptions des élèves d\'une école');
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
