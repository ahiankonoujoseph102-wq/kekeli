<?php

namespace Tests\Feature;

use App\Agent\AnswerModels;
use App\Agent\Energy;
use App\Agent\Markdown;
use App\Agent\ProviderList;
use App\Agent\RunnerEventHandler;
use App\Models\AgentRun;
use App\Models\ProviderUsage;
use App\Models\User;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Qualité des réponses comme Claude (12 novembre 2026) : réponses en Markdown affichées de façon sûre, réflexion
 * repliée, modèle de réponse choisi par formule, plafond de dépense OpenAI par jour et passage silencieux au secours.
 */
class AnswerQualityTest extends TestCase
{
    use RefreshDatabase;

    private function ask(User $user, string $question)
    {
        Volt::actingAs($user)->test('site-prompt')->set('description', $question)->call('build');

        return $user->projects()->latest('id')->firstOrFail();
    }

    public function test_a_rich_answer_shows_titles_bold_lists_a_scrolling_table_and_code_with_a_copy_button(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->answers("Commencez **petit**.\n\n## Le budget\n\n| Poste | Montant |\n|---|---|\n| Stock | 400 000 FCFA |\n\n1. Le stock\n2. Les photos\n\n```html\n<a href=\"https://wa.me/22890000000\">Écrire</a>\n```\n\n*Ma recommandation* : gardez une réserve.");
        $chat = $this->ask($user, 'combien pour ouvrir une boutique de pagnes');

        $html = $this->actingAs($user)->get(route('projects.show', $chat))->assertOk()->getContent();
        $answer = (string) str($html)->after('data-question-answer')->before('data-answer-actions');
        $this->assertStringContainsString('<h2>Le budget</h2>', $answer);
        $this->assertStringContainsString('<strong>petit</strong>', $answer);
        $this->assertStringContainsString('<ol>', $answer);
        $this->assertStringContainsString('<em>Ma recommandation</em>', $answer);
        // Le tableau défile sur le côté sur un téléphone ; les montants ne se coupent pas.
        $this->assertStringContainsString('<div class="kekeli-tableau" data-markdown-table><table>', $answer);
        $this->assertStringContainsString('400&nbsp;000&nbsp;FCFA', $answer);
        // Le code est montré tel quel (échappé), avec son bouton « Copier », sans typographie dedans.
        $this->assertStringContainsString('data-copy-code', $answer);
        $this->assertStringContainsString('&lt;a href=&quot;https://wa.me/22890000000&quot;&gt;Écrire&lt;/a&gt;', $answer);
        $this->assertStringNotContainsString('<a href="https://wa.me', $answer);
    }

    public function test_the_markdown_display_never_accepts_raw_html_dangerous_links_or_images_from_the_model(): void
    {
        $html = (string) Markdown::render("Bonjour <script>alert(1)</script> et <img src=x onerror=alert(2)>\n\n[cliquez](javascript:alert(3)) [site](https://exemple.tg) ![photo](https://exemple.tg/a.png \"Une photo\") <b>gras</b>");

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('href=', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        // Les liens et les images du modèle deviennent du texte.
        $this->assertStringContainsString('cliquez', $html);
        $this->assertStringContainsString('Une photo', $html);
    }

    public function test_a_model_that_really_thought_shows_a_folded_thinking_line_with_its_length_and_a_short_summary(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->answersAfterThinking('Affichez chaque pagne à **12 000 FCFA**.', 'Je calcule le prix payé après la remise, puis le prix affiché.', 6);
        $chat = $this->ask($user, 'à quel prix afficher mes pagnes avec une remise de 10 %');

        $this->actingAs($user)->get(route('projects.show', $chat))->assertOk()
            ->assertSee('data-thinking', false)
            ->assertSee('Réflexion · 6 s')
            ->assertSee('Je calcule le prix payé après la remise, puis le prix affiché.');
        $this->assertSame(6, data_get(AgentRun::latest('id')->first()->report, 'thinking.seconds'));
    }

    public function test_a_simple_question_has_no_thinking_line(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->answers('La capitale du Burkina Faso est **Ouagadougou**.');
        $chat = $this->ask($user, 'Quelle est la capitale du Burkina Faso ?');

        $this->actingAs($user)->get(route('projects.show', $chat))->assertOk()
            ->assertDontSee('data-thinking', false)
            ->assertSee('<strong>Ouagadougou</strong>', false);
    }

    public function test_each_formula_gets_its_answer_model_first_and_the_free_providers_behind_it(): void
    {
        $answers = app(AnswerModels::class);
        $free = User::factory()->create(['plan' => 'gratuite']);
        $pro = User::factory()->create(['plan' => 'pro', 'plan_expires_at' => now()->addDays(10)]);

        $list = $answers->forRunner($free);
        $this->assertSame(['openai', 'gpt-6-luna'], [$list[0]['fournisseur'], $list[0]['modele']]);
        $this->assertSame('gemini', $list[1]['fournisseur']);
        $this->assertSame(['openai', 'gpt-6.1-terra'], [$answers->forRunner($pro)[0]['fournisseur'], $answers->forRunner($pro)[0]['modele']]);

        // Une formule payée dont les 30 jours sont finis répond comme la formule gratuite.
        $pro->forceFill(['plan_expires_at' => now()->subDay()])->save();
        $this->assertSame('gpt-6-luna', $answers->forRunner($pro->fresh())[0]['modele']);
    }

    public function test_the_daily_ceiling_of_openai_sends_the_answers_to_the_free_providers_without_a_word_to_the_client(): void
    {
        $answers = app(AnswerModels::class);
        $user = User::factory()->create(['plan' => 'pro', 'plan_expires_at' => now()->addDays(10)]);
        $this->assertTrue($answers->openAiAvailable());

        // 1 $ par jour au départ : atteint, OpenAI n'est plus proposé jusqu'au lendemain (jour UTC).
        ProviderUsage::create(['day' => CarbonImmutable::now('UTC')->toDateString(), 'provider' => 'openai', 'model' => 'gpt-6.1-terra'])->forceFill(['cost_usd' => 1.0])->save();
        $this->assertFalse($answers->openAiAvailable());
        $list = $answers->forRunner($user);
        $this->assertNotContains('openai', array_column($list, 'fournisseur'));
        $this->assertSame('gemini', $list[0]['fournisseur']);

        // Plafond relevé dans l'administration : OpenAI revient.
        app(Settings::class)->set('openai_daily_cap_usd', 2.5);
        $this->assertSame('openai', $answers->forRunner($user)[0]['fournisseur']);
        // 0 : OpenAI jamais utilisé.
        app(Settings::class)->set('openai_daily_cap_usd', 0);
        $this->assertSame('gemini', $answers->forRunner($user)[0]['fournisseur']);
    }

    public function test_a_wrong_setting_can_never_choose_an_expensive_model_and_openai_never_works_on_the_sites(): void
    {
        $this->assertFalse(AnswerModels::isValid('openai/gpt-6-astra'));
        $this->assertFalse(AnswerModels::isValid('openai/gpt-5.5-pro'));
        $this->assertFalse(AnswerModels::isValid('openrouter/anthropic/claude-sonnet-5-5'));
        $this->assertTrue(AnswerModels::isValid('openai/gpt-6.1-sol'));
        $this->assertTrue(AnswerModels::isValid('gemini/gemini-3.6-flash'));

        app(Settings::class)->set('plans', ['gratuite' => ['name' => 'Gratuite', 'price_fcfa' => 0, 'energy_fcfa' => 30, 'period' => 'day', 'answer_model' => 'openai/gpt-6-astra']]);
        $user = User::factory()->create(['plan' => 'gratuite']);
        $this->assertNull(app(AnswerModels::class)->chosenFor($user));
        $this->assertNotContains('openai', array_column(app(AnswerModels::class)->forRunner($user), 'fournisseur'));

        // L'agent qui construit les sites ne reçoit jamais OpenAI, même mis par erreur dans la liste.
        config(['kekeli.providers_order' => 'openai,gemini']);
        $this->assertSame(['gemini'], array_column(app(ProviderList::class)->forRunner(), 'fournisseur'));
    }

    public function test_the_administrator_chooses_the_model_of_each_formula_and_the_daily_ceiling(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Volt::actingAs($admin)->test('admin.settings')
            ->set('plans.gratuite.answer_model', 'openai/gpt-6-astra')
            ->call('save')
            ->assertHasErrors('plans.gratuite.answer_model')
            ->set('plans.gratuite.answer_model', 'openai/gpt-6.1-terra')
            ->set('general.openai_daily_cap_usd', 2)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('openai/gpt-6.1-terra', app(Settings::class)->get('plans')['gratuite']['answer_model']);
        $this->assertSame(2, app(Settings::class)->int('openai_daily_cap_usd'));
        $this->actingAs($admin)->get(route('admin.settings'))->assertOk()->assertSee('data-plan-answer-model', false)->assertSee('Plafond de dépense OpenAI par jour (dollars)');
    }

    public function test_the_real_cost_of_an_openai_answer_is_counted_in_the_energy_and_in_the_ceiling_of_the_day(): void
    {
        $user = User::factory()->create(['country' => 'TG', 'plan' => 'gratuite']);
        $project = $user->projects()->create(['name' => 'Question']);
        $message = $project->messages()->create(['role' => 'user', 'content' => 'bonjour']);
        $run = $project->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);

        // GPT-6.1 Terra : 1 $ par million de tokens lus, 5 $ écrits (réflexion comprise), 0,05 $ en cache.
        app(RunnerEventHandler::class)->handle($run, ['type' => 'requete', 'fournisseur' => 'openai', 'modele' => 'gpt-6.1-terra', 'tokens_entree' => 1000, 'tokens_sortie' => 400, 'tokens_reflexion' => 200, 'tokens_cache' => 3000, 'requetes' => 1]);

        $expected = (1000 * 1 + 600 * 5 + 3000 * 0.05) / 1_000_000;
        $this->assertEqualsWithDelta($expected, (float) $run->fresh()->model_cost_usd, 0.000001);
        $this->assertEqualsWithDelta($expected, app(AnswerModels::class)->openAiSpentToday(), 0.000001);
        $this->assertEqualsWithDelta($expected * 590, app(Energy::class)->usedFcfa($user), 0.001);
    }

    public function test_the_administration_appears_in_the_menu_only_for_an_administrator(): void
    {
        $client = User::factory()->create(['country' => 'TG']);
        $admin = User::factory()->create(['country' => 'TG', 'is_admin' => true]);

        $this->actingAs($client)->get(route('projects.index'))->assertOk()->assertDontSee('Administration')->assertDontSee(route('admin.settings'));
        $this->actingAs($client)->get(route('admin.settings'))->assertForbidden();
        $this->actingAs($admin)->get(route('projects.index'))->assertOk()->assertSee('Administration')->assertSee(route('admin.settings'));
    }
}
