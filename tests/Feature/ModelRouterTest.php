<?php

namespace Tests\Feature;

use App\Agent\AnswerModels;
use App\Agent\ModelRouter;
use App\Agent\RunnerClient;
use App\Models\Project;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Le routeur de modèles et Coarena (docs/plan.md, partie 26.26) : type de tâche reconnu sans modèle, modes Standard et
 * Pro, interrupteurs, liste envoyée au Runner, administration des comptes (clés jamais montrées), mode Pro du client.
 */
class ModelRouterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['kekeli.settings.model_providers' => ['coarena' => true, 'openai' => true, 'gratuits' => true]]);
    }

    private function router(): ModelRouter
    {
        return app(ModelRouter::class);
    }

    public function test_the_type_of_an_answer_is_recognised_without_any_model(): void
    {
        $r = $this->router();
        $this->assertSame('reponse_simple', $r->classifyAnswer('Quelle est la capitale du Burkina Faso ?'));
        $this->assertSame('reponse_reflexion', $r->classifyAnswer('Je veux ouvrir une boutique de pagnes à Lomé, combien il faut pour commencer ?'));
        $this->assertSame('redaction', $r->classifyAnswer('Rédige un courriel poli pour relancer un client.'));
        $this->assertSame('traduction', $r->classifyAnswer('Traduis ce texte en anglais : bienvenue chez nous.'));
        $this->assertSame('extraction', $r->classifyAnswer('Extrais les noms et les numéros de cette liste en JSON.'));
        $this->assertSame('code', $r->classifyAnswer('Pourquoi ma fonction JavaScript renvoie undefined ?'));
    }

    public function test_the_type_of_a_work_comes_from_the_project(): void
    {
        $user = User::factory()->create();
        $make = fn (array $attributes) => tap($user->projects()->make(['name' => 'Essai']))->forceFill($attributes)->save();
        $site = $user->projects()->create(['name' => 'Restaurant']);
        $this->assertSame('site', $this->router()->classifyBuild($site));
        $site->agentRuns()->create(['status' => 'completed']);
        $this->assertSame('modification', $this->router()->classifyBuild($site->fresh()));

        $app = $user->projects()->make(['name' => 'Rendez-vous']);
        $app->forceFill(['kind' => Project::KIND_WEBAPP, 'is_application' => true])->save();
        $this->assertSame('application', $this->router()->classifyBuild($app));
        $agent = $user->projects()->make(['name' => 'Assistante']);
        $agent->forceFill(['kind' => Project::KIND_WEBAPP, 'is_agent' => true])->save();
        $this->assertSame('agent', $this->router()->classifyBuild($agent));
    }

    public function test_standard_for_the_free_formula_and_pro_only_for_a_paid_one_that_chose_it(): void
    {
        $free = User::factory()->create(['plan' => 'gratuite']);
        $free->forceFill(['model_mode' => 'pro'])->save();
        $this->assertSame('standard', $this->router()->mode($free));

        $paid = User::factory()->create(['plan' => 'pro']);
        $paid->forceFill(['plan_expires_at' => now()->addDays(10)])->save();
        $this->assertSame('standard', $this->router()->mode($paid));
        $paid->forceFill(['model_mode' => 'pro'])->save();
        $this->assertSame('pro', $this->router()->mode($paid));

        // Pro : les meilleurs modèles du banc (GPT-6.1 Sol pour les applications) ; Claude, pas encore testé, nulle part.
        $this->assertSame('gpt-6-1-sol', $this->router()->entries($paid, 'application')[0]['modele']);
        foreach (ModelRouter::TASKS as $task) {
            $this->assertEmpty(array_filter(array_column($this->router()->entries($paid, $task), 'modele'), fn ($m) => str_starts_with($m, 'claude-')));
        }
        $this->assertSame('gpt-6-luna', $this->router()->entries($free, 'reponse_simple')[0]['modele']);
    }

    public function test_coarena_comes_first_then_the_usual_list_and_switches_remove_a_provider(): void
    {
        $user = User::factory()->create();
        $usual = app(AnswerModels::class)->forRunner($user);
        $list = $this->router()->route($user, 'reponse_simple', $usual);
        $this->assertSame('coarena', $list[0]['fournisseur']);
        $this->assertSame(array_slice($list, 2), $usual);

        app(Settings::class)->set('model_providers', ['coarena' => false, 'openai' => true, 'gratuits' => true]);
        $this->assertSame($usual, $this->router()->route($user, 'reponse_simple', $usual));

        app(Settings::class)->set('model_providers', ['coarena' => true, 'openai' => true, 'gratuits' => false]);
        $this->assertSame([], array_values(array_filter($this->router()->route($user, 'site', $usual), fn ($e) => ! in_array($e['fournisseur'], ['coarena', 'openai'], true))));
    }

    public function test_only_allowed_models_are_routed_and_the_settings_name_keys_only(): void
    {
        app(Settings::class)->set('model_routes', ['reponse_simple' => ['standard' => ['gpt-6-astra', 'gpt-6-luna']]]);
        $this->assertSame(['gpt-6-luna'], $this->router()->models('reponse_simple', 'standard'));

        app(Settings::class)->set('coarena_disabled_keys', ['COARENA_API_KEY_3', 'nimporte quoi']);
        $this->assertSame(['comptes_multiples' => true, 'cles_desactivees' => ['COARENA_API_KEY_3']], $this->router()->coarenaSettings());
    }

    public function test_the_runner_receives_coarena_first_and_its_settings_with_a_question(): void
    {
        $this->useRealFirstResponder();
        config(['kekeli.runner.url' => 'http://runner.test', 'kekeli.runner.secret' => 'secret-de-test']);
        Http::fake(['runner.test/repondre' => Http::response(['ok' => true, 'decision' => 'repondre', 'reponse' => 'Lomé.', 'fournisseur' => 'coarena', 'modele' => 'gpt-6-luna', 'refus' => []])]);
        $user = User::factory()->create();
        $chat = $user->projects()->make(['name' => 'Question']);
        $chat->forceFill(['kind' => Project::KIND_CHAT])->save();
        $message = $chat->messages()->create(['role' => 'user', 'content' => 'Quelle est la capitale du Togo ?']);
        $run = $chat->agentRuns()->create(['message_id' => $message->id, 'purpose' => 'question']);

        app(RunnerClient::class)->respond($run, 'discussion');

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['fournisseurs'][0]['fournisseur'] === 'coarena' && $body['coarena']['comptes_multiples'] === true;
        });
    }

    public function test_a_construction_does_not_stop_at_the_openai_ceiling_when_coarena_can_build(): void
    {
        $user = User::factory()->create();
        app(Settings::class)->set('openai_daily_cap_usd', 0.0);
        $this->assertFalse(app(AnswerModels::class)->buildCapReached($user, 'site'));
        app(Settings::class)->set('model_providers', ['coarena' => false, 'openai' => true, 'gratuits' => true]);
        $this->assertTrue(app(AnswerModels::class)->buildCapReached($user, 'site'));
    }

    public function test_the_administration_shows_the_accounts_without_any_key_and_tests_them(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        config(['kekeli.runner.url' => 'http://runner.test', 'kekeli.runner.secret' => 'secret-de-test']);
        Http::fake([
            'runner.test/etat' => Http::response(['cles_presentes' => [], 'mis_de_cote' => [], 'coarena' => [['nom' => 'COARENA_API_KEY', 'compte' => 'compte-1b52cfbf73', 'hors_service' => false, 'statistiques' => ['demandes' => 3, 'reussites' => 2, 'erreurs' => ['quota' => 1], 'duree_totale_ms' => 2400]]]]),
            'runner.test/coarena/cles' => Http::response(['cles' => [['nom' => 'COARENA_API_KEY_2', 'valide' => true, 'compte' => 'compte-da8a838b9f', 'expire_le' => '2027-02-12T13:12:11.000Z', 'quota' => ['utilise_usd' => 0.12, 'usd_par_jour' => 5], 'modeles' => ['gpt-6-luna', 'kimi-k3-1-pro'], 'essai' => ['ok' => true, 'duree_ms' => 950], 'controle_le' => now()->toIso8601String()]]]),
        ]);

        $page = Volt::actingAs($admin)->test('admin.models')
            ->assertSee('COARENA_API_KEY')
            ->assertSee('Modèles par tâche')
            ->call('testKeys', 'COARENA_API_KEY_2')
            ->assertSee('compte-da8a838b9f')
            ->assertSee('12 février 2027')
            ->assertSee('0,120 / 5 $');
        $this->assertSame('COARENA_API_KEY_2', app(Settings::class)->get('coarena_key_checks')[0]['nom'] ?? null);

        // Interrupteurs et modèles par tâche (un modèle hors de la liste est refusé).
        $page->set('multiAccounts', false)->call('saveSwitches');
        $this->assertFalse((bool) app(Settings::class)->get('coarena_multi_accounts'));
        $page->set('routes.code.pro', 'gpt-6-astra')->call('saveRoutes')->assertHasErrors('routes.code.pro');
        $page->set('routes.code.pro', 'kimi-k3-1-pro, gpt-6-1-sol')->call('saveRoutes')->assertHasNoErrors();
        $this->assertSame(['kimi-k3-1-pro', 'gpt-6-1-sol'], app(Settings::class)->get('model_routes')['code']['pro']);

        $page->call('toggleKey', 'COARENA_API_KEY_2');
        $this->assertSame(['COARENA_API_KEY_2'], app(Settings::class)->get('coarena_disabled_keys'));
    }

    public function test_only_administrators_open_the_models_page(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.models'))->assertForbidden();
        $this->assertNotSame(200, $this->get(route('admin.models'))->getStatusCode());
    }

    public function test_the_pro_mode_is_offered_only_to_paid_formulas_and_never_names_a_model(): void
    {
        $free = User::factory()->create(['plan' => 'gratuite']);
        $this->actingAs($free)->get(route('projects.index'))->assertDontSee('data-prompt-mode-pro', false);
        $this->actingAs($free)->postJson(route('model-mode.update'), ['mode' => 'pro'])->assertJson(['mode' => 'standard']);

        $paid = User::factory()->create(['plan' => 'pro']);
        $paid->forceFill(['plan_expires_at' => now()->addDays(10)])->save();
        $page = $this->actingAs($paid)->get(route('projects.index'))->assertSee('data-prompt-mode-pro', false)->assertSee('Mode Pro');
        foreach (['Coarena', 'GPT', 'Claude', 'Gemini', 'Kimi'] as $name) {
            $page->assertDontSee($name);
        }
        $this->actingAs($paid)->postJson(route('model-mode.update'), ['mode' => 'pro'])->assertJson(['mode' => 'pro']);
        $this->assertSame('pro', $paid->fresh()->model_mode);
        $this->actingAs($paid)->postJson(route('model-mode.update'), ['mode' => 'turbo'])->assertUnprocessable();
    }
}
