<?php

namespace Tests\Feature;

use App\Jobs\RespondToMessage;
use App\Jobs\SendAgentRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Créer comme Lovable et Claude (15 novembre 2026), partie 3 : une demande vague reçoit au plus 3 questions courtes,
 * avec des choix à toucher et « Choisis pour moi » ; ces questions ne coûtent pas d'énergie ; ensuite, Kekeli crée.
 */
class CreationQuestionsTest extends TestCase
{
    use RefreshDatabase;

    private const QUESTIONS = [
        ['question' => 'Quel est le nom de votre école ?', 'choix' => ['Lomé IA Academy', 'Formations IA Togo']],
        ['question' => 'Quelles formations voulez-vous vendre ?', 'choix' => ['Débutants en IA', 'IA pour entreprises', 'Création d’agents IA']],
        ['question' => 'Quel style préférez-vous ?', 'choix' => ['Sobre et moderne', 'Coloré et chaleureux']],
    ];

    public function test_a_vague_request_gets_questions_with_choices_and_choose_for_me_free_of_energy(): void
    {
        Bus::fake([SendAgentRun::class]);
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->asksQuestions('creer_site', self::QUESTIONS);

        Volt::actingAs($user)->test('site-prompt')->set('description', 'pouvez-vous me créer un site web pour vendre mes formations en intelligence artificielle')->call('build');
        $chat = $user->projects()->sole();
        $run = $chat->agentRuns()->sole();

        Bus::assertNotDispatched(SendAgentRun::class);
        $this->assertTrue((bool) $run->simple_chat, 'les questions ne coûtent pas d’énergie');
        $this->assertSame('creer_site', data_get($run->report, 'questions.tool'));
        $this->assertCount(3, data_get($run->report, 'questions.items'));

        $this->actingAs($user)->get(route('projects.show', $chat))->assertOk()
            ->assertSee('data-questions="3"', false)
            ->assertSee('Pour créer un site qui vous ressemble')
            ->assertSee('Quelles formations voulez-vous vendre ?')
            ->assertSee('IA pour entreprises')
            ->assertSee('Envoyer mes réponses')
            ->assertSee('data-choose-for-me', false)
            ->assertSee('Choisis pour moi');
        $this->assertSame(7, substr_count($this->actingAs($user)->get(route('projects.show', $chat))->getContent(), 'data-question-choice'));
    }

    public function test_after_the_questions_the_runner_is_told_not_to_ask_again(): void
    {
        config(['kekeli.runner.secret' => 'secret-de-test-tres-long-pour-les-signatures-0123456789', 'kekeli.runner.url' => 'http://runner.test']);
        Bus::fake([SendAgentRun::class]);
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->asksQuestions('creer_site', self::QUESTIONS);
        Volt::actingAs($user)->test('site-prompt')->set('description', 'crée-moi un site pour vendre mes formations en IA')->call('build');
        $chat = $user->projects()->sole();

        $this->useRealFirstResponder();
        Http::fake(['runner.test/repondre' => Http::response(['ok' => true, 'decision' => 'outil', 'outil' => 'creer_site', 'fournisseur' => 'gemini', 'modele' => 'gemini-3.5-flash-lite', 'tokens_entree' => 100, 'tokens_sortie' => 5, 'requetes' => 1, 'refus' => []])]);
        $message = $chat->messages()->create(['role' => 'user', 'content' => 'Choisis pour moi']);
        $run = $chat->agentRuns()->create(['message_id' => $message->id, 'purpose' => 'question']);
        RespondToMessage::dispatchSync($run);

        Http::assertSent(fn (Request $request) => $request->url() === 'http://runner.test/repondre' && ($request['contexte']['questions_posees'] ?? false) === true
            // L'outil prévu par les questions part avec, pour que Kekeli le lance (jamais « je ne peux pas »).
            && ($request['contexte']['outil_prevu'] ?? null) === 'creer_site');
        Bus::assertDispatched(SendAgentRun::class);
    }

    public function test_the_computer_of_a_showcase_site_starts_while_the_client_reads_the_questions(): void
    {
        config(['kekeli.runner.secret' => 'secret-de-test-tres-long-pour-les-signatures-0123456789', 'kekeli.runner.url' => 'http://runner.test']);
        Http::fake(['runner.test/preparer' => Http::response(['accepte' => true], 202)]);
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->asksQuestions('creer_site', self::QUESTIONS);
        Volt::actingAs($user)->test('site-prompt')->set('description', 'crée-moi un site pour mon restaurant')->call('build');
        $chat = $user->projects()->sole();

        Http::assertSent(fn (Request $request) => $request->url() === 'http://runner.test/preparer' && $request['projet'] === $chat->id && $request['fournisseurs'] !== []);

        // Une application (ou un site avec des comptes) ne démarre pas à l'avance : son socle dépend du plan.
        Http::fake(['runner.test/*' => Http::response(['accepte' => true], 202)]);
        $this->model->asksQuestions('creer_application', self::QUESTIONS);
        Volt::actingAs($user)->test('site-prompt')->set('description', 'je veux une application pour mon école')->call('build');
        Http::assertNotSent(fn (Request $request) => $request->url() === 'http://runner.test/preparer');
    }

    public function test_the_name_given_by_the_model_names_the_project_and_the_site(): void
    {
        Bus::fake([SendAgentRun::class]);
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->callsToolWith('creer_site', ['nom' => 'Chez Tante Akpéné']);
        Volt::actingAs($user)->test('site-prompt')->set('description', "fais-moi un site pour mon restaurant\nQuel est le nom de votre restaurant ? Chez Tante Akpéné")->call('build');

        $project = $user->projects()->sole();
        $this->assertSame('Chez Tante Akpéné', $project->name);
        $build = $project->agentRuns()->whereNull('purpose')->sole();
        $this->assertStringContainsString('Chez Tante Akpéné', (string) $build->instructions);
        $this->assertStringNotContainsString('Restaurant Quel', (string) $build->instructions);
    }

    public function test_questions_from_the_runner_are_checked_again_by_the_site(): void
    {
        $this->assertNull(RespondToMessage::questions(['outil' => 'creer_site', 'liste' => [['question' => 'Nom ?', 'choix' => ['A']]]], 'discussion'));
        $this->assertNull(RespondToMessage::questions(['outil' => 'modifier_site', 'liste' => self::QUESTIONS], 'discussion'));
        $this->assertNull(RespondToMessage::questions(['outil' => 'creer_site', 'liste' => self::QUESTIONS], 'site'));
        $checked = RespondToMessage::questions(['outil' => 'creer_site', 'liste' => [...self::QUESTIONS, ['question' => 'Une quatrième ?', 'choix' => ['A', 'B']]]], 'discussion');
        $this->assertCount(3, $checked['items']);
    }
}
