<?php

namespace Tests\Feature;

use App\Agent\RunnerClient;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * « Copier » et « Modifier » sous les messages du client, comme Claude et ChatGPT (16 novembre 2026).
 */
class EditMessagesTest extends TestCase
{
    use RefreshDatabase;

    private function ask(User $user, string $question): Project
    {
        Volt::actingAs($user)->test('site-prompt')->set('description', $question)->call('build');

        return $user->projects()->latest('id')->firstOrFail();
    }

    public function test_each_client_message_can_be_copied_and_edited_and_the_discussion_starts_again_from_it(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->answers('Réponse sur Mixx by Yas.');
        $chat = $this->ask($user, 'Comment encaisser avec Mixx by Yas ?');
        $this->model->answers('Réponse sur Flooz.');
        Volt::actingAs($user)->test('projects.show', ['project' => $chat])->call('send', 'Et avec Flooz ?');
        $first = $chat->messages()->where('role', Message::ROLE_USER)->first();

        $page = $this->actingAs($user)->get(route('projects.show', $chat))->assertOk();
        $this->assertSame(2, substr_count($page->getContent(), 'data-copy-message'));
        $this->assertSame(2, substr_count($page->getContent(), 'data-edit-message='));

        // « Modifier » le premier message : son texte revient dans la zone ; à l'envoi, tout ce qui le suivait sort.
        $this->model->answers('Réponse sur Wave.');
        $component = Volt::actingAs($user)->test('projects.show', ['project' => $chat]);
        $this->assertSame('Comment encaisser avec Mixx by Yas ?', $component->call('startEdit', $first->id)->effects['returns'][0] ?? null);
        $component->assertSee('Vous modifiez votre message : la discussion repartira de lui.')
            ->call('send', 'Comment encaisser avec Wave ?')->assertSet('editing', null);

        $this->assertSame(['Comment encaisser avec Wave ?'], $chat->messages()->where('role', Message::ROLE_USER)->pluck('content')->all());
        $this->assertSame(['Réponse sur Wave.'], $chat->messages()->where('role', Message::ROLE_ASSISTANT)->pluck('content')->all());
        // Les anciens messages sont gardés (masqués), jamais relus par le modèle ni montrés.
        $this->assertSame(4, Message::query()->where('project_id', $chat->id)->whereNotNull('hidden_at')->count());
        $this->actingAs($user)->get(route('projects.show', $chat))->assertSee('Réponse sur Wave.')->assertDontSee('Réponse sur Flooz.')->assertDontSee('Et avec Flooz ?');
        $next = $chat->agentRuns()->create(['message_id' => $chat->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Merci'])->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
        $history = json_encode((fn () => $this->history($next))->call(app(RunnerClient::class)), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Wave', $history);
        $this->assertStringNotContainsString('Flooz', $history);
    }

    public function test_a_message_followed_by_a_work_on_the_site_cannot_be_edited_and_a_stranger_cannot_edit(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $site = $user->projects()->create(['name' => 'Boutique']);
        $message = $site->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Crée mon site']);
        $site->agentRuns()->create(['message_id' => $message->id, 'status' => AgentRun::STATUS_COMPLETED, 'report' => ['done' => 'Votre site est prêt.']]);

        $page = $this->actingAs($user)->get(route('projects.show', $site))->assertOk();
        $page->assertSee('data-copy-message', false)->assertDontSee('data-edit-message=', false);
        $this->assertNull(Volt::actingAs($user)->test('projects.show', ['project' => $site])->call('startEdit', $message->id)->effects['returns'][0] ?? null);
        Volt::actingAs(User::factory()->create())->test('projects.show', ['project' => $site])->assertStatus(404);
    }
}
