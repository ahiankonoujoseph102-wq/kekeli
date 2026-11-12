<?php

namespace Tests\Feature;

use App\Agent\AnswerModels;
use App\Agent\ProjectBackup;
use App\Agent\RunnerClient;
use App\Agent\Tools\CreateApplication;
use App\Jobs\RespondToMessage;
use App\Jobs\SendAgentRun;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\ProjectSecret;
use App\Models\User;
use App\Support\SecretScanner;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;
use ZipArchive;

/**
 * Création d'applications (12 novembre 2026), comme Claude Code, Codex et Cursor : plan puis construction, petite
 * modification sans plan, questions sur le code sans modification, technologie imposée respectée, Variables secrètes,
 * clé collée jamais réutilisée, code complet avec son LISEZ-MOI.
 */
class ApplicationsTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'secret-de-test-tres-long-pour-les-signatures-0123456789';

    private const PLAN = "## Pages\n- Accueil, Mes rendez-vous, Administration\n\n## Données\n- Rendez-vous (cliente, date, heure, état)\n\n## Rôles\n- Cliente, administrateur\n\n## Fonctions\n- Prendre, annuler, confirmer\n\nTechnologie : Laravel (PHP), simple et solide, facile à héberger";

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Bus::fake([SendAgentRun::class]);
        config(['kekeli.runner.secret' => self::SECRET, 'kekeli.runner.url' => 'http://runner.test']);
    }

    private function ask(User $user, string $text): Project
    {
        Volt::actingAs($user)->test('site-prompt')->set('description', $text)->call('build');

        return $user->projects()->latest('id')->firstOrFail();
    }

    public function test_an_application_request_shows_a_short_plan_with_a_build_button_before_any_work(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->callsToolWith('creer_application', ['plan' => self::PLAN, 'pile' => 'auto', 'confirme' => 'non']);
        $chat = $this->ask($user, 'je veux une application pour les rendez-vous de mon salon');

        $this->assertTrue($chat->fresh()->isChat(), 'aucune construction avant le plan accepté');
        Bus::assertNotDispatched(SendAgentRun::class);
        $this->actingAs($user)->get(route('projects.show', $chat))->assertOk()
            ->assertSee('<h2>Pages</h2>', false)
            ->assertSee('Technologie : Laravel (PHP), simple et solide, facile à héberger')
            ->assertSee('data-build-plan', false)
            ->assertSee('Construire ce plan')
            ->assertSee("Ou écrivez ce qu'il faut changer dans le plan.");
    }

    public function test_build_this_plan_turns_the_discussion_into_an_installable_application_and_starts_the_work(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->callsToolWith('creer_application', ['plan' => self::PLAN, 'pile' => 'auto', 'confirme' => 'non']);
        $chat = $this->ask($user, 'une application pour les rendez-vous de mon salon Belle Afi');
        $offer = app(CreateApplication::class)->lastOffer($chat);

        Volt::actingAs($user)->test('projects.show', ['project' => $chat])->call('acceptPlan', $offer->id);

        $project = $chat->fresh();
        $this->assertTrue($project->isApplication());
        $this->assertSame('laravel', $project->app_stack);
        $run = $project->agentRuns()->whereNull('purpose')->sole();
        $this->assertSame('Construire ce plan', $run->message->content);
        $this->assertStringContainsString('Plan validé par le client', $run->instructions);
        $this->assertStringContainsString('## Données', $run->instructions);
        Bus::assertDispatched(SendAgentRun::class);

        // Le Runner reçoit une application installable, sans service imposé.
        Http::fake(['runner.test/*' => Http::response(['accepte' => true], 202)]);
        app(RunnerClient::class)->send($run);
        Http::assertSent(fn (Request $request) => $request['application'] === ['pile' => 'laravel', 'modele' => 'socle', 'installable' => true, 'service' => null]);
    }

    public function test_the_client_can_also_accept_the_plan_by_writing(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $this->model
            ->callsToolWith('creer_application', ['plan' => self::PLAN, 'pile' => 'auto', 'confirme' => 'non'])
            ->callsToolWith('creer_application', ['plan' => '', 'pile' => 'auto', 'confirme' => 'oui']);
        $chat = $this->ask($user, 'une application de rendez-vous');

        Volt::actingAs($user)->test('projects.show', ['project' => $chat])->set('content', 'oui vas-y construis')->call('send');

        $this->assertTrue($chat->fresh()->isApplication());
        $this->assertStringContainsString('Rendez-vous (cliente, date, heure, état)', $chat->agentRuns()->whereNull('purpose')->sole()->instructions);
    }

    public function test_the_technology_asked_by_the_client_is_always_respected(): void
    {
        $this->assertSame(['nextjs', 'supabase'], CreateApplication::stackFor('supabase', ''));
        $this->assertSame(['nextjs', 'supabase'], CreateApplication::stackFor('auto', 'une application avec Supabase'));
        $this->assertSame(['nextjs', null], CreateApplication::stackFor('auto', 'je la veux en Next.js'));
        $this->assertSame(['laravel', null], CreateApplication::stackFor('laravel', 'avec React'));
        $this->assertSame(['laravel', null], CreateApplication::stackFor('auto', 'une application de rendez-vous'));

        $user = User::factory()->create(['country' => 'TG']);
        $this->model->callsToolWith('creer_application', ['plan' => self::PLAN, 'pile' => 'supabase', 'confirme' => 'non']);
        $chat = $this->ask($user, 'une application de commandes avec Supabase');
        Volt::actingAs($user)->test('projects.show', ['project' => $chat])->call('acceptPlan', app(CreateApplication::class)->lastOffer($chat)->id);

        $project = $chat->fresh();
        $this->assertSame(['nextjs', 'supabase'], [$project->app_stack, $project->app_service]);
        $this->assertStringContainsString('Supabase', $project->agentRuns()->whereNull('purpose')->sole()->instructions);
    }

    public function test_a_site_request_still_creates_a_site_without_a_plan(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->callsTool('creer_site');
        $project = $this->ask($user, 'crée le site de mon restaurant');

        $this->assertSame(Project::KIND_SITE, $project->fresh()->kind);
        $this->assertFalse($project->fresh()->isApplication());
        Bus::assertDispatched(SendAgentRun::class);
    }

    public function test_a_small_change_in_an_application_starts_at_once_without_a_plan(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $project = $this->application($user);
        $this->model->callsTool('modifier_site');

        Volt::actingAs($user)->test('projects.show', ['project' => $project])->set('content', 'mets le bouton de connexion en vert')->call('send');

        $runs = $project->agentRuns()->orderBy('id')->get();
        $this->assertNull(data_get($runs->first()->report, 'offer'));
        $this->assertSame('mets le bouton de connexion en vert', $runs->last()->message->content);
        Bus::assertDispatched(SendAgentRun::class);
    }

    public function test_a_question_about_the_code_reads_the_files_without_changing_anything(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $project = $this->application($user, withBackup: true);
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'où est la page de connexion ?']);
        $run = $project->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);

        $this->useRealFirstResponder();
        Http::fake(['runner.test/repondre' => Http::response(['ok' => true, 'decision' => 'repondre', 'reponse' => 'La page de connexion est dans `resources/views/auth/connexion.blade.php`.', 'fournisseur' => 'openai', 'modele' => 'gpt-6.1-terra', 'refus' => []])]);
        RespondToMessage::dispatchSync($run);

        $lecture = null;
        Http::assertSent(function (Request $request) use (&$lecture) {
            $lecture = $request['lecture'];

            return $request['lieu'] === 'site' && $request['contexte']['application'] === true && str_contains((string) $request['lecture'], '/fichier?');
        });
        Bus::assertNotDispatched(SendAgentRun::class);

        // L'adresse signée donne un fichier de la sauvegarde, jamais .env, et rien sans signature.
        $this->getJson($lecture.'&chemin=resources/views/auth/connexion.blade.php')->assertOk()->assertJson(['chemin' => 'resources/views/auth/connexion.blade.php', 'contenu' => '<form>connexion</form>']);
        $this->getJson($lecture.'&chemin=.env')->assertNotFound();
        $this->getJson(route('runner.file.show', $project).'?chemin=LISEZ-MOI.md')->assertForbidden();
    }

    public function test_secret_variables_are_encrypted_never_shown_again_and_only_sent_to_the_computer_of_the_project(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $project = $this->application($user);

        Volt::actingAs($user)->test('projects.show', ['project' => $project])
            ->set('secretName', 'database_url')
            ->set('secretValue', 'postgresql://postgres:TresSecret2026@db.exemple.supabase.co:5432/postgres')
            ->call('saveSecret')
            ->assertHasNoErrors()
            ->set('secretName', 'APP_KEY')->set('secretValue', 'x')->call('saveSecret')->assertHasErrors('secretName')
            ->set('secretName', 'mauvais nom')->set('secretValue', 'x')->call('saveSecret')->assertHasErrors('secretName');

        $secret = ProjectSecret::sole();
        $this->assertSame('DATABASE_URL', $secret->name);
        $this->assertStringNotContainsString('TresSecret2026', (string) DB::table('project_secrets')->value('value'));

        $html = $this->actingAs($user)->get(route('projects.show', $project))->assertOk()->getContent();
        $this->assertStringContainsString('data-secret="DATABASE_URL"', $html);
        $this->assertStringNotContainsString('TresSecret2026', $html);

        Http::fake(['runner.test/*' => Http::response(['accepte' => true], 202)]);
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'ajoute la page profil']);
        app(RunnerClient::class)->send($project->agentRuns()->create(['message_id' => $message->id]));
        Http::assertSent(fn (Request $request) => $request['secrets'] === ['DATABASE_URL' => 'postgresql://postgres:TresSecret2026@db.exemple.supabase.co:5432/postgres']);

        // Le premier répondant (sans ordinateur) ne reçoit jamais les variables secrètes.
        $this->useRealFirstResponder();
        Http::fake(['runner.test/repondre' => Http::response(['ok' => true, 'decision' => 'repondre', 'reponse' => 'Bien.', 'fournisseur' => 'gemini', 'modele' => 'gemini-3.5-flash-lite', 'refus' => []])]);
        RespondToMessage::dispatchSync($project->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION]));
        Http::assertSent(fn (Request $request) => ! str_contains($request->body(), 'TresSecret2026'));

        // Un autre utilisateur n'ouvre jamais le projet ; le propriétaire peut supprimer la variable.
        $this->actingAs(User::factory()->create(['country' => 'TG']))->get(route('projects.show', $project))->assertNotFound();
        Volt::actingAs($user)->test('projects.show', ['project' => $project])->call('deleteSecret', $secret->id);
        $this->assertSame(0, ProjectSecret::count());
    }

    public function test_a_key_pasted_in_the_discussion_is_masked_never_reused_and_the_form_is_suggested(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $project = $this->application($user);
        $this->model->answers('Merci. Mettez plutôt la clé dans « Variables secrètes ».');

        Volt::actingAs($user)->test('projects.show', ['project' => $project])
            ->set('content', 'voici ma clé sk-proj-abcdefghijklmnopqrstuvwxyz0123456789 branche-la')
            ->call('send')
            ->assertSet('keyMasked', true)
            ->assertSee('Pour votre sécurité, Kekeli a masqué la clé que vous avez collée');

        $stored = $project->messages()->where('role', Message::ROLE_USER)->latest('id')->value('content');
        $this->assertSame('voici ma clé [clé masquée] branche-la', $stored);
        $this->assertStringNotContainsString('sk-proj-abcdefghijklmnopqrstuvwxyz', (string) $this->model->received[0]['message']);
        $this->assertSame(['postgresql://u:'.SecretScanner::MASK.'@hote/base', true], SecretScanner::redact('postgresql://u:motdepasse@hote/base'));
        $this->assertSame(['bonjour, ça va ?', false], SecretScanner::redact('bonjour, ça va ?'));
    }

    public function test_the_downloaded_code_always_has_its_french_readme_and_never_the_env_file(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $project = $this->application($user, withBackup: true);

        $response = $this->actingAs($user)->get(route('projects.code.zip', $project))->assertOk();
        $zip = new ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());
        $readme = $zip->getFromName($project->pagesName().'/LISEZ-MOI.md');
        $this->assertStringContainsString('## Variables à remplir', $readme);
        $this->assertStringContainsString('php artisan serve', $readme);
        $this->assertFalse($zip->getFromName($project->pagesName().'/.env'));
        $zip->close();
    }

    public function test_the_build_model_follows_the_formula_with_the_free_providers_behind_and_the_daily_ceiling(): void
    {
        $answers = app(AnswerModels::class);
        $free = User::factory()->create(['plan' => 'gratuite']);
        $pro = User::factory()->create(['plan' => 'pro', 'plan_expires_at' => now()->addDays(10)]);

        $this->assertSame(['openai', 'gpt-6.1-terra'], [$answers->buildForRunner($free)[0]['fournisseur'], $answers->buildForRunner($free)[0]['modele']]);
        $this->assertSame('gemini', $answers->buildForRunner($free)[1]['fournisseur']);
        $this->assertSame(['openai', 'gpt-6.1-sol'], [$answers->buildForRunner($pro)[0]['fournisseur'], $answers->buildForRunner($pro)[0]['modele']]);
        $this->assertSame('gemini', $answers->buildForRunner($pro)[1]['fournisseur']);

        app(Settings::class)->set('openai_daily_cap_usd', 0);
        $this->assertNotContains('openai', array_column($answers->buildForRunner($pro), 'fournisseur'));
    }

    public function test_the_folded_actions_of_an_application_show_the_files_and_commands_like_claude_code(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $project = $this->application($user);
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'ajoute la page profil']);
        $run = $project->agentRuns()->create(['message_id' => $message->id]);
        $run->steps()->create(['kind' => 'fichier_lu', 'target' => '/home/user/projet/routes/web.php']);
        $run->steps()->create(['kind' => 'fichier_modifie', 'target' => "Success. Updated the following files:\nM app/Http/Controllers/ProfilController.php"]);
        $run->steps()->create(['kind' => 'commande', 'target' => 'php artisan test']);
        $run->forceFill(['status' => AgentRun::STATUS_COMPLETED, 'finished_at' => now(), 'report' => ['done' => 'La page profil est prête.', 'note' => null, 'files' => [], 'files_count' => 0, 'tests' => [], 'problems' => []]])->save();

        $html = $this->actingAs($user)->get(route('projects.show', $project))->assertOk()->getContent();
        $this->assertStringContainsString('data-step-detail>routes/web.php</code>', $html);
        $this->assertStringContainsString('data-step-detail>app/Http/Controllers/ProfilController.php</code>', $html);
        $this->assertStringContainsString('data-step-detail>php artisan test</code>', $html);
        $this->assertStringNotContainsString('Success. Updated', $html);
        // Le message final reste court et clair.
        $this->assertStringContainsString('La page profil est prête.', $html);
    }

    private function application(User $user, bool $withBackup = false): Project
    {
        $project = $user->projects()->make(['name' => 'Belle Afi']);
        $project->forceFill(['kind' => Project::KIND_WEBAPP, 'is_application' => true, 'app_stack' => 'laravel', 'app_template' => 'socle'])->save();

        if ($withBackup) {
            $folder = sys_get_temp_dir().'/kekeli-app-'.bin2hex(random_bytes(4));
            mkdir($folder.'/resources/views/auth', 0777, true);
            file_put_contents($folder.'/resources/views/auth/connexion.blade.php', '<form>connexion</form>');
            file_put_contents($folder.'/.env', 'APP_KEY=jamais');
            exec('tar -czf '.escapeshellarg($folder.'.tar.gz').' -C '.escapeshellarg($folder).' .');
            $backups = app(ProjectBackup::class);
            $backups->disk()->put($backups->path($project->id), file_get_contents($folder.'.tar.gz'));
            exec('rm -rf '.escapeshellarg($folder).' '.escapeshellarg($folder.'.tar.gz'));
            $project->forceFill(['backup_at' => now()])->save();
        }

        return $project;
    }
}
