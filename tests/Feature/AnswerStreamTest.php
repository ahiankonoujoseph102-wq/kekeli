<?php

namespace Tests\Feature;

use App\Agent\FirstResponder;
use App\Agent\RunnerEventHandler;
use App\Jobs\RespondToMessage;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Chantier « vitesse », parties 2 et 3 : envoi instantané, et réponse mot par mot ouverte par la page dès l'envoi,
 * sans file d'attente (la file d'attente ne répond qu'en secours).
 */
class AnswerStreamTest extends TestCase
{
    use RefreshDatabase;

    private function chat(User $user): Project
    {
        $chat = $user->projects()->make(['name' => 'Discussion']);
        $chat->forceFill(['kind' => Project::KIND_CHAT])->save();

        return $chat;
    }

    private function question(Project $chat, string $text = 'Salut'): AgentRun
    {
        $message = $chat->messages()->create(['role' => Message::ROLE_USER, 'content' => $text]);

        return $chat->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
    }

    public function test_sending_returns_the_request_and_the_queue_only_answers_later_as_a_fallback(): void
    {
        Queue::fake();
        $user = User::factory()->create(['country' => 'TG']);
        $chat = $this->chat($user);

        $page = Volt::actingAs($user)->test('projects.show', ['project' => $chat])->call('send', 'Salut');

        $run = AgentRun::query()->where('project_id', $chat->id)->sole();
        $page->assertReturned($run->id)->assertSet('content', '');
        $this->assertSame('Salut', $run->message->content);
        Queue::assertPushed(RespondToMessage::class, fn (RespondToMessage $job) => $job->run->is($run) && $job->delay !== null);

        // La page affiche tout de suite la place de la réponse en direct, ouverte dès l'arrivée.
        $this->actingAs($user)->get(route('projects.show', $chat))
            ->assertSee('data-reponse-en-cours="'.$run->id.'"', false)
            ->assertSee('data-ouvrir-flux', false)
            ->assertSee('data-envois-en-attente', false)
            ->assertSee('data-prompt-stop', false);
    }

    public function test_a_message_that_cannot_leave_returns_nothing_so_the_page_offers_to_try_again(): void
    {
        Queue::fake();
        $user = User::factory()->create(['country' => 'TG']);
        $chat = $this->chat($user);
        $chat->forceFill(['viewing_version' => 2])->save();

        Volt::actingAs($user)->test('projects.show', ['project' => $chat])->call('send', 'Salut')->assertReturned(null);
        $this->assertSame(0, AgentRun::query()->count());
    }

    public function test_the_answer_comes_word_by_word_then_is_saved(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $chat = $this->chat($user);
        $run = $this->question($chat);
        $this->model->answers('Bonjour, que voulez-vous créer aujourd’hui ?');

        $response = $this->actingAs($user)->post(route('projects.answers.stream', [$chat, $run]), ['chronos' => ['affiche' => 40, 'envoye' => 300]]);
        $response->assertOk();
        $this->assertStringContainsString('text/event-stream', (string) $response->headers->get('Content-Type'));
        $body = $response->streamedContent();

        $this->assertStringContainsString('"type":"debut"', $body);
        $this->assertGreaterThan(3, substr_count($body, '"type":"texte"'), 'plusieurs morceaux de texte');
        $this->assertStringContainsString('"type":"fin"', $body);
        $run->refresh();
        $this->assertSame(AgentRun::STATUS_COMPLETED, $run->status);
        $this->assertSame('Bonjour, que voulez-vous créer aujourd’hui ?', $run->report['done']);
        $this->assertSame(['affiche' => 40, 'envoye' => 300], $run->timings['navigateur']);
        $this->assertArrayHasKey('premier_mot', $run->timings['serveur']);

        // La file d'attente arrive ensuite (secours) : la réponse est déjà faite, rien n'est refait.
        (new RespondToMessage($run))->handle(app(FirstResponder::class), app(RunnerEventHandler::class));
        $this->assertSame(1, $chat->messages()->where('role', Message::ROLE_USER)->count());
        $this->assertCount(1, $this->model->received);
    }

    public function test_only_one_answer_at_a_time_and_only_for_the_owner(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $chat = $this->chat($user);
        $run = $this->question($chat);

        $this->actingAs(User::factory()->create())->post(route('projects.answers.stream', [$chat, $run]))->assertNotFound();

        // La page répond déjà (verrou pris) : la file d'attente ne fait rien.
        $lock = Cache::lock('kekeli:reponse:'.$run->id, 60);
        $lock->get();
        (new RespondToMessage($run))->handle(app(FirstResponder::class), app(RunnerEventHandler::class));
        $this->assertSame(AgentRun::STATUS_QUEUED, $run->fresh()->status);
        $this->assertCount(0, $this->model->received);
        $lock->release();

        // Une demande déjà finie : le flux ne fait que dire « fin ».
        $run->forceFill(['status' => AgentRun::STATUS_COMPLETED])->save();
        $body = $this->actingAs($user)->post(route('projects.answers.stream', [$chat, $run]))->streamedContent();
        $this->assertStringNotContainsString('"type":"texte"', $body);
        $this->assertStringContainsString('"type":"fin"', $body);
    }

    public function test_a_tool_is_announced_so_the_page_shows_the_work_at_once(): void
    {
        Queue::fake();
        $user = User::factory()->create(['country' => 'TG']);
        $chat = $this->chat($user);
        $run = $this->question($chat, 'Crée un site vitrine pour mon restaurant Le Palmier à Lomé, avec le menu et les horaires.');

        $body = $this->actingAs($user)->post(route('projects.answers.stream', [$chat, $run]))->streamedContent();

        $this->assertStringContainsString('"type":"outil"', $body);
        $this->assertFalse($chat->fresh()->isChat(), 'la discussion est devenue un projet de site');
    }
}
