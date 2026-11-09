<?php

namespace Tests\Feature;

use App\Agent\AgentPlaces;
use App\Agent\RunnerClient;
use App\Jobs\SendAgentRun;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CountriesAndQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'kekeli.runner.secret' => 'secret-de-test-tres-long-pour-les-signatures-0123456789',
            'kekeli.runner.url' => 'http://runner.test',
            'kekeli.providers_order' => 'gemini',
        ]);
        Http::fake(['runner.test/*' => Http::response(['accepte' => true], 202)]);
    }

    // Pays du monde entier

    public function test_every_country_of_the_world_can_be_chosen_in_french(): void
    {
        $countries = config('kekeli.countries');

        $this->assertGreaterThan(240, count($countries));
        $this->assertSame('Japon', $countries['JP']['name']);
        $this->assertSame('Brésil', $countries['BR']['name']);
        $this->assertSame('Lomé', $countries['TG']['city']);
        $this->assertSame('République démocratique du Congo', $countries['CD']['name']);
        // Aucun ancien code de pays (Allemagne de l'Est, Haute-Volta, Zaïre…).
        foreach (['DD', 'HV', 'ZR', 'SU', 'YU', 'DY'] as $old) {
            $this->assertArrayNotHasKey($old, $countries);
        }

        $this->get('/register')->assertSee('Japon')->assertSee('Brésil')->assertSee('Togo');
        Volt::test('auth.register')
            ->set('name', 'Haruto')
            ->set('email', 'haruto@exemple.jp')
            ->set('password', 'motdepasse-solide-2026')
            ->set('password_confirmation', 'motdepasse-solide-2026')
            ->set('country', 'JP')
            ->set('terms', true)
            ->call('register')
            ->assertHasNoErrors();
    }

    public function test_a_country_without_details_still_tells_the_runner_its_name_and_currency(): void
    {
        $user = User::factory()->create(['country' => 'BR']);

        $this->assertSame('Brésil', $user->countryDetails()['name']);
        $this->assertSame('Brazil', $user->countryDetails()['english']);
        $this->assertStringContainsString('BRL', $user->countryDetails()['currency']);
    }

    // Pays bientôt disponibles

    public function test_countries_where_free_gemini_is_forbidden_see_coming_soon(): void
    {
        foreach (['FR', 'DE', 'GB', 'CH', 'NO', 'RE'] as $code) {
            $this->assertContains($code, config('kekeli.coming_soon_countries'), $code);
        }

        $user = User::factory()->create(['country' => 'FR']);
        $project = $user->projects()->create(['name' => 'Boulangerie']);

        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertSee('Bientôt disponible dans votre pays')
            ->assertDontSee('data-message-input', false);

        Volt::actingAs($user)->test('projects.show', ['project' => $project])
            ->set('content', 'Crée mon site')
            ->call('send');
        $this->assertSame(0, AgentRun::count());
    }

    public function test_the_country_of_the_connection_is_also_checked(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $project = $user->projects()->create(['name' => 'Boutique']);

        $this->actingAs($user)->get(route('projects.show', $project), ['CF-IPCountry' => 'DE'])
            ->assertSee('Bientôt disponible dans votre pays');
        $this->actingAs($user)->get(route('projects.show', $project), ['CF-IPCountry' => 'TG'])
            ->assertDontSee('Bientôt disponible dans votre pays')
            ->assertSee('data-message-input', false);
    }

    // File d'attente : 10 demandes en même temps au plus

    public function test_beyond_the_limit_requests_wait_for_their_turn_without_loss(): void
    {
        app(Settings::class)->set('max_concurrent_runs', 2);
        $busy1 = $this->makeRun(['status' => AgentRun::STATUS_RUNNING, 'sent_at' => now()]);
        $busy2 = $this->makeRun(['status' => AgentRun::STATUS_QUEUED, 'sent_at' => now()]);
        $first = $this->makeRun();
        $this->travel(1)->seconds();
        $second = $this->makeRun();

        // Toutes les places sont prises : la demande revient plus tard, sans être envoyée ni perdue.
        $job = (new SendAgentRun($first))->withFakeQueueInteractions();
        $job->handle(app(RunnerClient::class));
        $job->assertReleased(SendAgentRun::WAIT_SECONDS);
        Http::assertNothingSent();
        $this->assertSame(AgentRun::STATUS_QUEUED, $first->fresh()->status);
        $this->assertNull($first->fresh()->sent_at);

        $places = app(AgentPlaces::class);
        $this->assertSame(1, $places->position($first->fresh()));
        $this->assertSame(2, $places->position($second->fresh()));

        // Le client voit sa position dans la file d'attente.
        $this->actingAs($second->project->user)->get(route('projects.show', $second->project))
            ->assertSee('Votre demande attend son tour (position 2)')
            ->assertSee('vous ne perdez rien');

        // Une place se libère : la plus ancienne demande passe la première, jamais la suivante.
        $busy1->update(['status' => AgentRun::STATUS_COMPLETED]);
        $job = (new SendAgentRun($second))->withFakeQueueInteractions();
        $job->handle(app(RunnerClient::class));
        $job->assertReleased();

        $job = (new SendAgentRun($first))->withFakeQueueInteractions();
        $job->handle(app(RunnerClient::class));
        $job->assertNotReleased();
        $this->assertNotNull($first->fresh()->sent_at);
        Http::assertSentCount(1);
        $this->assertNull($places->position($first->fresh()));
    }

    public function test_a_lost_request_does_not_hold_a_place_forever(): void
    {
        app(Settings::class)->set('max_concurrent_runs', 1);
        $lost = $this->makeRun(['status' => AgentRun::STATUS_RUNNING, 'sent_at' => now()]);
        $this->travel(2)->hours();
        $run = $this->makeRun();

        $job = (new SendAgentRun($run))->withFakeQueueInteractions();
        $job->handle(app(RunnerClient::class));
        $job->assertNotReleased();
        $this->assertSame(0, app(AgentPlaces::class)->occupied() - 1);
        $this->assertNotNull($lost->fresh());
    }

    public function test_no_limit_when_the_setting_is_zero(): void
    {
        app(Settings::class)->set('max_concurrent_runs', 0);
        foreach (range(1, 3) as $i) {
            $this->makeRun(['status' => AgentRun::STATUS_RUNNING, 'sent_at' => now()]);
        }
        $run = $this->makeRun();

        $job = (new SendAgentRun($run))->withFakeQueueInteractions();
        $job->handle(app(RunnerClient::class));
        $job->assertNotReleased();
        Http::assertSentCount(1);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeRun(array $attributes = []): AgentRun
    {
        /** @var Project $project */
        $project = User::factory()->create()->projects()->create(['name' => 'Projet']);
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Crée mon site']);
        $run = $project->agentRuns()->create(['message_id' => $message->id]);
        $run->forceFill($attributes)->save();

        return $run;
    }
}
