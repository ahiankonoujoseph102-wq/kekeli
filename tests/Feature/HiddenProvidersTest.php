<?php

namespace Tests\Feature;

use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\ProviderUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Finitions du 12 novembre 2026 : fournisseurs de Kekeli cachés au client (comme Bolt.new), choix guidé du fournisseur
 * pour SA clé, phrase de la politique de confidentialité.
 */
class HiddenProvidersTest extends TestCase
{
    use RefreshDatabase;

    private const NAMES = '/\b(openai|gpt-?\d|gemini|tavily|exa\b|cloudflare|openrouter|groq|mistral|anthropic|claude|sonnet|haiku)/i';

    /** The visible text of a page, without scripts, styles and attributes. */
    private function text(string $html): string
    {
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html);
        // Une bibliothèque publique (cdnjs.cloudflare.com) dans les sources libres n'est pas un fournisseur de Kekeli.
        $html = str_replace('cdnjs.cloudflare.com', 'cdnjs', (string) $html);

        return html_entity_decode(strip_tags((string) $html));
    }

    public function test_the_client_never_sees_the_models_or_providers_of_kekeli(): void
    {
        $user = User::factory()->create(['name' => 'Afi Mensah', 'email' => 'afi@exemple.tg', 'country' => 'TG', 'plan' => 'pro', 'plan_expires_at' => now()->addDays(10)]);
        $project = $user->projects()->make(['name' => 'Belle Afi']);
        $project->forceFill(['kind' => Project::KIND_WEBAPP, 'is_application' => true, 'app_stack' => 'laravel', 'app_template' => 'socle'])->save();
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'ajoute la page profil']);
        $run = $project->agentRuns()->create(['message_id' => $message->id]);
        $run->steps()->create(['kind' => 'fichier_lu', 'target' => 'routes/web.php']);
        $run->forceFill(['status' => AgentRun::STATUS_COMPLETED, 'finished_at' => now(), 'requests' => 12, 'input_tokens' => 9000, 'model_cost_usd' => 0.2, 'report' => ['done' => 'La page profil est prête.', 'note' => null, 'files' => [], 'files_count' => 0, 'tests' => [], 'problems' => []]])->save();
        ProviderUsage::create(['day' => now()->toDateString(), 'provider' => 'openai', 'model' => 'gpt-6.1-sol']);

        foreach ([route('projects.show', $project), route('projects.index'), route('plans'), route('settings.plan'), route('settings.profile')] as $page) {
            $text = $this->text($this->actingAs($user)->get($page)->assertOk()->getContent());
            preg_match_all(self::NAMES, $text, $found, PREG_OFFSET_CAPTURE);
            $this->assertSame([], array_map(fn ($m) => mb_substr($text, max(0, $m[1] - 60), 120), $found[0]), $page);
        }

        // L'administrateur, lui, voit les fournisseurs.
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.providers'))->assertOk()->assertSee('Gemini');
    }

    public function test_the_client_chooses_the_provider_of_his_own_key_and_the_address_fills_in_by_itself(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $project = $user->projects()->make(['name' => 'Assistante']);
        $project->forceFill(['kind' => Project::KIND_WEBAPP, 'is_agent' => true, 'app_stack' => 'langgraph', 'app_template' => 'socle'])->save();

        $html = $this->actingAs($user)->get(route('projects.show', $project))->assertOk()->getContent();
        $this->assertStringContainsString('data-model-form', $html);
        foreach (['api.openai.com', 'generativelanguage.googleapis.com', 'api.mistral.ai', 'api.groq.com', 'openrouter.ai'] as $host) {
            $this->assertStringContainsString($host, $html);
        }
        $this->assertStringContainsString('https://api.openai.com/v1', html_entity_decode(str_replace('\/', '/', $html)));

        Volt::actingAs($user)->test('projects.show', ['project' => $project])
            ->set('modelProvider', 'gemini')
            ->assertSee("Avec l'offre gratuite de Google, les messages peuvent être utilisés pour améliorer ses modèles")
            ->set('modelKey', 'cle-inventee-pour-le-test')
            ->call('connectModel')
            ->assertHasNoErrors();

        $secrets = $project->secrets()->get()->mapWithKeys(fn ($s) => [$s->name => $s->value])->all();
        $this->assertSame(['MODELE_API_CLE' => 'cle-inventee-pour-le-test', 'MODELE_API_URL' => 'https://generativelanguage.googleapis.com/v1beta/openai/', 'MODELE_NOM' => 'gemini-3.5-flash-lite'], collect($secrets)->sortKeys()->all());
        // La clé n'est jamais réaffichée.
        $this->assertStringNotContainsString('cle-inventee-pour-le-test', $this->actingAs($user)->get(route('projects.show', $project))->getContent());

        Volt::actingAs($user)->test('projects.show', ['project' => $project])->set('modelProvider', 'inconnu')->set('modelKey', 'x')->call('connectModel')->assertHasErrors('modelProvider');
    }

    public function test_the_privacy_policy_says_that_the_exchanges_can_improve_the_models(): void
    {
        $this->get(route('legal.privacy'))->assertOk()
            ->assertSee('data-improve-models', false)
            ->assertSee('peuvent être utilisés par Kekeli et par ses fournisseurs d\'intelligence artificielle pour améliorer les modèles', false)
            ->assertSee('OpenAI');
    }
}
