<?php

namespace Tests\Feature;

use App\Agent\AgentPlaces;
use App\Agent\RunnerClient;
use App\Agent\RunnerEventHandler;
use App\Jobs\AnswerQuestion;
use App\Jobs\SendAgentRun;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\ProviderUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Questions in the discussion: Kekeli answers without changing the site, like Lovable and Claude Code.
 */
class QuestionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['kekeli.runner.secret' => 'secret-de-test-tres-long-pour-les-signatures-0123456789', 'kekeli.runner.url' => 'http://runner.test']);
    }

    public function test_a_question_is_answered_without_a_request_to_change_the_site(): void
    {
        Queue::fake();
        [$user, $project] = $this->webApp();

        Volt::actingAs($user)->test('projects.show', ['project' => $project])
            ->set('content', 'Où héberger mon site ?')
            ->call('send')
            ->assertHasNoErrors()
            ->assertSet('content', '');

        $run = $project->agentRuns()->sole();
        $this->assertSame(AgentRun::PURPOSE_QUESTION, $run->purpose);
        Queue::assertPushed(AnswerQuestion::class, fn (AnswerQuestion $job) => $job->run->is($run) && ! $job->doubt);
        Queue::assertNotPushed(SendAgentRun::class);

        // Une demande de modification part toujours vers l'agent.
        $run->update(['status' => AgentRun::STATUS_COMPLETED]);
        Volt::actingAs($user)->test('projects.show', ['project' => $project])->set('content', 'Ajoute un menu du jour')->call('send');
        Queue::assertPushed(SendAgentRun::class);
    }

    public function test_the_answer_comes_from_the_model_only_without_a_computer_and_counts_very_little_energy(): void
    {
        [$user, $project] = $this->webApp();
        $project->forceFill(['preview_url' => 'https://4100-essai.e2b.app/', 'preview_expires_at' => now()->subMinute()])->save();
        $before = $project->fresh()->only(['backup_at', 'preview_url', 'published_url', 'updated_at']);
        $run = $this->question($project, 'Où héberger mon site ?');

        Http::fake([
            'runner.test/question' => Http::response([
                'ok' => true,
                'reponse' => "Votre site a besoin d'un hébergeur qui accepte PHP et MySQL. Trois choix :\n- un hébergement mutualisé (3 à 10 dollars par mois, prix à vérifier) ;\n- Laravel Cloud ;\n- un hébergeur de votre pays. 🙂",
                'fournisseur' => 'gemini', 'modele' => 'gemini-3.5-flash-lite',
                'tokens_entree' => 1450, 'tokens_sortie' => 180, 'tokens_reflexion' => 0, 'tokens_cache' => 0,
                'refus' => [['fournisseur' => 'gemini', 'modele' => 'gemini-3.1-flash-lite']],
            ]),
            '*' => Http::response([], 500),
        ]);

        (new AnswerQuestion($run))->handle(app(RunnerClient::class), app(RunnerEventHandler::class));

        // Une seule requête, vers /question : aucune tâche, aucun ordinateur, aucun fichier touché.
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->url() === 'http://runner.test/question'
            && $request['question'] === 'Où héberger mon site ?'
            && $request['contexte']['type'] === 'laravel'
            && $request['contexte']['pays'] === 'Togo'
            && $request['contexte']['doute'] === false);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/taches'));

        $run->refresh();
        $this->assertSame(AgentRun::STATUS_COMPLETED, $run->status);
        $this->assertTrue($run->report['question']);
        $this->assertSame([], $run->report['files']);
        $this->assertSame(1, $run->requests);
        $this->assertSame(1450, $run->input_tokens);
        $this->assertSame(0, $run->computer_seconds);
        $this->assertEquals(0.0, (float) $run->computer_cost_usd);
        $this->assertSame(0, $run->steps()->count());
        $this->assertSame(1, ProviderUsage::where('model', 'gemini-3.5-flash-lite')->value('requests'));
        $this->assertSame(1, ProviderUsage::where('model', 'gemini-3.1-flash-lite')->value('rate_limited'));
        $this->assertSame($before['backup_at']?->toIso8601String(), $project->fresh()->backup_at?->toIso8601String());
        $this->assertSame($before['preview_url'], $project->fresh()->preview_url);
        $this->assertNull($project->fresh()->published_url);

        // La réponse s'affiche telle quelle (listes comprises), sans émoji ni « Kekeli a fait X actions ».
        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertSee('data-question-answer', false)
            ->assertSee('un hébergement mutualisé (3 à 10 dollars par mois, prix à vérifier)')
            ->assertSee('Laravel Cloud')
            ->assertDontSee('🙂')
            ->assertDontSee('Kekeli a fait')
            ->assertDontSee('Kekeli réfléchit');
    }

    public function test_when_in_doubt_kekeli_answers_and_asks_whether_to_change_the_site(): void
    {
        Queue::fake();
        [$user, $project] = $this->webApp();

        Volt::actingAs($user)->test('projects.show', ['project' => $project])->set('content', 'Faut-il changer la couleur ?')->call('send');
        Queue::assertPushed(AnswerQuestion::class, fn (AnswerQuestion $job) => $job->doubt);
        Queue::assertNotPushed(SendAgentRun::class);

        Http::fake(['runner.test/question' => Http::response(['ok' => true, 'reponse' => 'Un vert foncé irait bien avec votre logo. Voulez-vous que je modifie votre site dans ce sens ?', 'fournisseur' => 'gemini', 'modele' => 'gemini-3.5-flash-lite', 'tokens_entree' => 900, 'tokens_sortie' => 40])]);
        (new AnswerQuestion($project->agentRuns()->sole(), true))->handle(app(RunnerClient::class), app(RunnerEventHandler::class));
        Http::assertSent(fn (Request $request) => $request['contexte']['doute'] === true);
        $this->assertStringContainsString('Voulez-vous que je modifie votre site', Message::where('role', Message::ROLE_ASSISTANT)->sole()->content);
    }

    public function test_a_question_that_cannot_be_answered_is_said_softly(): void
    {
        [$user, $project] = $this->webApp();
        $run = $this->question($project, 'Combien ça coûte ?');
        Http::fake(['runner.test/question' => Http::response(['ok' => false, 'refus' => []], 503)]);

        (new AnswerQuestion($run))->handle(app(RunnerClient::class), app(RunnerEventHandler::class));

        $this->assertSame(AgentRun::STATUS_FAILED, $run->fresh()->status);
        $this->actingAs($user)->get(route('projects.show', $project))->assertSee('data-run-failed', false);
    }

    public function test_a_question_never_takes_a_place_in_the_waiting_line(): void
    {
        [, $project] = $this->webApp();
        $run = $this->question($project, 'Où héberger mon site ?');

        $this->assertNull(app(AgentPlaces::class)->position($run));
    }

    /**
     * @return array{User, Project}
     */
    private function webApp(): array
    {
        $user = User::factory()->create(['country' => 'TG']);
        $project = $user->projects()->create(['name' => 'Pressing Éclat']);
        $project->forceFill(['kind' => Project::KIND_WEBAPP, 'app_stack' => 'laravel', 'app_template' => 'socle'])->save();

        return [$user, $project];
    }

    private function question(Project $project, string $text): AgentRun
    {
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => $text]);

        return $project->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
    }
}
