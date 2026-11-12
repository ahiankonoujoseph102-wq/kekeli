<?php

namespace Tests\Feature;

use App\Agent\AnswerModels;
use App\Agent\Energy;
use App\Agent\RunnerClient;
use App\Agent\RunnerEventHandler;
use App\Agent\Tools\CreateApplication;
use App\Jobs\SendAgentRun;
use App\Models\AgentRun;
use App\Models\AgentStep;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Finitions de la pull request 41 (applications) et règles d'énergie comme Claude Code : « Lu · dossier du projet »,
 * commandes internes de Kekeli cachées, phrases adaptées à une construction neuve, bouton « Récupérer le code » en
 * icône sur téléphone, titre deviné dès le plan, restes du point de départ retirés, ligne sobre « Énergie épuisée »,
 * arrêt d'une construction au plafond OpenAI du jour au lieu de passer à un modèle gratuit.
 */
class ApplicationFinishesTest extends TestCase
{
    use RefreshDatabase;

    private const PLAN = "## Pages\n- Accueil, Mes rendez-vous\n\n## Données\n- Rendez-vous\n\n## Rôles\n- Cliente, administrateur\n\n## Fonctions\n- Prendre un rendez-vous\n\nTechnologie : Laravel (PHP)";

    protected function setUp(): void
    {
        parent::setUp();
        config(['kekeli.runner.secret' => 'secret-de-test-tres-long-pour-les-signatures-0123456789', 'kekeli.runner.url' => 'http://runner.test']);
    }

    private function application(User $user): Project
    {
        $project = $user->projects()->make(['name' => 'Belle Afi']);
        $project->forceFill(['kind' => Project::KIND_WEBAPP, 'is_application' => true, 'app_stack' => 'laravel', 'app_template' => 'socle'])->save();

        return $project;
    }

    public function test_the_project_folder_is_named_and_internal_commands_of_kekeli_are_hidden(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $project = $this->application($user);
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'ajoute la page profil']);
        $run = $project->agentRuns()->create(['message_id' => $message->id]);
        $run->steps()->create(['kind' => 'recherche', 'target' => '/home/user/projet']);
        $run->steps()->create(['kind' => 'fichier_lu', 'target' => '.']);
        $run->steps()->create(['kind' => 'commande', 'target' => 'touch /home/user/.kekeli/relancer']);
        $run->steps()->create(['kind' => 'commande', 'target' => 'php artisan migrate && touch /home/user/.kekeli/relancer']);
        $run->steps()->create(['kind' => 'commande', 'target' => 'php artisan test']);
        $run->forceFill(['status' => AgentRun::STATUS_COMPLETED, 'report' => ['done' => 'La page profil est prête.', 'note' => null, 'files' => [], 'files_count' => 0, 'tests' => [], 'problems' => []]])->save();

        $html = $this->actingAs($user)->get(route('projects.show', $project))->assertOk()->getContent();

        $this->assertStringContainsString('dossier du projet', $html);
        $this->assertStringNotContainsString('.kekeli', $html);
        $this->assertStringNotContainsString('relancer', $html);
        $this->assertStringContainsString('php artisan migrate', $html);
        $this->assertStringContainsString('php artisan test', $html);
        // La commande entièrement interne n'est ni montrée ni comptée.
        $this->assertStringContainsString('data-actions-count="4"', $html);

        $this->assertSame('', AgentStep::projectCommand('touch /home/user/.kekeli/relancer'));
        $this->assertSame('npm test', AgentStep::projectCommand('cd /home/user/projet/ && touch /home/user/.kekeli/x; npm test'));
        $this->assertSame('dossier du projet', AgentStep::cleanTarget('/home/user/projet'));
    }

    public function test_on_a_phone_get_the_code_is_an_icon_with_its_accessible_label(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $project = $this->application($user);

        $html = $this->actingAs($user)->get(route('projects.show', $project))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/data-publish-open="icon"/', $html);
        $this->assertStringContainsString('aria-label="Récupérer le code"', $html);
        // Le bouton complet est caché sur téléphone, montré sur ordinateur.
        $this->assertMatchesRegularExpression('/max-sm:hidden[^>]*data-publish-open[^=]|data-publish-open[^=][^>]*max-sm:hidden/s', $html);
    }

    public function test_the_discussion_takes_the_guessed_name_as_soon_as_the_plan_is_offered(): void
    {
        Bus::fake([SendAgentRun::class]);
        $user = User::factory()->create(['country' => 'TG']);

        // Nom écrit par le client : il devient le titre tout de suite.
        $this->model->callsToolWith('creer_application', ['plan' => self::PLAN, 'pile' => 'auto', 'confirme' => 'non']);
        Volt::actingAs($user)->test('site-prompt')->set('description', 'je veux une application de rendez-vous pour mon Salon de coiffure Belle Afi à Lomé')->call('build');
        $this->assertSame('Salon de coiffure Belle Afi', $user->projects()->latest('id')->first()->name);

        // Sans nom écrit : le nom court donné par le modèle avec le plan, jamais le début coupé du message.
        $this->model->callsToolWith('creer_application', ['plan' => self::PLAN, 'pile' => 'auto', 'confirme' => 'non', 'nom' => 'Rendez-vous du salon']);
        Volt::actingAs($user)->test('site-prompt')->set('description', 'il me faut une appli pour que mes clientes prennent rendez-vous en ligne')->call('build');
        $this->assertSame('Rendez-vous du salon', $user->projects()->latest('id')->first()->name);
    }

    public function test_the_build_instructions_speak_of_a_new_project_and_remove_the_leftovers_of_the_starting_point(): void
    {
        Bus::fake([SendAgentRun::class]);
        $user = User::factory()->create(['country' => 'TG']);
        $chat = $user->projects()->make(['name' => 'Discussion']);
        $chat->forceFill(['kind' => Project::KIND_CHAT])->save();
        $message = $chat->messages()->create(['role' => Message::ROLE_USER, 'content' => 'une application de rendez-vous']);

        $run = app(CreateApplication::class)->build($chat, $message, self::PLAN);

        $instructions = (string) $run->instructions;
        $this->assertStringContainsString('construction NEUVE', $instructions);
        $this->assertStringContainsString('jamais « votre application actuelle »', $instructions);
        $this->assertStringContainsString('Anciennes demandes', $instructions);
        $this->assertStringContainsString('Retire tout ce qui vient du point de départ', $instructions);
    }

    public function test_energy_used_up_shows_one_sober_line_like_claude_code(): void
    {
        $user = User::factory()->create(['country' => 'TG', 'plan' => 'gratuite']);
        $project = $this->application($user);
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'construis']);
        $project->agentRuns()->create(['message_id' => $message->id])->forceFill(['status' => AgentRun::STATUS_COMPLETED, 'model_cost_usd' => 1, 'finished_at' => now()])->save();

        $page = $this->actingAs($user)->get(route('projects.show', $project))->assertOk()
            ->assertSee('Énergie épuisée · Recharge à 00 h 00')
            ->assertSee('data-see-plans', false)
            ->assertDontSee('Vous ne voulez pas attendre');
        $this->assertSame(1, substr_count($page->getContent(), 'data-stop-line'));

        // Formule au mois : la date de recharge.
        $pro = User::factory()->create(['country' => 'TG', 'plan' => 'pro', 'plan_expires_at' => now()->addDays(20)]);
        $proProject = $this->application($pro);
        $proMessage = $proProject->messages()->create(['role' => Message::ROLE_USER, 'content' => 'construis']);
        $proProject->agentRuns()->create(['message_id' => $proMessage->id])->forceFill(['status' => AgentRun::STATUS_COMPLETED, 'model_cost_usd' => 20, 'finished_at' => now()])->save();
        $this->actingAs($pro)->get(route('projects.show', $proProject))->assertOk()
            ->assertSeeText('Énergie épuisée · Recharge le '.app(Energy::class)->rechargeText($pro));
        $this->assertMatchesRegularExpression('#^\d{2}/\d{2}/\d{4}$#', app(Energy::class)->rechargeText($pro));
    }

    public function test_a_construction_stops_at_the_openai_ceiling_instead_of_falling_back_then_continues(): void
    {
        Http::fake();
        $user = User::factory()->create(['country' => 'TG', 'plan' => 'pro', 'plan_expires_at' => now()->addDays(20)]);
        $project = $this->application($user);
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'ajoute la page profil']);
        $run = $project->agentRuns()->create(['message_id' => $message->id]);
        app(Settings::class)->set('openai_daily_cap_usd', 0);

        // Avant l'envoi : rien ne part vers le Runner, la construction s'arrête avec « Reprise à … ».
        (new SendAgentRun($run))->handle(app(RunnerClient::class));
        Http::assertNothingSent();
        $run->refresh();
        $this->assertSame(AgentRun::STATUS_STOPPED, $run->status);
        $this->assertSame(AgentRun::STOP_MODEL_CAP, $run->stop_reason);
        $this->actingAs($user)->get(route('projects.show', $project))->assertOk()
            ->assertSee('data-run-stopped="cap"', false)
            ->assertSee('Kekeli est très sollicité · Reprise à 00 h 00')
            ->assertDontSee('data-continue', false);
        Volt::actingAs($user)->test('projects.show', ['project' => $project])->call('continueRun', $run->id);
        $this->assertSame(AgentRun::STATUS_STOPPED, $run->fresh()->status);

        // Pendant le travail : le site demande au Runner de s'arrêter.
        $other = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'ajoute un menu']);
        $working = $project->agentRuns()->create(['message_id' => $other->id, 'status' => AgentRun::STATUS_RUNNING, 'sent_at' => now()]);
        $this->assertSame(['arreter' => true, 'raison' => AgentRun::STOP_MODEL_CAP], app(RunnerEventHandler::class)->stopRequest($working));
        $working->forceFill(['status' => AgentRun::STATUS_COMPLETED])->save();

        // Le lendemain (plafond remis à zéro), « Continuer » reprend : un travail jamais parti démarre sans reprise.
        app(Settings::class)->set('openai_daily_cap_usd', 1);
        $this->assertFalse(app(AnswerModels::class)->buildCapReached($user));
        Bus::fake([SendAgentRun::class]);
        $this->actingAs($user)->get(route('projects.show', $project))->assertSee('data-continue', false);
        Volt::actingAs($user)->test('projects.show', ['project' => $project])->call('continueRun', $run->id);
        $this->assertSame(AgentRun::STATUS_QUEUED, $run->fresh()->status);
        Bus::assertDispatched(SendAgentRun::class, fn (SendAgentRun $job) => $job->run->is($run) && ! $job->resume);
    }

    public function test_simple_questions_keep_the_free_fallback_when_the_openai_ceiling_is_reached(): void
    {
        $user = User::factory()->create(['country' => 'TG', 'plan' => 'pro', 'plan_expires_at' => now()->addDays(20)]);
        app(Settings::class)->set('openai_daily_cap_usd', 0);

        $providers = array_column(app(AnswerModels::class)->forRunner($user), 'fournisseur');
        $this->assertNotContains('openai', $providers);
        $this->assertContains('gemini', $providers);
    }
}
