<?php

namespace Tests\Feature;

use App\Agent\RunnerClient;
use App\Jobs\RespondToMessage;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\ProviderUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Résumé automatique des longues discussions (invisible pour le client) : au-delà de 10 messages, seuls les 6 derniers
 * partent en entier ; le début est résumé par le modèle économique, par paquets, et le site garde le résumé.
 */
class ConversationSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['kekeli.runner.secret' => 'secret-de-test-tres-long-pour-les-signatures-0123456789', 'kekeli.runner.url' => 'http://runner.test']);
    }

    private function longDiscussion(User $user, int $count): array
    {
        $project = $user->projects()->make(['name' => 'Kafui Mode']);
        $project->forceFill(['kind' => Project::KIND_CHAT])->save();
        for ($i = 1; $i <= $count; $i++) {
            $project->messages()->create(['role' => $i % 2 ? Message::ROLE_USER : Message::ROLE_ASSISTANT, 'content' => $i === 3 ? 'Mon numéro WhatsApp est le +228 91 23 45 67.' : "Message $i"]);
        }
        $last = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Rappelle-moi mon numéro.']);
        $run = $project->agentRuns()->create(['message_id' => $last->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);

        return [$project, $run];
    }

    public function test_a_short_discussion_is_sent_whole_without_summary(): void
    {
        [, $run] = $this->longDiscussion(User::factory()->create(['country' => 'TG']), 8);
        Http::fake(['runner.test/*' => Http::response(['ok' => true, 'decision' => 'repondre', 'reponse' => 'Bonjour'], 200)]);
        app(RunnerClient::class)->respond($run, 'discussion');
        Http::assertSent(fn (Request $r) => count($r['historique']) === 8 && ! isset($r['resumer']) && ! isset($r['contexte']['resume']));
    }

    public function test_a_long_discussion_sends_the_last_six_messages_and_asks_the_economic_model_for_a_summary(): void
    {
        $user = User::factory()->create(['country' => 'TG', 'plan' => 'pro', 'plan_expires_at' => now()->addDays(10)]);
        [$project, $run] = $this->longDiscussion($user, 20);
        Http::fake(['runner.test/*' => Http::response(['ok' => true, 'decision' => 'repondre', 'reponse' => 'Votre numéro est le +228 91 23 45 67.'], 200)]);
        app(RunnerClient::class)->respond($run, 'discussion');
        Http::assertSent(function (Request $r) {
            return count($r['historique']) === 6
                && count($r['resumer']['messages']) === 14
                && str_contains($r['resumer']['messages'][2]['contenu'], '+228 91 23 45 67')
                // Le modèle économique, même pour un client Pro (le résumé est une petite tâche interne).
                && $r['resumer']['fournisseurs'][0]['modele'] === 'gpt-6-luna'
                && $r['fournisseurs'][0]['modele'] === 'gpt-6.1-terra';
        });

        // Le résumé reçu est gardé par le site, son coût compté comme une requête.
        $until = $project->messages()->orderBy('id')->skip(13)->value('id');
        $this->model->script[] = ['ok' => true, 'decision' => 'repondre', 'reponse' => 'Votre numéro est le +228 91 23 45 67.', 'fournisseur' => 'openai', 'modele' => 'gpt-6.1-terra', 'tokens_entree' => 2000, 'tokens_sortie' => 50, 'refus' => [],
            'resume' => ['texte' => "- Numéro WhatsApp : +228 91 23 45 67\n- Boutique Kafui Mode", 'jusqu_a' => $until, 'consommation' => ['fournisseur' => 'openai', 'modele' => 'gpt-6-luna', 'tokens_entree' => 1200, 'tokens_sortie' => 80, 'requetes' => 1]]];
        app()->call([new RespondToMessage($run), 'handle']);
        $project->refresh();
        $this->assertStringContainsString('+228 91 23 45 67', $project->conversation_summary);
        $this->assertSame($until, (int) $project->summary_until_message_id);
        $this->assertSame(1, (int) ProviderUsage::query()->where('model', 'gpt-6-luna')->sum('requests'));
        // Le client ne voit jamais le résumé.
        $this->actingAs($user)->get(route('projects.show', $project))->assertDontSee('Numéro WhatsApp : +228');

        // Message suivant : le résumé part dans le contexte, sans être refait (moins de 4 nouveaux messages).
        $next = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Merci']);
        $run2 = $project->agentRuns()->create(['message_id' => $next->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
        Http::fake(['runner.test/*' => Http::response(['ok' => true, 'decision' => 'repondre', 'reponse' => 'Avec plaisir'], 200)]);
        app(RunnerClient::class)->respond($run2, 'discussion');
        Http::assertSent(fn (Request $r) => str_contains((string) ($r['contexte']['resume'] ?? ''), '+228 91 23 45 67') && ! isset($r['resumer']));
    }
}
