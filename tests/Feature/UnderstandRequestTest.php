<?php

namespace Tests\Feature;

use App\Jobs\SendAgentRun;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Chantier « comprendre, répondre et réagir comme Claude et ChatGPT » (15 novembre 2026), partie 1 : une question reçoit
 * une réponse ; à la fin, Kekeli peut proposer en une phrase de créer ce dont il a parlé, avec un bouton qui répond
 * « Oui, créer… » comme un message écrit. Rien n'est créé sans que le client le demande.
 */
class UnderstandRequestTest extends TestCase
{
    use RefreshDatabase;

    private function ask(User $user, string $text): Project
    {
        Volt::actingAs($user)->test('site-prompt')->set('description', $text)->call('build');

        return $user->projects()->latest('id')->firstOrFail();
    }

    public function test_a_question_gets_an_answer_and_a_button_to_create_what_was_discussed(): void
    {
        Bus::fake([SendAgentRun::class]);
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->answersAndSuggests("**Oui**, c’est possible aujourd’hui.\n\nVoulez-vous que je crée cet agent pour votre boutique ?", 'creer_agent');
        $chat = $this->ask($user, "C'est possible de créer un agent IA aujourd'hui avec l'IA ? Et comment gagner de l'argent avec l'IA");

        $chat->refresh();
        $this->assertTrue($chat->isChat(), 'une question ne crée rien');
        Bus::assertNotDispatched(SendAgentRun::class);
        $run = $chat->agentRuns()->sole();
        $this->assertSame('creer_agent', $run->report['suggestion']);
        $this->assertNull($run->report['offer'], 'jamais deux boutons');
        $this->actingAs($user)->get(route('projects.show', $chat))
            ->assertSee('data-suggestion="creer_agent"', false)
            ->assertSee('Oui, créer l’agent')
            ->assertDontSee('data-accept-offer', false)
            ->assertDontSee('Construire ce plan');
    }

    public function test_a_site_suggestion_never_shows_the_old_site_offer_button_too(): void
    {
        Bus::fake([SendAgentRun::class]);
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->answersAndSuggests('Un site vitrine présente votre activité. Voulez-vous que je crée le site de votre pressing ?', 'creer_site');
        $chat = $this->ask($user, 'Quelle différence entre un site vitrine et une boutique en ligne ?');

        $run = $chat->agentRuns()->sole();
        $this->assertSame('creer_site', $run->report['suggestion']);
        $this->assertNull($run->report['offer']);
        $this->actingAs($user)->get(route('projects.show', $chat))->assertSee('data-suggestion="creer_site"', false)->assertSee('Oui, créer le site');
    }

    public function test_an_unknown_tool_is_never_offered(): void
    {
        Bus::fake([SendAgentRun::class]);
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->answersAndSuggests('Voici la réponse.', 'modifier_site');
        $chat = $this->ask($user, 'C’est quoi une API ?');

        $this->assertNull($chat->agentRuns()->sole()->report['suggestion']);
    }
}
