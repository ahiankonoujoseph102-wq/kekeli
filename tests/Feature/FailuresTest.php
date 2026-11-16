<?php

namespace Tests\Feature;

use App\Agent\Failures;
use App\Agent\RunnerEventHandler;
use App\Agent\RunnerSignature;
use App\Agent\Tools\CreateVideo;
use App\Jobs\RespondToMessage;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Quand Kekeli ne peut pas faire une action, il le dit comme Claude Code (15 novembre 2026) : ce qui s'est passé,
 * pourquoi, ce qui est gardé, et ce qu'il propose (« Réessayer », une autre façon de faire, l'heure du retour).
 */
class FailuresTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'secret-de-test-tres-long-pour-les-signatures-0123456789';

    protected function setUp(): void
    {
        parent::setUp();
        config(['kekeli.runner.secret' => self::SECRET, 'kekeli.runner.url' => 'http://runner.test']);
    }

    public function test_gemini_quota_used_up_the_failure_says_why_and_offers_to_try_again(): void
    {
        Queue::fake();
        [$user, $project, $run] = $this->workRun();

        $this->event($run, ['type' => 'erreur', 'raison' => 'aucun_fournisseur', 'detail' => '429 RESOURCE_EXHAUSTED gemini-3.5-flash-lite'])->assertOk();

        $report = $run->fresh()->report;
        $this->assertSame('Kekeli n’a pas pu finir : ses modèles d’intelligence artificielle sont tous très sollicités en ce moment. Rien n’est perdu. Touchez « Réessayer » dans quelques minutes.', $report['done']);
        $this->assertTrue($report['retry']);
        $this->assertNull($report['note']);
        $page = $this->actingAs($user)->get(route('projects.show', $project))->assertOk();
        $page->assertSee('data-retry', false)->assertDontSee('RESOURCE_EXHAUSTED')->assertDontSee('gemini');

        // « Réessayer » : le même message repart vers le premier répondant, rien n'est effacé.
        Volt::actingAs($user)->test('projects.show', ['project' => $project])->call('retry', $run->id);
        $again = $project->agentRuns()->where('id', '!=', $run->id)->sole();
        $this->assertSame($run->message_id, $again->message_id);
        $this->assertSame(AgentRun::PURPOSE_QUESTION, $again->purpose);
        Queue::assertPushed(RespondToMessage::class, fn (RespondToMessage $job) => $job->run->is($again));
        $this->assertFalse($run->fresh()->report['retry']);
    }

    public function test_cloudflare_empty_the_failure_gives_the_return_time_and_another_way(): void
    {
        [$user, $project, $run] = $this->workRun();

        $this->event($run, ['type' => 'erreur', 'raison' => 'images_indisponibles', 'detail' => 'daily free allocation of 10,000 neurons'])->assertOk();

        $report = $run->fresh()->report;
        $this->assertStringContainsString('a atteint sa limite du jour et revient à 00 h 00', $report['done']);
        $this->assertStringContainsString('vos propres photos avec « + »', $report['done']);
        // Réessayer tout de suite ne servirait à rien : pas de bouton, l'heure du retour est donnée.
        $this->assertFalse($report['retry']);
        $this->actingAs($user)->get(route('projects.show', $project))->assertDontSee('data-retry', false)->assertDontSee('neurons');
    }

    public function test_a_site_that_failed_says_it_went_back_to_its_last_working_version(): void
    {
        [, $project, $run] = $this->workRun();
        $project->versions()->create(['number' => 1, 'title' => 'Première version', 'size' => 10]);

        $this->event($run, ['type' => 'erreur', 'raison' => 'ordinateur', 'detail' => 'injoignable'])->assertOk();

        $this->assertSame('L’ordinateur de travail de Kekeli a cessé de répondre. Votre projet est revenu à sa dernière version qui fonctionnait. Touchez « Réessayer » : un nouvel ordinateur sera préparé.', $run->fresh()->report['done']);
    }

    public function test_every_known_failure_has_its_own_explanation_and_never_a_technical_word(): void
    {
        $sentences = collect(['aucun_fournisseur', 'images_indisponibles', 'trop_lourd', 'duree', 'ordinateur', 'atelier', 'scenario', 'rendu', 'agent', 'interne', 'flux', ''])
            ->map(fn (string $reason) => Failures::explain($reason.' : détail', null));

        foreach ($sentences as $sentence) {
            $this->assertDoesNotMatchRegularExpression('/\b(erreur|error|429|API|Gemini|Cloudflare|OpenAI|gratuit|quota)\b/iu', $sentence);
            $this->assertLessThanOrEqual(3, preg_match_all('/[.!?](\s|$)/u', $sentence), $sentence);
        }
        $this->assertSame('aucun_fournisseur', Failures::reason('aucun_fournisseur : 429'));
        $this->assertFalse(Failures::retryable('images_indisponibles : x'));
        $this->assertTrue(Failures::retryable('agent : x'));
    }

    public function test_an_impossible_request_is_answered_with_what_kekeli_can_do_instead(): void
    {
        $user = User::factory()->create();
        $project = tap($user->projects()->create(['name' => 'Discussion']), fn (Project $p) => $p->forceFill(['kind' => Project::KIND_CHAT])->save());
        $run = $this->question($project, 'Appelle mon client Koffi au 90 00 00 00 pour lui dire que sa commande est prête.');
        $this->model->answers('Je ne peux pas appeler votre client : je n’ai pas accès au téléphone. Voici le message prêt à lui envoyer sur WhatsApp : « Bonjour Koffi, votre commande est prête. »');

        (new RespondToMessage($run))->handle($this->model, app(RunnerEventHandler::class));

        $this->assertSame(AgentRun::STATUS_COMPLETED, $run->fresh()->status);
        $answer = Message::where('role', Message::ROLE_ASSISTANT)->sole()->content;
        $this->assertStringContainsString('Je ne peux pas appeler votre client', $answer);
        $this->assertStringContainsString('Voici le message prêt', $answer);
    }

    public function test_a_function_not_available_here_says_where_to_ask(): void
    {
        Bus::fake();
        $user = User::factory()->create();
        $project = $user->projects()->create(['name' => 'Affiche du marché']);
        $project->forceFill(['kind' => Project::KIND_DOCUMENT, 'document_type' => 'image'])->save();
        $run = $this->question($project, 'Fais-moi une vidéo de cette affiche');
        // Le modèle choisit un outil de la discussion, qui n'existe pas dans un projet d'image.
        $this->model->callsTool('creer_video');

        (new RespondToMessage($run))->handle($this->model, app(RunnerEventHandler::class));

        $this->assertStringContainsString('Touchez « Nouvelle discussion » et demandez-le là', Message::where('role', Message::ROLE_ASSISTANT)->sole()->content);
        $this->assertSame(Project::KIND_DOCUMENT, $project->fresh()->kind);
    }

    public function test_a_function_in_early_access_says_so_instead_of_a_generic_sentence(): void
    {
        Bus::fake();
        app(Settings::class)->set('early_access_tools', ['creer_video']);
        $user = User::factory()->create();
        $project = tap($user->projects()->create(['name' => 'Discussion']), fn (Project $p) => $p->forceFill(['kind' => Project::KIND_CHAT])->save());
        $run = $this->question($project, 'Une publicité vidéo pour ma boutique');
        $this->model->callsTool('creer_video');

        (new RespondToMessage($run))->handle($this->model, app(RunnerEventHandler::class));

        $this->assertStringContainsString('en avant-première pour les formules Expert et Agence', Message::where('role', Message::ROLE_ASSISTANT)->sole()->content);
    }

    public function test_the_voice_chosen_by_the_client_goes_to_the_runner(): void
    {
        $this->assertSame(['femme', 'homme', 'client'], CreateVideo::VOICES);
        $this->assertSame(['femme' => 'Kore', 'homme' => 'Charon'], app(Settings::class)->get('video_voices_by_gender'));
        $this->assertCount(4, (array) app(Settings::class)->get('video_free_voices'));
    }

    /**
     * @return array{0: User, 1: Project, 2: AgentRun}
     */
    private function workRun(): array
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Crée un site pour ma boutique']);
        $run = $project->agentRuns()->create(['message_id' => $message->id, 'status' => AgentRun::STATUS_RUNNING]);

        return [$user, $project, $run];
    }

    private function question(Project $project, string $text): AgentRun
    {
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => $text]);

        return $project->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
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
