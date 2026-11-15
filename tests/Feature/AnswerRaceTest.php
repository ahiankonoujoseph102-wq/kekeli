<?php

namespace Tests\Feature;

use App\Agent\AnswerModels;
use App\Agent\FirstResponder;
use App\Agent\RunnerEventHandler;
use App\Jobs\RespondToMessage;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\ProviderUsage;
use App\Models\User;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Chantier « premier mot » (15 novembre 2026) : la course entre fournisseurs. Le site dit au Runner quel coureur
 * part, et au bout de combien de temps ; il compte aussi la demande annulée, dans la consommation et dans le plafond
 * OpenAI du jour.
 */
class AnswerRaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_runner_is_gpt_terra_at_openai_after_one_second_and_the_free_gemini_once_the_ceiling_is_reached(): void
    {
        $models = app(AnswerModels::class);
        $this->assertSame(['fournisseur' => 'openai', 'modele' => 'gpt-6.1-terra', 'delai_ms' => 1000], $models->raceFor());

        ProviderUsage::create(['day' => CarbonImmutable::now('UTC')->toDateString(), 'provider' => 'openai', 'model' => 'gpt-6.1-terra'])->forceFill(['cost_usd' => 5.0])->save();
        $this->assertSame(['fournisseur' => 'gemini', 'modele' => 'gemini-3.1-flash-lite', 'delai_ms' => 1000, 'reflexion' => 'minimal'], $models->raceFor());

        app(Settings::class)->set('answer_race_delay_ms', 0);
        $this->assertNull($models->raceFor(), '0 = pas de course');
    }

    public function test_only_an_allowed_runner_can_be_chosen_in_the_administration(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Volt::actingAs($admin)->test('admin.settings')->set('raceModel', 'openai/gpt-6.1-sol')->call('save')->assertHasErrors('raceModel');
        Volt::actingAs($admin)->test('admin.settings')->set('raceModel', 'openai/gpt-6-luna')->set('general.answer_race_delay_ms', 1500)->call('save')->assertHasNoErrors();
        $this->assertSame(['fournisseur' => 'openai', 'modele' => 'gpt-6-luna', 'delai_ms' => 1500], app(AnswerModels::class)->raceFor());
    }

    public function test_the_cancelled_request_is_counted_with_its_provider_and_the_race_is_noted(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $chat = $user->projects()->make(['name' => 'Discussion']);
        $chat->forceFill(['kind' => Project::KIND_CHAT])->save();
        $message = $chat->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Salut']);
        $run = $chat->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
        $this->model->script[] = ['ok' => true, 'decision' => 'repondre', 'reponse' => 'Bonjour !', 'fournisseur' => 'coarena', 'modele' => 'gpt-6-1-terra', 'tokens_entree' => 200, 'tokens_cache' => 9000, 'tokens_sortie' => 10, 'refus' => [],
            'course' => ['lancee' => true, 'delai_ms' => 1000, 'gagnant' => ['fournisseur' => 'coarena', 'modele' => 'gpt-6-1-terra'], 'perdant' => ['fournisseur' => 'openai', 'modele' => 'gpt-6.1-terra'], 'premier_contenu_ms' => 1180, 'tokens_entree_estimes' => 9200, 'tokens_cache_estimes' => 9000]];

        (new RespondToMessage($run))->handle(app(FirstResponder::class), app(RunnerEventHandler::class));

        $openai = ProviderUsage::query()->where('provider', 'openai')->sole();
        $this->assertSame(1, (int) $openai->requests);
        $this->assertGreaterThan(0, (float) $openai->cost_usd, 'compté dans le plafond OpenAI du jour');
        $this->assertSame(['lancee' => true, 'gagnant' => 'coarena', 'modele' => 'gpt-6-1-terra', 'premier_contenu_ms' => 1180], $run->fresh()->timings['course']);
        $this->assertSame('Bonjour !', $run->fresh()->report['done']);
    }
}
