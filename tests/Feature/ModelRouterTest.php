<?php

namespace Tests\Feature;

use App\Agent\ModelRouter;
use App\Models\Project;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le routeur de modèles (docs/plan.md, parties 26.26 et 26.37) : type de tâche reconnu sans modèle, une seule expérience
 * pour tous (aucun mode Pro, aucun nom de modèle pour le client), page réservée aux administrateurs. FreeLLMAPI, qui
 * remplace Coarena, a ses propres tests (tests/Feature/FreeLlmApiTest.php).
 */
class ModelRouterTest extends TestCase
{
    use RefreshDatabase;

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

    /**
     * Le meilleur du gratuit, tâche par tâche (15 novembre 2026) : chaque groupe de tâches a son ordre mesuré, une entrée
     * non listée garde sa place après, et OpenAI reste en dernier.
     */
    public function test_each_task_group_has_its_own_free_order_and_openai_stays_last(): void
    {
        config(['kekeli.free_order' => [
            'discussion' => ['gemini/b', 'gemini/a'],
            'construction' => ['gemini/a', 'gemini/b'],
        ]]);
        $user = User::factory()->create();
        $usual = [
            ['fournisseur' => 'openai', 'modele' => 'gpt-6.1-terra', 'remise_a_zero' => 'x'],
            ['fournisseur' => 'gemini', 'modele' => 'a', 'remise_a_zero' => 'x'],
            ['fournisseur' => 'workersai', 'modele' => 'w', 'remise_a_zero' => 'x'],
            ['fournisseur' => 'gemini', 'modele' => 'b', 'remise_a_zero' => 'x'],
        ];
        $names = fn (array $list) => array_map(fn ($e) => $e['fournisseur'].'/'.$e['modele'], $list);

        $this->assertSame(['gemini/a', 'gemini/b', 'workersai/w', 'openai/gpt-6.1-terra'], array_slice($names($this->router()->route($user, 'site', $usual)), 0, 4));
        $this->assertSame(['gemini/b', 'gemini/a', 'workersai/w'], array_slice($names($this->router()->route($user, 'reponse_simple', $usual)), 0, 3));
        $this->assertSame('openai/gpt-6.1-terra', last($names($this->router()->route($user, 'reponse_simple', $usual))));
        // Sans ordre pour les documents : l'ordre habituel, OpenAI en dernier.
        $this->assertSame(['gemini/a', 'workersai/w', 'gemini/b'], array_slice($names($this->router()->route($user, 'document', $usual)), 0, 3));
        $this->assertSame('construction', ModelRouter::groupOf('automatisation'));
        $this->assertSame('discussion', ModelRouter::groupOf('traduction'));
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
        foreach (['Mode Pro', 'Kekeli Pro', 'Coarena', 'FreeLLMAPI', 'Groq', 'LongCat', 'GPT', 'Claude', 'Gemini', 'Kimi'] as $word) {
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
