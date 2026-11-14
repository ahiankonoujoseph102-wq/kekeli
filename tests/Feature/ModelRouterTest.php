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

    public function test_the_same_best_models_for_every_formula_and_economy_only_when_the_credit_is_almost_used(): void
    {
        // Une seule expérience pour tous, comme Lovable et Manus : la formule Gratuite a les mêmes modèles de qualité.
        $free = User::factory()->create(['plan' => 'gratuite']);
        $paid = User::factory()->create(['plan' => 'pro']);
        $paid->forceFill(['plan_expires_at' => now()->addDays(10)])->save();
        $this->assertSame('qualite', $this->router()->mode($free));
        $this->assertSame('qualite', $this->router()->mode($paid));
        $this->assertSame($this->router()->entries($free, 'application'), $this->router()->entries($paid, 'application'));
        $this->assertSame('gpt-6-1-sol', $this->router()->entries($free, 'application')[0]['modele']);

        // Plus que 12 % de crédit (0,045 $ dépensé sur 30 FCFA par jour) : des modèles plus économiques finissent le
        // travail au lieu de l'arrêter.
        $project = $free->projects()->create(['name' => 'Essai']);
        $project->agentRuns()->create([])->forceFill(['status' => 'completed', 'model_cost_usd' => 0.045, 'finished_at' => now()])->save();
        $this->assertSame('economie', app(ModelRouter::class)->mode($free));
        $this->assertSame('gpt-6-1-terra', app(ModelRouter::class)->entries($free, 'application')[0]['modele']);
        app(Settings::class)->set('economy_below_percent', 0);
        $this->assertSame('qualite', app(ModelRouter::class)->mode($free));

        // Claude, pas encore testé, n'est dans aucune liste.
        foreach (ModelRouter::TASKS as $task) {
            foreach (ModelRouter::MODES as $mode) {
                $this->assertEmpty(array_filter($this->router()->models($task, $mode), fn ($m) => str_starts_with($m, 'claude-')));
            }
        }
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
        app(Settings::class)->set('model_routes', ['reponse_simple' => ['qualite' => ['gpt-6-astra', 'gpt-6-luna']]]);
        $this->assertSame(['gpt-6-luna'], $this->router()->models('reponse_simple', 'qualite'));

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
        $page->set('routes.code.qualite', 'gpt-6-astra')->call('saveRoutes')->assertHasErrors('routes.code.qualite');
        $page->set('routes.code.qualite', 'kimi-k3-1-pro, gpt-6-1-sol')->call('saveRoutes')->assertHasNoErrors();
        $this->assertSame(['kimi-k3-1-pro', 'gpt-6-1-sol'], app(Settings::class)->get('model_routes')['code']['qualite']);

        $page->call('toggleKey', 'COARENA_API_KEY_2');
        $this->assertSame(['COARENA_API_KEY_2'], app(Settings::class)->get('coarena_disabled_keys'));
    }

    public function test_the_ranking_is_a_compact_list_on_a_phone_and_a_table_on_a_computer(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        config(['kekeli.runner.url' => 'http://runner.test', 'kekeli.runner.secret' => 'secret-de-test']);
        Http::fake(['runner.test/*' => Http::response(['cles_presentes' => [], 'mis_de_cote' => [], 'coarena' => []])]);

        $html = $this->actingAs($admin)->get(route('admin.models'))->assertOk()->getContent();

        // Téléphone : une carte par modèle (rien de coupé à droite) ; ordinateur : le tableau, caché sur téléphone.
        $this->assertStringContainsString('data-ranking-list', $html);
        $this->assertStringContainsString('class="flex flex-col gap-2 sm:hidden" data-ranking-list', $html);
        $this->assertStringContainsString('kekeli-tableau hidden sm:block', $html);
        $this->assertSame(substr_count($html, 'data-ranking-row'), substr_count($html, 'data-ranking-item'));
        $this->assertGreaterThan(0, substr_count($html, 'data-ranking-item'));
    }

    public function test_only_administrators_open_the_models_page(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.models'))->assertForbidden();
        $this->assertNotSame(200, $this->get(route('admin.models'))->getStatusCode());
    }

    public function test_no_pro_mode_and_no_model_name_anywhere_for_the_client(): void
    {
        // Un nom fixe : un prénom tiré au hasard (« Claude ») ferait échouer le test sans raison.
        $free = User::factory()->create(['plan' => 'gratuite', 'name' => 'Ama Mensah']);
        $page = $this->actingAs($free)->get(route('projects.index'))->assertOk();
        foreach (['Mode Pro', 'Kekeli Pro', 'Coarena', 'GPT', 'Claude', 'Gemini', 'Kimi'] as $word) {
            $page->assertDontSee($word);
        }
    }

    public function test_used_up_free_credits_show_a_card_with_what_was_made_and_the_formulas_and_keep_the_project(): void
    {
        $free = User::factory()->create(['plan' => 'gratuite', 'country' => 'TG']);
        $site = $free->projects()->create(['name' => 'Restaurant Le Palmier']);
        $free->projects()->create(['name' => 'École Les Flamboyants']);
        $doc = $free->projects()->make(['name' => 'Devis']);
        $doc->forceFill(['kind' => Project::KIND_DOCUMENT, 'document_type' => 'document'])->save();
        app(Settings::class)->set('plans_published', true);
        $site->agentRuns()->create([])->forceFill(['status' => 'completed', 'model_cost_usd' => 1, 'finished_at' => now()])->save();

        $this->actingAs($free)->get(route('projects.show', $site))->assertOk()
            ->assertSee('data-credit-exhausted', false)
            ->assertSee('Vous avez utilisé tous vos crédits gratuits.')
            ->assertSee('Votre projet est toujours disponible. Pour continuer à créer avec Kekeli, choisissez une formule d’abonnement.')
            ->assertSee('2 sites')
            ->assertSee('1 document')
            // Comme Claude et ChatGPT : un seul bouton vers la page des formules, sans les cartes des formules.
            ->assertSee('Voir les formules')
            ->assertSee('href="'.route('plans').'"', false)
            ->assertDontSee('data-credit-plan', false)
            ->assertDontSee('data-plan="pro"', false)
            ->assertDontSee('7 500 FCFA')
            ->assertDontSee('Choisir une formule')
            ->assertDontSee('Kekeli Pro');
        $this->assertModelExists($site);
    }
}
