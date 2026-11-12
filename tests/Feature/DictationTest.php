<?php

namespace Tests\Feature;

use App\Agent\AnswerModels;
use App\Agent\Energy;
use App\Agent\RunnerSignature;
use App\Models\Dictation;
use App\Models\ProviderUsage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Saisie vocale (12 novembre 2026), comme le micro de ChatGPT et de Claude : un bouton dans la zone de saisie, la
 * dictée du navigateur d'abord, la transcription d'OpenAI ensuite (comptée dans l'énergie), un message clair si le
 * micro est refusé.
 */
class DictationTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'secret-de-test-tres-long-pour-les-signatures-0123456789';

    protected function setUp(): void
    {
        parent::setUp();
        config(['kekeli.runner.secret' => self::SECRET, 'kekeli.runner.url' => 'http://runner.test']);
    }

    private function recording(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('dictee.webm', str_repeat('a', 2000))->mimeType('audio/webm');
    }

    public function test_the_input_zone_has_a_microphone_next_to_the_send_button_with_a_clear_message_when_it_is_refused(): void
    {
        $user = User::factory()->create(['country' => 'TG']);

        $this->actingAs($user)->get(route('projects.index'))->assertOk()
            ->assertSee('data-prompt-mic', false)
            ->assertSee('aria-label="Dictée vocale"', false)
            ->assertSee('kekeliDictee(', false)
            // Français par défaut, langue de l'interface ensuite.
            ->assertSee('fr-FR', false)
            ->assertSee('Pour dicter, autorisez le micro pour ce site dans les r', false);
        // Le micro est placé juste avant le bouton d'envoi.
        $html = $this->actingAs($user)->get(route('projects.index'))->getContent();
        $this->assertLessThan(strpos($html, 'data-prompt-send'), strpos($html, 'data-prompt-mic'));
    }

    public function test_a_recording_is_written_by_the_runner_and_its_cost_goes_into_the_energy_and_the_openai_ceiling(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        Http::fake(['runner.test/transcrire' => Http::response(['ok' => true, 'texte' => 'je veux un site pour mon restaurant', 'secondes' => 30])]);

        $this->actingAs($user)->post(route('dictation.store'), ['audio' => $this->recording(), 'duree' => 30], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJson(['texte' => 'je veux un site pour mon restaurant']);

        Http::assertSent(fn (Request $request) => $request->url() === 'http://runner.test/transcrire'
            && RunnerSignature::verify(self::SECRET, $request->header('X-Kekeli-Horodatage')[0], $request->header('X-Kekeli-Signature')[0], $request->body())
            && $request['type'] === 'audio/webm' && $request['langue'] === 'fr' && $request['modele'] === 'gpt-transcribe'
            && base64_decode($request['audio']) === str_repeat('a', 2000));

        // 30 secondes à 0,0045 $ la minute.
        $this->assertEqualsWithDelta(0.00225, (float) Dictation::sole()->cost_usd, 0.000001);
        $this->assertEqualsWithDelta(0.00225 * 590, app(Energy::class)->usedFcfa($user), 0.001);
        $this->assertEqualsWithDelta(0.00225, app(AnswerModels::class)->openAiSpentToday(), 0.000001);
    }

    public function test_no_dictation_without_energy_or_above_the_daily_ceiling_and_never_for_a_visitor(): void
    {
        Http::fake(['*' => Http::response(['ok' => true, 'texte' => 'texte'])]);
        $user = User::factory()->create(['country' => 'TG']);

        $this->post(route('dictation.store'), ['audio' => $this->recording()], ['Accept' => 'application/json'])->assertUnauthorized();

        ProviderUsage::create(['day' => CarbonImmutable::now('UTC')->toDateString(), 'provider' => 'openai', 'model' => 'gpt-6-luna'])->forceFill(['cost_usd' => 5])->save();
        $this->actingAs($user)->post(route('dictation.store'), ['audio' => $this->recording()], ['Accept' => 'application/json'])
            ->assertStatus(503)->assertJson(['erreur' => "La dictée n'est pas disponible pour le moment. Vous pouvez écrire votre message."]);

        Http::assertNothingSent();
        $this->assertSame(0, Dictation::count());
    }

    public function test_a_file_that_is_not_a_recording_is_refused(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        Http::fake();

        $this->actingAs($user)->post(route('dictation.store'), ['audio' => UploadedFile::fake()->create('page.html', 5, 'text/html')], ['Accept' => 'application/json'])->assertUnprocessable();
        Http::assertNothingSent();
    }
}
