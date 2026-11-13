<?php

namespace Tests\Feature;

use App\Agent\RunnerClient;
use App\Agent\Tools\ModifyDocument;
use App\Jobs\GenerateDocument;
use App\Models\AgentRun;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Modèle selon la difficulté : une petite retouche d'un document, d'une affiche ou d'une vidéo (une couleur, un texte,
 * un prix, une date) passe par le modèle moins cher de la formule ; un gros travail garde le meilleur. Qualité mesurée :
 * 8 retouches sur 8 justes avec chaque modèle (runner/mesures/retouches).
 */
class SmallEditModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake([GenerateDocument::class]);
        config(['kekeli.runner.secret' => 'secret-de-test-tres-long-pour-les-signatures-0123456789', 'kekeli.runner.url' => 'http://runner.test']);
    }

    private function modelFor(User $user, string $size): string
    {
        $project = $user->projects()->make(['name' => 'Affiche']);
        $project->forceFill(['kind' => Project::KIND_DOCUMENT, 'document_type' => 'image'])->save();
        $message = $project->messages()->create(['role' => 'user', 'content' => 'mets le prix à 9 500 FCFA']);
        $responder = $project->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
        $run = app(ModifyDocument::class)->handle($responder, ['ampleur' => $size]);
        $this->assertSame($size === 'petite' ? 'petite' : 'grande', $run->document_options['ampleur']);

        Http::fake(['runner.test/*' => Http::response(['accepte' => true], 202)]);
        app(RunnerClient::class)->document($run);
        $model = null;
        Http::assertSent(function (Request $r) use (&$model) {
            $model = $r['fournisseurs'][0]['modele'] ?? null;

            return true;
        });

        return (string) $model;
    }

    public function test_a_small_change_uses_the_cheaper_model_of_the_formula_and_a_big_one_the_best(): void
    {
        $pro = User::factory()->create(['country' => 'TG', 'plan' => 'pro', 'plan_expires_at' => now()->addDays(10)]);
        $this->assertSame('gpt-6.1-terra', $this->modelFor($pro, 'petite'));
        $this->assertSame('gpt-6.1-sol', $this->modelFor($pro, 'grande'));

        $free = User::factory()->create(['country' => 'TG']);
        $this->assertSame('gpt-6-luna', $this->modelFor($free, 'petite'));
        $this->assertSame('gpt-6.1-terra', $this->modelFor($free, ''));
    }
}
