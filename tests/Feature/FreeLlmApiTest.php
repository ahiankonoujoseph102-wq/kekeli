<?php

namespace Tests\Feature;

use App\Agent\AnswerModels;
use App\Agent\Costs;
use App\Agent\FreeLlmApi;
use App\Agent\ModelRouter;
use App\Agent\RunnerClient;
use App\Http\Controllers\RunnerFreellmapiBackupController;
use App\Models\AgentRun;
use App\Models\Project;
use App\Models\ProviderUsage;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * FreeLLMAPI à la place de Coarena (docs/plan.md, partie 26.38) : dernier dans les listes, part de chaque client par
 * jour, plafond global, identifiant opaque du client, sauvegarde chiffrée que le site ne peut pas lire, prix estimés,
 * administration sans aucune clé, plafond OpenAI des constructions inchangé, Coarena retiré.
 */
class FreeLlmApiTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'secret-de-test-freellmapi';

    protected function setUp(): void
    {
        parent::setUp();
        config(['kekeli.settings.model_providers' => ['openai' => true, 'gratuits' => true, 'freellmapi' => true]]);
        config(['kekeli.runner.url' => 'http://runner.test', 'kekeli.runner.secret' => self::SECRET]);
    }

    public function test_freellmapi_comes_after_the_free_offers_then_openai_last_and_never_coarena(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $usual = app(AnswerModels::class)->forRunner($user);
        $list = app(ModelRouter::class)->answerRoute($user, 'reponse_simple', $usual);
        $names = array_map(fn ($e) => $e['fournisseur'].'/'.$e['modele'], $list);

        // Les gratuits d'abord, puis FreeLLMAPI, puis OpenAI en tout dernier (crédit épuisé, décision du 15 novembre 2026).
        $this->assertNotSame('openai', $list[0]['fournisseur']);
        $this->assertSame(['openai/gpt-6.1-terra', 'openai/gpt-6-luna'], array_slice($names, -2));
        // Ordre mesuré du 15 novembre 2026 (« free_order ») : Gemini 3.1 Flash-Lite, puis Groq (gpt-oss-120b), puis les
        // autres gratuits, Z.ai (glm-4.5-flash) le plus lent en dernier des listés, puis le reste de FreeLLMAPI.
        $this->assertSame(['gemini/gemini-3.1-flash-lite', 'freellmapi/gpt-oss-120b', 'gemini/gemini-3.5-flash-lite'], array_slice($names, 0, 3));
        $this->assertLessThan(array_search('freellmapi/glm-4.5-flash', $names, true), array_search('openrouter/google/gemma-4-31b-it:free', $names, true));
        $this->assertSame(['freellmapi/qwen3.8-27b', 'freellmapi/gpt-oss-20b', 'freellmapi/longcat-2.5', 'freellmapi/glm-4.7-flash'], array_slice($names, -6, 4));
        $this->assertEmpty(array_filter($names, fn ($n) => str_starts_with($n, 'coarena/')));

        // Une construction : les familles de FreeLLMAPI pour construire, après la liste habituelle.
        $build = app(ModelRouter::class)->route($user, 'site', $usual);
        $free = array_values(array_filter($usual, fn ($e) => $e['fournisseur'] !== 'openai'));
        $this->assertSame(array_slice($build, 0, count($free)), $free);
        $this->assertSame(['longcat-2.5', 'glm-4.7-flash'], array_column(array_slice($build, count($free), 2), 'modele'));
        $this->assertSame('openai', end($build)['fournisseur']);
        $this->assertSame(['glm-4.5-flash', 'longcat-2.5', 'glm-4.7-flash', 'gpt-oss-120b'], array_column(app(FreeLlmApi::class)->entries($user, 'document'), 'modele'));

        // Interrupteur coupé : plus aucune entrée FreeLLMAPI.
        app(Settings::class)->set('model_providers', ['openai' => true, 'gratuits' => true, 'freellmapi' => false]);
        $this->assertSame([], array_filter(app(ModelRouter::class)->answerRoute($user, 'reponse_simple', $usual), fn ($e) => $e['fournisseur'] === 'freellmapi'));
    }

    public function test_only_the_closed_list_is_kept_never_auto_nor_a_paid_model(): void
    {
        app(Settings::class)->set('freellmapi_routes', ['discussion' => ['auto', 'gpt-oss-120b', 'glm-5.3-flash', 'GPT-OSS-20B']]);
        $this->assertSame(['gpt-oss-120b'], app(FreeLlmApi::class)->models('discussion'));
    }

    public function test_each_client_has_a_share_per_day_and_all_clients_a_ceiling(): void
    {
        $free = User::factory()->create(['plan' => 'gratuite']);
        $project = $free->projects()->create(['name' => 'Essai']);
        app(Settings::class)->set('freellmapi_requests_per_day', ['gratuite' => 2, 'pro' => 200]);
        $this->assertSame(2, app(FreeLlmApi::class)->userLeft($free));

        foreach ([1, 2] as $i) {
            $project->agentRuns()->create([])->forceFill(['provider' => 'freellmapi', 'model' => 'groq/openai/gpt-oss-120b', 'status' => 'completed'])->save();
        }
        $this->assertSame(0, app(FreeLlmApi::class)->userLeft($free));
        $this->assertSame([], app(FreeLlmApi::class)->entries($free, 'reponse_simple'), 'part du jour utilisée : plus de FreeLLMAPI pour ce client');

        // Un autre client garde sa part ; le plafond global l'arrête aussi.
        $other = User::factory()->create(['plan' => 'gratuite']);
        $this->assertNotEmpty(app(FreeLlmApi::class)->entries($other, 'reponse_simple'));
        ProviderUsage::create(['day' => now('UTC')->toDateString(), 'provider' => 'freellmapi', 'model' => 'groq/openai/gpt-oss-120b'])->forceFill(['requests' => 2000])->save();
        $this->assertSame(0, app(FreeLlmApi::class)->globalLeft());
        $this->assertSame([], app(FreeLlmApi::class)->entries($other, 'reponse_simple'));
        app(Settings::class)->set('freellmapi_daily_cap', 0);
        $this->assertNotEmpty(app(FreeLlmApi::class)->entries($other, 'reponse_simple'), '0 = aucun plafond global');
    }

    public function test_the_runner_receives_an_opaque_client_and_the_conversation_never_coarena_settings(): void
    {
        $this->useRealFirstResponder();
        Http::fake(['runner.test/repondre' => Http::response(['ok' => true, 'decision' => 'repondre', 'reponse' => 'Lomé.', 'fournisseur' => 'freellmapi', 'modele' => 'groq/openai/gpt-oss-120b', 'refus' => []])]);
        $user = User::factory()->create(['email' => 'ama@example.test']);
        $chat = $user->projects()->make(['name' => 'Question']);
        $chat->forceFill(['kind' => Project::KIND_CHAT])->save();
        $message = $chat->messages()->create(['role' => 'user', 'content' => 'Quelle est la capitale du Togo ?']);
        $run = $chat->agentRuns()->create(['message_id' => $message->id, 'purpose' => 'question']);

        app(RunnerClient::class)->respond($run, 'discussion');

        Http::assertSent(function ($request) use ($user, $chat) {
            $body = $request->data();

            return $body['contexte']['client'] === FreeLlmApi::clientId($user)
                && strlen($body['contexte']['client']) === 24
                && ! str_contains($request->body(), 'ama@example.test')
                && $body['contexte']['session'] === (string) $chat->id
                && end($body['fournisseurs'])['fournisseur'] === 'openai'
                && collect($body['fournisseurs'])->contains('fournisseur', 'freellmapi')
                && ! array_key_exists('coarena', $body);
        });
    }

    public function test_the_real_model_served_is_counted_with_the_estimated_price_of_its_family(): void
    {
        $costs = app(Costs::class);
        $this->assertEqualsWithDelta(0.15 * 3 + 0.60 * 1, $costs->modelCost('freellmapi', 'groq/openai/gpt-oss-120b', 3_000_000, 1_000_000, 0, 0), 0.0001);
        $this->assertEqualsWithDelta(0.07 + 0.40, $costs->modelCost('freellmapi', 'zhipu/glm-4.7-flash', 1_000_000, 1_000_000, 0, 0), 0.0001);
        // Une famille sans prix connu : l'estimation par défaut, jamais zéro.
        $this->assertEqualsWithDelta(0.30, $costs->modelCost('freellmapi', 'longcat/longcat-2.5', 1_000_000, 0, 0, 0), 0.0001);
        // Un prix enregistré dans l'administration sans les lignes de FreeLLMAPI : la configuration reste lue.
        app(Settings::class)->set('model_prices', ['gemini/gemini-3.5-flash-lite' => ['label' => 'x', 'input' => 1, 'output' => 1, 'cache' => 0]]);
        $this->assertEqualsWithDelta(0.15, $costs->modelCost('freellmapi', 'groq/openai/gpt-oss-120b', 1_000_000, 0, 0, 0), 0.0001);
    }

    public function test_the_openai_ceiling_no_longer_stops_a_construction(): void
    {
        // OpenAI en dernier depuis le 15 novembre 2026 : les fournisseurs gratuits construisent sans attendre OpenAI.
        $user = User::factory()->create();
        app(Settings::class)->set('openai_daily_cap_usd', 0.0);
        $this->assertFalse(app(AnswerModels::class)->buildCapReached($user, 'site'));
    }

    public function test_the_backup_is_kept_only_with_the_runner_token_and_only_in_freellmapi_format(): void
    {
        Storage::fake(config('kekeli.backups.disk'));
        $token = hash_hmac('sha256', 'freellmapi:sauvegarde', self::SECRET);
        $this->assertSame($token, FreeLlmApi::backupToken());
        $url = route('runner.freellmapi.backup.store', ['empreinte' => 'abcdef012345']);

        $this->call('PUT', $url, [], [], [], ['CONTENT_TYPE' => 'application/octet-stream'], "FAPIBK1\0données")->assertUnauthorized();
        $this->call('PUT', $url, [], [], [], ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_AUTHORIZATION' => 'Bearer faux'], "FAPIBK1\0données")->assertUnauthorized();
        $this->get($url, ['Authorization' => 'Bearer '.$token])->assertNotFound();
        $this->call('PUT', $url, [], [], [], ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_AUTHORIZATION' => 'Bearer '.$token], 'autre chose')->assertStatus(422);
        $this->call('PUT', $url, [], [], [], ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_AUTHORIZATION' => 'Bearer '.$token], "FAPIBK1\0chiffré")->assertNoContent();

        Storage::disk(config('kekeli.backups.disk'))->assertExists(RunnerFreellmapiBackupController::path('abcdef012345'));
        // Une autre empreinte (autres secrets) : aucune sauvegarde, FreeLLMAPI repart d'une base neuve.
        $this->get(route('runner.freellmapi.backup.show', ['empreinte' => '999999999999']), ['Authorization' => 'Bearer '.$token])->assertNotFound();
        $this->assertSame("FAPIBK1\0chiffré", $this->get($url, ['Authorization' => 'Bearer '.$token])->assertOk()->getContent());
        $this->get($url)->assertUnauthorized();
    }

    public function test_the_administration_shows_freellmapi_and_its_providers_without_any_key(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Http::fake(['runner.test/freellmapi/etat' => Http::response([
            'demande' => true, 'pret' => true, 'plateformes' => ['groq'], 'fournisseurs' => [['nom' => 'groq', 'etat' => 'healthy', 'actif' => true, 'requetes_mois' => 12, 'tokens_mois' => 34000, 'pauses' => 0]],
            'quotas' => [['nom' => 'groq', 'modeles' => 9, 'budget_mois' => '~30M', 'restant' => 7000, 'limite' => 8000, 'unite' => 'tokens']],
            'version' => 'bce469c3994f460b97cbf0e2b32fc2e47fcd4e88', 'sauvegarde' => true, 'erreur' => null,
        ])]);
        ProviderUsage::create(['day' => now('UTC')->toDateString(), 'provider' => 'freellmapi', 'model' => 'groq/openai/gpt-oss-120b'])->forceFill(['requests' => 1700, 'rate_limited' => 3, 'input_tokens' => 1_000_000, 'output_tokens' => 100_000])->save();

        $page = Volt::actingAs($admin)->test('admin.models')
            ->assertSee('FreeLLMAPI')
            ->assertSee('bce469c')
            ->assertSee('groq/openai/gpt-oss-120b')
            ->assertSee('7 000 / 8 000')
            ->assertSee('1 700 sur 2 000 demandes (85 %)')
            ->assertSee('data-freellmapi-alert', false)
            ->assertDontSee('Coarena')
            ->assertDontSee('gsk_');

        $page->set('routes.discussion', 'gpt-oss-120b, auto')->call('saveFreellmapi')->assertHasErrors('routes.discussion');
        $page->set('routes.discussion', 'gpt-oss-20b, qwen3.8-27b')->set('perPlan.gratuite', 30)->set('dailyCap', 5000)->call('saveFreellmapi')->assertHasNoErrors();
        $this->assertSame(['gpt-oss-20b', 'qwen3.8-27b'], app(Settings::class)->get('freellmapi_routes')['discussion']);
        $this->assertSame(30, app(Settings::class)->get('freellmapi_requests_per_day')['gratuite']);
        $this->assertSame(5000, app(Settings::class)->int('freellmapi_daily_cap'));

        $page->set('providers.freellmapi', false)->call('saveSwitches');
        $this->assertFalse(app(FreeLlmApi::class)->enabled());
    }

    public function test_the_page_still_opens_when_the_runner_does_not_answer(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Http::fake(['runner.test/*' => Http::response([], 500)]);
        $this->actingAs($admin)->get(route('admin.models'))->assertOk()->assertSee('data-freellmapi-unreachable', false);
    }

    public function test_the_migration_removes_the_coarena_settings_and_adds_the_freellmapi_switch(): void
    {
        foreach (['coarena_multi_accounts' => true, 'coarena_disabled_keys' => ['COARENA_API_KEY_3'], 'coarena_key_checks' => [], 'model_routes' => ['site' => []], 'economy_below_percent' => 15, 'openai_daily_cap_usd' => 10.0] as $key => $value) {
            DB::table('settings')->insert(['key' => $key, 'value' => json_encode($value), 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('settings')->insert(['key' => 'model_providers', 'value' => json_encode(['coarena' => true, 'openai' => false, 'gratuits' => true]), 'created_at' => now(), 'updated_at' => now()]);

        $migration = require database_path('migrations/2026_11_29_000001_replace_coarena_by_freellmapi.php');
        $migration->up();
        $migration->up();

        $this->assertEqualsCanonicalizing(['openai_daily_cap_usd', 'model_providers'], DB::table('settings')->pluck('key')->all());
        // L'interrupteur d'OpenAI choisi à la main n'est jamais changé.
        $this->assertSame(['openai' => false, 'gratuits' => true, 'freellmapi' => true], json_decode(DB::table('settings')->where('key', 'model_providers')->value('value'), true));
    }

    public function test_coarena_is_gone_from_the_code(): void
    {
        $this->assertFileDoesNotExist(base_path('runner/src/coarena.js'));
        $this->assertNotContains('coarena', ModelRouter::PROVIDERS);
        foreach (['app/Agent/ModelRouter.php', 'app/Agent/RunnerClient.php', 'app/Agent/AnswerModels.php', 'runner/src/repondant.js', 'runner/src/passerelle.js', 'runner/src/fournisseurs.js', 'runner/src/server.js', 'runner/src/documents/redaction.js'] as $file) {
            $this->assertStringNotContainsString('registrePour', (string) file_get_contents(base_path($file)), $file);
            $this->assertDoesNotMatchRegularExpression("/'coarena'/", (string) file_get_contents(base_path($file)), $file);
        }
        $this->assertSame(AgentRun::class, get_class(new AgentRun));
    }
}
