<?php

namespace Tests\Feature;

use App\Agent\Energy;
use App\Agent\RunnerClient;
use App\Jobs\SendAgentRun;
use App\Models\AgentRun;
use App\Models\Project;
use App\Models\ProviderUsage;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * « Une formule gratuite généreuse et rapide, comme Lovable » (décision du porteur du projet du 15 novembre 2026) :
 * une discussion simple (réponse sans aucun outil) ne retire rien à l'énergie du client, avec un nombre maximum par
 * jour et par formule ; son coût compte toujours dans le plafond global des comptes gratuits.
 */
class FreeFormulaTest extends TestCase
{
    use RefreshDatabase;

    private function chat(User $user, string $text): Project
    {
        Volt::actingAs($user)->test('site-prompt')->set('description', $text)->call('build');

        return $user->projects()->latest('id')->firstOrFail();
    }

    private function say(User $user, Project $project, string $text): void
    {
        Volt::actingAs($user)->test('projects.show', ['project' => $project])->call('send', $text)->assertHasNoErrors();
    }

    public function test_a_greeting_does_not_use_energy_but_counts_in_the_discussions_of_the_day(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $energy = app(Energy::class);
        $this->assertSame(100, $energy->simpleChatsLeft($user));

        $this->model->answers('Bonjour ! Comment puis-je vous aider ?');
        $chat = $this->chat($user, 'Salut');

        $run = $chat->agentRuns()->sole();
        $this->assertTrue($run->simple_chat);
        $this->assertGreaterThan(0, $run->model_cost_usd, 'le coût réel reste enregistré');
        $this->assertSame(0.0, $energy->usedFcfa($user));
        $this->assertSame(100, $energy->percentLeft($user));
        $this->assertSame(99, $energy->simpleChatsLeft($user));

        // Le menu du compte : la barre ne bouge pas, « Discussions du jour : 99 restantes ».
        $this->actingAs($user)->get(route('projects.show', $chat))
            ->assertSee('data-account-chats="99"', false)
            ->assertSee('Discussions du jour')
            ->assertSee('99 restantes')
            ->assertSee('style="width: 100%"', false);
    }

    public function test_a_search_or_a_creation_uses_energy_as_before(): void
    {
        Bus::fake([SendAgentRun::class]);
        $user = User::factory()->create(['country' => 'TG']);
        $energy = app(Energy::class);

        $this->model->answersWithSources('Le prix du ciment est de 5 000 FCFA [1].', [['titre' => 'Prix', 'url' => 'https://exemple.tg/prix']]);
        $chat = $this->chat($user, 'Quel est le prix du ciment à Lomé en ce moment ?');
        $this->assertFalse($chat->agentRuns()->sole()->simple_chat, 'une recherche sur Internet n’est pas une discussion simple');
        $afterSearch = $energy->usedFcfa($user);
        $this->assertGreaterThan(0, $afterSearch);

        $this->model->callsTool('creer_site');
        $this->say($user, $chat, 'Crée le site de mon restaurant Le Palmier à Lomé');
        $decision = $chat->agentRuns()->where('message_id', $chat->messages()->where('content', 'Crée le site de mon restaurant Le Palmier à Lomé')->value('id'))->where('purpose', AgentRun::PURPOSE_QUESTION)->sole();
        $this->assertFalse($decision->simple_chat, 'la décision qui lance une création compte');
        $this->assertGreaterThan($afterSearch, $energy->usedFcfa($user));
        $this->assertSame(100, $energy->simpleChatsLeft($user));
        Bus::assertDispatched(SendAgentRun::class);

        // Le travail du site compte comme avant (modèle et ordinateur).
        $work = $chat->fresh()->agentRuns()->whereNull('purpose')->sole();
        $work->forceFill(['model_cost_usd' => 0.05, 'computer_cost_usd' => 0.01])->save();
        $this->assertEqualsWithDelta($afterSearch + $decision->fresh()->model_cost_usd * 590 + 0.06 * 590, $energy->usedFcfa($user), 0.5);
    }

    public function test_a_confirmation_question_before_a_creation_is_a_simple_discussion(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->asksToConfirm('creer_site', 'Voulez-vous que je crée le site maintenant ?');
        $chat = $this->chat($user, 'site site site');

        $this->assertTrue($chat->agentRuns()->sole()->simple_chat);
        $this->assertSame(0.0, app(Energy::class)->usedFcfa($user));
    }

    public function test_the_daily_number_of_each_formula_is_a_safeguard(): void
    {
        $settings = app(Settings::class);
        $plans = $settings->get('plans');
        $this->assertSame([100, 300, 600, 1000], array_map(fn ($key) => $plans[$key]['simple_chats_per_day'], ['gratuite', 'pro', 'expert', 'agence']));
        $plans['gratuite']['simple_chats_per_day'] = 2;
        $settings->set('plans', $plans);

        $user = User::factory()->create(['country' => 'TG']);
        $energy = app(Energy::class);
        $this->model->answers('Bonjour !')->answers('Lomé.')->answers('Réponse au-delà du nombre du jour');
        $chat = $this->chat($user, 'Salut');
        $this->say($user, $chat, 'Quelle est la capitale du Togo ?');
        $this->assertSame(0, $energy->simpleChatsLeft($user));
        $this->assertSame(0.0, $energy->usedFcfa($user));

        $this->say($user, $chat, 'Et celle du Bénin ?');
        $last = $chat->agentRuns()->where('message_id', $chat->messages()->where('content', 'Et celle du Bénin ?')->value('id'))->sole();
        $this->assertFalse($last->simple_chat, 'au-delà, la discussion compte de nouveau dans l’énergie');
        $this->assertTrue($last->report['chat_limit']);
        $this->assertStringContainsString('Vous avez utilisé toutes vos discussions du jour. Elles reviennent à 00 h 00.', $last->report['done']);
        $this->assertStringNotContainsString('Réponse au-delà', $last->report['done']);
        $this->assertGreaterThan(0, $energy->usedFcfa($user));
        $this->actingAs($user)->get(route('projects.show', $chat))->assertSee('data-chat-limit', false)->assertSee('Voir les formules')
            ->assertSee('data-account-chats="0"', false)->assertSee('aucune restante');

        // Le lendemain (heure de Lomé), les discussions reviennent.
        $this->travelTo(now('Africa/Lome')->addDay()->startOfDay()->addMinute());
        $this->assertSame(2, $energy->simpleChatsLeft($user));
    }

    public function test_a_paid_formula_sees_no_formulas_link_at_the_limit(): void
    {
        $settings = app(Settings::class);
        $plans = $settings->get('plans');
        $plans['pro']['simple_chats_per_day'] = 0;
        $settings->set('plans', $plans);
        $user = User::factory()->create(['country' => 'TG']);
        $user->forceFill(['plan' => 'pro', 'plan_expires_at' => now()->addDays(20)])->save();

        $this->model->answers('Bonjour !');
        $chat = $this->chat($user, 'Salut');
        $this->assertTrue($chat->agentRuns()->sole()->report['chat_limit']);
        $this->actingAs($user)->get(route('projects.show', $chat))->assertDontSee('data-chat-limit', false);
    }

    public function test_simple_discussions_still_count_in_the_ceiling_of_all_free_users(): void
    {
        $settings = app(Settings::class);
        $settings->set('free_daily_cap_fcfa', 1);
        $energy = app(Energy::class);

        $first = User::factory()->create(['country' => 'TG']);
        $this->model->answers(str_repeat('Une longue réponse. ', 20));
        $this->chat($first, 'Explique-moi ce qu’est un site web');
        $run = AgentRun::query()->sole();
        $this->assertTrue($run->simple_chat);
        $run->forceFill(['model_cost_usd' => 0.002])->save();

        $this->assertSame(0.0, $energy->usedFcfa($first), 'rien n’est retiré à l’énergie du client');
        $this->assertEqualsWithDelta(1.18, $energy->freeUsedTodayFcfa(), 0.01, 'mais le plafond global des gratuits le compte');
        $this->assertTrue($energy->freeCapReached());

        // Plafond global atteint : un autre compte gratuit attend minuit, comme avant (dépense maximale inchangée).
        $other = User::factory()->create(['country' => 'TG']);
        $chat = $this->chat($other, 'Salut');
        $this->assertSame(0, $chat->agentRuns()->count());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function providersSentFor(User $user, string $text): array
    {
        $this->useRealFirstResponder();
        config(['kekeli.settings.model_providers' => ['openai' => true, 'gratuits' => true, 'freellmapi' => true]]);
        config(['kekeli.runner.url' => 'http://runner.test', 'kekeli.runner.secret' => 'secret-de-test']);
        $sent = [];
        Http::fake(['runner.test/repondre' => function ($request) use (&$sent) {
            $sent = $request->data()['fournisseurs'];

            return Http::response(['ok' => true, 'decision' => 'repondre', 'reponse' => 'Bonjour !', 'fournisseur' => 'openai', 'modele' => 'gpt-6.1-terra', 'refus' => []]);
        }]);
        $chat = $user->projects()->make(['name' => 'Question']);
        $chat->forceFill(['kind' => Project::KIND_CHAT])->save();
        $message = $chat->messages()->create(['role' => 'user', 'content' => $text]);
        app(RunnerClient::class)->respond($chat->agentRuns()->create(['message_id' => $message->id, 'purpose' => 'question']), 'discussion');

        return $sent;
    }

    public function test_a_free_account_gets_its_simple_answer_from_the_free_gemini_then_freellmapi_and_openai_last(): void
    {
        // Décisions du porteur du projet (15 novembre 2026) : FreeLLMAPI à la place de Coarena après les autres gratuits,
        // puis OpenAI en tout dernier (crédit épuisé, pas rechargé pour l'instant).
        $user = User::factory()->create(['country' => 'TG']);
        foreach (['Salut', 'Quelle est la capitale du Togo ?'] as $text) {
            $sent = $this->providersSentFor($user, $text);
            $list = array_map(fn ($entry) => $entry['fournisseur'].'/'.$entry['modele'], $sent);
            $this->assertSame('gemini/gemini-3.1-flash-lite', $list[0]);
            $this->assertSame(['openai/gpt-6.1-terra', 'openai/gpt-6-luna'], array_slice($list, -2));
            // Réflexion choisie par la mesure (15 novembre 2026) : « none », la plus basse qui garde la qualité.
            $this->assertSame('none', $sent[count($sent) - 2]['reflexion'] ?? null);
            $freellmapi = array_keys(array_filter($list, fn ($entry) => str_starts_with($entry, 'freellmapi/')));
            $this->assertNotEmpty($freellmapi);
            $this->assertSame(array_values(range(count($list) - 2 - count($freellmapi), count($list) - 3)), array_values($freellmapi), 'FreeLLMAPI juste avant OpenAI');
            $this->assertEmpty(array_filter($list, fn ($entry) => str_starts_with($entry, 'coarena/')), 'plus jamais Coarena');
            $this->assertLessThan(array_search('workersai/@cf/zai-org/glm-4.7-flash', $list, true) ?: PHP_INT_MAX, array_search('openrouter/google/gemma-4-31b-it:free', $list, true), 'OpenRouter avant Workers AI');
        }
    }

    public function test_the_fast_model_stays_first_even_with_little_energy_left_and_openai_keeps_its_ceiling(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        // Moins de 15 % d'énergie : le modèle rapide reste le premier (le mode économie de Coarena n'existe plus).
        $project = $user->projects()->create(['name' => 'Site']);
        $project->agentRuns()->create([])->forceFill(['status' => 'completed', 'model_cost_usd' => 0.16, 'finished_at' => now()])->save();

        $this->assertSame('gemini/gemini-3.1-flash-lite', collect($this->providersSentFor($user, 'Salut'))->map(fn ($e) => $e['fournisseur'].'/'.$e['modele'])->first());

        // Plafond OpenAI du jour atteint (10 $) : OpenAI n'est plus proposé du tout, le Gemini gratuit reste devant.
        ProviderUsage::create(['provider' => 'openai', 'model' => 'gpt-6-luna', 'day' => now('UTC')->toDateString()])->forceFill(['cost_usd' => 10.0])->save();
        $list = $this->providersSentFor($user, 'Salut');
        $this->assertSame([], array_values(array_filter($list, fn ($e) => $e['fournisseur'] === 'openai')));
        $this->assertSame(['fournisseur' => 'gemini', 'modele' => 'gemini-3.1-flash-lite', 'reflexion' => 'minimal'], array_intersect_key($list[0], array_flip(['fournisseur', 'modele', 'reflexion'])));
    }

    public function test_the_free_formula_shows_100_fcfa_and_a_complete_showcase_site_fits_in_it(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $plan = app(Energy::class)->plan($user);
        $this->assertSame(100.0, $plan['energy_fcfa']);
        $this->assertSame(3, $plan['web_searches_per_day'], 'les recherches restent à 3 par jour');
        $this->assertLessThanOrEqual($plan['energy_fcfa'], app(Settings::class)->float('free_site_estimate_fcfa'), 'un site vitrine complet tient dans une journée');

        $this->actingAs($user)->get(route('plans'))->assertOk()
            ->assertSee('De quoi créer un site vitrine complet chaque jour')
            ->assertSee('Jusqu’à 100 discussions par jour, sans toucher à votre énergie')
            ->assertSee('L’énergie du mois entier, à utiliser quand vous voulez')
            ->assertDontSee('1 fois plus d’énergie');

        // Une énergie trop petite pour un site : la carte ne le promet plus.
        $plans = app(Settings::class)->get('plans');
        $plans['gratuite']['energy_fcfa'] = 50;
        app(Settings::class)->set('plans', $plans);
        $this->actingAs($user)->get(route('plans'))->assertDontSee('De quoi créer un site vitrine complet chaque jour')->assertSee('Énergie offerte, rechargée chaque jour');
    }

    public function test_the_accounts_move_to_100_fcfa_without_changing_a_value_chosen_in_the_administration(): void
    {
        $migration = require base_path('database/migrations/2026_11_25_000002_free_plan_energy_100.php');
        $settings = app(Settings::class);
        $user = User::factory()->create(['country' => 'TG']);

        // Formules enregistrées dans l'administration avec l'ancienne valeur (30) : elles passent à 100.
        $plans = config('kekeli.settings.plans');
        $plans['gratuite']['energy_fcfa'] = 30;
        unset($plans['gratuite']['simple_chats_per_day']);
        $settings->set('plans', $plans);
        $settings->set('free_site_estimate_fcfa', 55);
        $this->assertSame(30.0, app(Energy::class)->plan($user)['energy_fcfa']);
        $migration->up();
        $migration->up();
        $this->assertSame(100.0, app(Energy::class)->plan($user)['energy_fcfa']);
        $this->assertSame(100, app(Energy::class)->plan($user)['simple_chats_per_day'], 'valeur de départ pour une formule enregistrée avant');
        // 60 FCFA depuis la mesure réelle de 5 sites vitrines (15 novembre 2026).
        $this->assertSame(60.0, $settings->float('free_site_estimate_fcfa'));

        // Une autre valeur, choisie dans l'administration : jamais changée.
        $plans['gratuite']['energy_fcfa'] = 45;
        $settings->set('plans', $plans);
        $migration->up();
        $this->assertSame(45.0, app(Energy::class)->plan($user)['energy_fcfa']);
    }

    public function test_the_administration_sets_the_simple_discussions_of_each_formula(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->forceFill(['is_admin' => true])->save();
        Volt::actingAs($admin)->test('admin.settings')->assertSee('Discussions simples par jour')
            ->set('plans.gratuite.simple_chats_per_day', 150)->call('save')->assertHasNoErrors();
        $this->assertSame(150, app(Settings::class)->get('plans')['gratuite']['simple_chats_per_day']);
    }
}
