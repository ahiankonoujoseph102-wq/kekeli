<?php

namespace Tests\Feature;

use App\Agent\RunnerSignature;
use App\Agent\WebSearchQuota;
use App\Jobs\RespondToMessage;
use App\Jobs\SendAgentRun;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Recherche sur Internet avec sources (outil « rechercher_web » du premier répondant), comme Claude ou ChatGPT. Le
 * modèle et la recherche sont simulés : Tavily, Exa et le modèle ne sont jamais appelés pendant les tests.
 */
class WebSearchTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'secret-de-test-tres-long-pour-les-signatures-0123456789';

    protected function setUp(): void
    {
        parent::setUp();
        config(['kekeli.runner.secret' => self::SECRET, 'kekeli.runner.url' => 'http://runner.test']);
    }

    public function test_an_answer_with_a_search_shows_numbered_clickable_sources_and_counts_the_search(): void
    {
        Bus::fake([SendAgentRun::class]);
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->answersWithSources('Un nom de domaine en .tg coûte environ 15 000 FCFA par an [1], en .com entre 10 $ et 20 $ [2].', [
            ['numero' => 1, 'titre' => 'Prix des noms de domaine .tg', 'url' => 'https://www.exemple.tg/prix'],
            ['numero' => 2, 'titre' => 'Comparatif', 'url' => 'https://comparatif.example.com/domaines'],
            ['numero' => 3, 'titre' => 'Lien dangereux', 'url' => 'javascript:alert(1)'],
        ]);

        Volt::actingAs($user)->test('site-prompt')->set('description', 'combien coûte un nom de domaine en .tg aujourd’hui')->call('build');
        $chat = $user->projects()->sole();
        $run = $chat->agentRuns()->sole();

        $this->assertSame(1, $run->web_searches);
        $this->assertEqualsWithDelta(0.008, $run->web_search_cost_usd, 0.000001);
        $this->assertSame(2, $run->requests);

        $html = $this->actingAs($user)->get(route('projects.show', $chat))->assertOk()
            ->assertSee('data-sources', false)
            ->assertSeeInOrder(['Sources', 'Prix des noms de domaine .tg', 'Comparatif'])
            ->assertSee('exemple.tg')
            ->assertDontSee('javascript:alert', false)
            ->getContent();
        $this->assertStringContainsString('href="https://www.exemple.tg/prix" target="_blank" rel="noopener noreferrer nofollow"', $html);
        $this->assertSame(2, substr_count($html, 'data-source>'));
        // Un long titre de source ne fait jamais déborder la discussion sur un téléphone.
        $this->assertStringContainsString('id="panneau-discussion" role="tabpanel" class="flex min-h-0 min-w-0 flex-1 flex-col', $html);
    }

    public function test_a_question_without_search_has_no_sources_and_costs_no_search(): void
    {
        Bus::fake([SendAgentRun::class]);
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->answers('Un site vitrine présente votre activité ; un site avec base de données enregistre des informations.');

        Volt::actingAs($user)->test('site-prompt')->set('description', 'c’est quoi un site vitrine')->call('build');
        $chat = $user->projects()->sole();

        $this->assertSame(0, $chat->agentRuns()->sole()->web_searches);
        $this->actingAs($user)->get(route('projects.show', $chat))->assertDontSee('data-sources', false)->assertDontSee('Recherche sur Internet');
    }

    public function test_during_a_search_a_discreet_line_says_searching_on_the_internet(): void
    {
        [$user, $project] = $this->siteWithQuestion('Le prix du ciment aujourd’hui ?');
        $run = $project->agentRuns()->sole();
        $run->update(['status' => AgentRun::STATUS_RUNNING]);

        $body = json_encode(['tache' => $run->id, 'evenement' => ['type' => 'recherche', 'requete' => 'prix du ciment Lomé']]);
        $this->call('POST', route('runner.events'), [], [], [], $this->signed($body), $body)->assertOk();

        $this->assertSame('recherche_web', $run->steps()->sole()->kind);
        $this->actingAs($user)->get(route('projects.show', $project))->assertSee('Recherche sur Internet…');

        // Une fois la réponse là, la recherche est résumée et repliée : « A cherché sur Internet · 1 source ».
        $run->forceFill(['status' => AgentRun::STATUS_COMPLETED, 'report' => ['done' => 'Environ 5 000 FCFA [1].', 'question' => true, 'sources' => [['titre' => 'Journal', 'url' => 'https://journal.example.com/ciment']], 'files' => [], 'tests' => [], 'problems' => []]])->save();
        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertSee('data-web-search', false)
            ->assertSee('A cherché sur Internet · 1 source')
            ->assertSee('journal.example.com')
            ->assertDontSee('Kekeli a fait 1 action')
            ->assertDontSee('Recherche sur Internet…');
    }

    public function test_the_runner_gets_the_searches_left_for_the_day_and_the_month(): void
    {
        $this->useRealFirstResponder();
        [$user, $project] = $this->siteWithQuestion('Quel est le prix du ciment aujourd’hui ?');
        $run = $project->agentRuns()->sole();
        Http::fake(['runner.test/repondre' => Http::response(['ok' => true, 'decision' => 'repondre', 'reponse' => 'Environ 5 000 FCFA [1].', 'sources' => [['titre' => 'Journal', 'url' => 'https://journal.example.com/a']], 'recherches' => [['fournisseur' => 'exa', 'requete' => 'prix ciment', 'resultats' => 3]], 'requetes' => 2, 'fournisseur' => 'gemini', 'modele' => 'gemini-3.5-flash-lite', 'tokens_entree' => 2400, 'tokens_sortie' => 80, 'refus' => []])]);

        RespondToMessage::dispatchSync($run);

        Http::assertSent(fn (Request $request) => $request->url() === 'http://runner.test/repondre'
            && $request['recherche'] === ['permise' => true, 'restantes' => 3]
            && $request['rappel'] === route('runner.events')
            && $request['tache'] === $run->id
            && ! str_contains($request->body(), 'TAVILY') && ! str_contains($request->body(), 'EXA'));
        $this->assertSame(1, $run->fresh()->web_searches);
        $this->assertSame(2, $run->fresh()->requests);
    }

    public function test_limits_per_day_by_formula_and_per_month_for_everyone(): void
    {
        $quota = app(WebSearchQuota::class);
        $free = User::factory()->create(['country' => 'TG']);
        $pro = User::factory()->create(['country' => 'TG']);
        $pro->forceFill(['plan' => 'pro', 'plan_expires_at' => now()->addDays(20)])->save();

        $this->assertSame(3, $quota->remaining($free));
        $this->assertSame(20, $quota->remaining($pro));

        // Trois recherches aujourd'hui : plus rien pour la formule gratuite.
        $project = $free->projects()->create(['name' => 'Questions']);
        $project->agentRuns()->create([])->forceFill(['web_searches' => 3])->save();
        $this->assertSame(0, $quota->remaining($free));
        $this->assertSame(20, $quota->remaining($pro));

        // Plafond du mois pour tous les clients ensemble.
        app(Settings::class)->set('web_search_monthly_cap', 10);
        $this->assertSame(7, $quota->remaining($pro));
        app(Settings::class)->set('web_search_monthly_cap', 3);
        $this->assertSame(0, $quota->remaining($pro));
    }

    public function test_the_search_settings_are_in_the_administration(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        Volt::actingAs($admin)->test('admin.settings')
            ->assertSet('general.web_search_monthly_cap', 900)
            ->assertSet('plans.pro.web_searches_per_day', 20)
            ->set('general.web_search_monthly_cap', 500)
            ->set('plans.gratuite.web_searches_per_day', 2)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(500, app(Settings::class)->int('web_search_monthly_cap'));
        $this->assertSame(2, app(WebSearchQuota::class)->perDay(User::factory()->create()));
    }

    /**
     * @return array{User, Project}
     */
    private function siteWithQuestion(string $text): array
    {
        $user = User::factory()->create(['country' => 'TG']);
        $project = $user->projects()->create(['name' => 'Quincaillerie Ama']);
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => $text]);
        $project->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);

        return [$user, $project];
    }

    /**
     * @return array<string, string>
     */
    private function signed(string $body): array
    {
        $time = (string) time();

        return ['CONTENT_TYPE' => 'application/json', 'HTTP_X_KEKELI_HORODATAGE' => $time, 'HTTP_X_KEKELI_SIGNATURE' => RunnerSignature::sign(self::SECRET, $time, $body)];
    }
}
