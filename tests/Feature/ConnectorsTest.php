<?php

namespace Tests\Feature;

use App\Jobs\RespondToMessage;
use App\Models\AgentRun;
use App\Models\ConnectorAction;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use App\Models\UserConnector;
use App\Support\Connectors;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Connecteurs (MCP), comme Claude et ChatGPT (docs/plan.md, partie 26.16) : catalogue, connexion OAuth faite par le
 * Runner (simulé ici), jetons chiffrés propres à chaque client, « + » → « Connecteurs », lecture libre, accord avant
 * toute action qui modifie, refus, « Toujours autoriser », connecteur personnalisé, limite de la formule Gratuite.
 */
class ConnectorsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['kekeli.runner.secret' => 'secret-de-test', 'kekeli.runner.url' => 'http://runner.test']);
    }

    private function connected(User $user, string $key = 'notion', array $extra = []): UserConnector
    {
        $entry = config('kekeli.connectors.'.$key);

        return $user->connectors()->create(array_merge(['catalog_key' => $key, 'name' => $entry['name'], 'url' => $entry['url'], 'auth' => $entry['auth'], 'access_token' => 'jeton-acces-notion', 'refresh_token' => 'jeton-renouvellement', 'token_expires_at' => now()->addHour(), 'client' => ['client_id' => 'client-kekeli'], 'connected_at' => now()], $extra));
    }

    private function chat(User $user, string $text = 'Que dit ma page Notion sur le menu ?'): array
    {
        $project = $user->projects()->create(['name' => 'Discussion']);
        $project->forceFill(['kind' => Project::KIND_CHAT])->save();
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => $text]);
        $run = $project->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);

        return [$project, $run];
    }

    public function test_the_tab_and_the_catalog_appear_only_when_the_connectors_really_work(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        config(['kekeli.runner.secret' => null]);
        $this->actingAs($user)->get(route('settings.connectors'))->assertNotFound();

        config(['kekeli.runner.secret' => 'secret-de-test']);
        $this->actingAs($user)->get(route('settings.profile'))->assertSee('data-settings-tab="settings.connectors"', false);
        $this->actingAs($user)->get(route('settings.connectors'))->assertOk()
            ->assertSee(['Notion', 'Airtable', 'Jira et Confluence', 'Linear', 'Miro', 'Hugging Face', 'DeepWiki'])
            ->assertSee('Chercher, lire et créer des pages dans votre Notion.')
            ->assertSee('Ajouter un connecteur personnalisé')
            ->assertSee('Journal des actions')
            ->assertSee('Formule Gratuite : 2 connecteurs actifs à la fois dans une discussion.')
            // Les fournisseurs de modèles de Kekeli restent cachés ; seuls les noms des connecteurs sont visibles.
            ->assertDontSee(['OpenAI', 'Gemini', 'gpt-6']);
    }

    public function test_connecting_with_oauth_keeps_encrypted_tokens_specific_to_each_client(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        Http::fake([
            'runner.test/connecteurs/preparer' => Http::response(['ok' => true, 'adresse' => 'https://mcp.notion.com/authorize?client_id=client-kekeli&state=x', 'verificateur' => 'verificateur-pkce', 'client' => ['client_id' => 'client-kekeli'], 'ressource' => 'https://mcp.notion.com/mcp']),
            'runner.test/connecteurs/echanger' => Http::response(['ok' => true, 'jeton' => 'jeton-acces-secret', 'renouvellement' => 'jeton-renouvellement-secret', 'expire_dans' => 3600]),
        ]);

        $redirect = $this->actingAs($user)->get(route('connectors.connect', 'notion'))->assertRedirect()->headers->get('Location');
        $this->assertStringStartsWith('https://mcp.notion.com/authorize', $redirect);
        $state = array_key_first(session('connecteurs_oauth'));
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/connecteurs/preparer') && $request['redirection'] === route('connectors.callback') && $request['url'] === 'https://mcp.notion.com/mcp');

        // Un mauvais état est refusé ; le bon termine la connexion.
        $this->actingAs($user)->get(route('connectors.callback', ['state' => 'mauvais', 'code' => 'c']))->assertRedirect(route('settings.connectors'));
        $this->actingAs($user)->get(route('connectors.callback', ['state' => $state, 'code' => 'code-recu']))->assertRedirect(route('settings.connectors'))->assertSessionHas('connector-status', 'Notion est connecté.');
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/connecteurs/echanger') && $request['code'] === 'code-recu' && $request['verificateur'] === 'verificateur-pkce');

        $connector = UserConnector::sole();
        $this->assertTrue($connector->isConnected());
        $this->assertSame('jeton-acces-secret', $connector->access_token);
        $raw = DB::table('user_connectors')->first();
        $this->assertStringNotContainsString('jeton-acces-secret', (string) $raw->access_token);
        $this->assertStringNotContainsString('jeton-renouvellement-secret', (string) $raw->refresh_token);
        $html = $this->actingAs($user)->get(route('settings.connectors'))->assertOk()->assertSee('data-connected="oui"', false)->getContent();
        $this->assertStringNotContainsString('jeton-acces-secret', $html);

        // Un autre client ne voit ni ne déconnecte jamais ce connecteur.
        $other = User::factory()->create(['country' => 'TG']);
        $this->actingAs($other)->delete(route('connectors.destroy', $connector))->assertNotFound();
        $this->assertSame([], app(Connectors::class)->forRunner($other, null));

        // « Déconnecter » efface les jetons.
        $this->actingAs($user)->delete(route('connectors.destroy', $connector))->assertRedirect(route('settings.connectors'));
        $this->assertSame(0, UserConnector::count());
    }

    public function test_a_custom_connector_is_probed_and_internal_addresses_are_refused(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        Http::fake(['runner.test/connecteurs/sonder' => Http::sequence()
            ->push(['ok' => true, 'connexion' => 'aucune', 'outils' => 3])
            ->push(['ok' => true, 'connexion' => 'impossible', 'raison' => 'inscription'])]);

        foreach (['http://mcp.exemple.tg/mcp', 'https://localhost/mcp', 'https://10.0.0.2/mcp', 'https://192.168.1.4/mcp', 'https://serveur.internal/mcp'] as $bad) {
            $this->actingAs($user)->post(route('connectors.store'), ['name' => 'Mauvais', 'url' => $bad])->assertSessionHasErrors('url', null, 'connecteur');
        }
        Http::assertNothingSent();

        $this->actingAs($user)->post(route('connectors.store'), ['name' => 'Documentation', 'url' => 'https://mcp.exemple.tg/mcp'])->assertSessionHas('connector-status', 'Documentation est connecté.');
        $this->assertTrue(UserConnector::sole()->isConnected());
        $this->assertSame(UserConnector::AUTH_NONE, UserConnector::sole()->auth);

        $this->actingAs($user)->post(route('connectors.store'), ['name' => 'Fermé', 'url' => 'https://ferme.exemple.tg/mcp'])
            ->assertSessionHasErrors(['url' => 'Ce serveur demande une application enregistrée chez son éditeur : Kekeli ne peut pas s’y connecter tout seul.'], null, 'connecteur');
        $this->assertSame(1, UserConnector::count());
    }

    public function test_the_plus_menu_turns_connectors_on_and_off_with_two_at_most_for_the_free_formula(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $a = $this->connected($user, 'notion');
        $b = $this->connected($user, 'airtable');
        $c = $this->connected($user, 'huggingface', ['access_token' => null, 'auth' => 'aucune', 'active' => false]);
        [$project] = $this->chat($user);

        // Les deux premiers sont actifs (limite de la formule Gratuite) ; un troisième est refusé avec une phrase claire.
        $this->assertSame([$b->id, $a->id], app(Connectors::class)->activeIds($user, $project));
        Volt::actingAs($user)->test('projects.show', ['project' => $project])
            ->assertSee('Connecteurs')->assertSee('Gérer les connecteurs')
            ->call('toggleConnector', $c->id)
            ->assertSee('Formule Gratuite : 2 connecteurs actifs au plus.')
            ->call('toggleConnector', $a->id)
            ->call('toggleConnector', $c->id)
            ->assertSet('connectorNotice', null);
        $this->assertEqualsCanonicalizing([$b->id, $c->id], $project->fresh()->connectors);

        // Réglable dans l'administration ; les formules payantes n'ont pas de limite.
        app(Settings::class)->set('free_active_connectors_max', 1);
        $this->assertCount(1, app(Connectors::class)->activeIds($user, $project->fresh()));
        $user->forceFill(['plan' => 'pro', 'plan_expires_at' => now()->addDays(30)])->save();
        $this->assertNull(app(Connectors::class)->maxActive($user->fresh()));
    }

    public function test_a_read_is_done_and_journaled_and_only_the_paid_model_receives_the_data(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $connector = $this->connected($user);
        [$project, $run] = $this->chat($user);

        $this->useRealFirstResponder();
        Http::fake(['runner.test/repondre' => Http::response(['ok' => true, 'decision' => 'repondre', 'reponse' => 'Dans votre Notion, le riz gras est à 1 500 FCFA.', 'fournisseur' => 'openai', 'modele' => 'gpt-6-luna', 'tokens_entree' => 1800, 'tokens_sortie' => 90, 'requetes' => 2, 'refus' => [],
            'actions_connecteurs' => [['connecteur' => $connector->id, 'nom' => 'Notion', 'outil' => 'notion-search', 'ecriture' => false, 'statut' => 'fait', 'resume' => ['connecteur' => 'Notion', 'action' => 'Chercher des pages', 'details' => [['nom' => 'query', 'valeur' => 'menu']]]]]])]);
        RespondToMessage::dispatchSync($run);

        Http::assertSent(function (Request $request) use ($connector) {
            return $request['connecteurs'] === [['id' => $connector->id, 'nom' => 'Notion', 'url' => 'https://mcp.notion.com/mcp', 'jeton' => 'jeton-acces-notion', 'toujours' => []]]
                && collect($request['fournisseurs'])->pluck('fournisseur')->all() === ['openai']
                && ! str_contains($request->body(), 'jeton-renouvellement');
        });
        $this->assertSame('Dans votre Notion, le riz gras est à 1 500 FCFA.', $project->messages()->where('role', Message::ROLE_ASSISTANT)->value('content'));
        $action = ConnectorAction::sole();
        $this->assertSame([ConnectorAction::DONE, false, 'Chercher des pages'], [$action->status, $action->writes, $action->title()]);
        $this->assertGreaterThan(0, (float) $run->fresh()->model_cost_usd, 'le coût réel est compté dans l’énergie');

        $this->actingAs($user)->get(route('projects.show', $project))->assertOk()->assertSee('A utilisé Notion · 1 action');
        $this->actingAs($user)->get(route('settings.connectors'))->assertSee('Chercher des pages')->assertSee('data-journal-action="fait"', false);
    }

    public function test_an_action_that_changes_something_asks_first_then_allow_does_it_exactly_and_refuse_does_nothing(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $connector = $this->connected($user);
        [$project, $run] = $this->chat($user, 'Crée une page « Menu de la semaine » dans mon Notion');

        $this->useRealFirstResponder();
        $accord = ['connecteur' => $connector->id, 'outil' => 'notion-create-pages', 'arguments' => ['title' => 'Menu de la semaine'], 'resume' => ['connecteur' => 'Notion', 'action' => 'Créer des pages', 'details' => [['nom' => 'title', 'valeur' => 'Menu de la semaine']]]];
        Http::fake(['runner.test/repondre' => Http::sequence()
            ->push(['ok' => true, 'decision' => 'accord', 'accord' => $accord, 'fournisseur' => 'openai', 'modele' => 'gpt-6-luna', 'tokens_entree' => 900, 'tokens_sortie' => 40, 'requetes' => 1, 'refus' => []])
            ->push(['ok' => true, 'decision' => 'repondre', 'reponse' => 'C’est fait : la page « Menu de la semaine » est dans votre Notion.', 'fournisseur' => 'openai', 'modele' => 'gpt-6-luna', 'refus' => [],
                'actions_connecteurs' => [['connecteur' => $connector->id, 'nom' => 'Notion', 'outil' => 'notion-create-pages', 'ecriture' => true, 'statut' => 'fait', 'resume' => $accord['resume']]]])
            ->push(['ok' => true, 'decision' => 'accord', 'accord' => $accord, 'fournisseur' => 'openai', 'modele' => 'gpt-6-luna', 'refus' => []])]);
        RespondToMessage::dispatchSync($run);

        $pending = ConnectorAction::sole();
        $this->assertSame(ConnectorAction::PENDING, $pending->status);
        $this->assertStringNotContainsString('Menu de la semaine', (string) DB::table('connector_actions')->value('arguments'), 'arguments chiffrés');
        $page = Volt::actingAs($user)->test('projects.show', ['project' => $project])
            ->assertSee('Kekeli veut utiliser Notion')->assertSee('Créer des pages')->assertSee('Menu de la semaine')
            ->assertSee('Autoriser')->assertSee('Refuser')->assertSee('Toujours autoriser pour cet outil');

        // « Autoriser » avec « Toujours autoriser » : l'action vue est envoyée telle quelle au Runner, puis Kekeli termine.
        Queue::fake();
        $page->set('alwaysAllow', true)->call('approveConnector', $pending->id);
        Queue::assertPushed(RespondToMessage::class);
        $next = AgentRun::query()->where('connector_action_id', $pending->id)->sole();
        app()->call([new RespondToMessage($next), 'handle']);
        Http::assertSent(fn (Request $request) => ($request['accord'] ?? null) === ['decision' => 'autoriser', 'connecteur' => $connector->id, 'outil' => 'notion-create-pages', 'arguments' => ['title' => 'Menu de la semaine']]);
        $this->assertSame(ConnectorAction::ALLOWED, $pending->fresh()->status);
        $this->assertSame(['notion-create-pages'], $connector->fresh()->alwaysAllowed());
        $this->assertSame('C’est fait : la page « Menu de la semaine » est dans votre Notion.', $project->messages()->where('role', Message::ROLE_ASSISTANT)->latest('id')->value('content'));
        $this->assertSame(1, ConnectorAction::where('status', ConnectorAction::DONE)->where('writes', true)->count());

        // « Toujours autoriser » se retire dans Réglages → Connecteurs ; puis « Refuser » : rien n'est fait.
        Volt::actingAs($user)->test('settings.connectors')->assertSee('Toujours autorisés')->call('removeAlways', $connector->id, 'notion-create-pages');
        $this->assertSame([], $connector->fresh()->alwaysAllowed());
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Crée encore la page']);
        app()->call([new RespondToMessage($run3 = $project->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION])), 'handle']);
        $second = ConnectorAction::where('status', ConnectorAction::PENDING)->sole();
        Volt::actingAs($user)->test('projects.show', ['project' => $project])->call('refuseConnector', $second->id)->assertSee('Action refusée : rien n’a été modifié dans Notion.');
        $this->assertSame(ConnectorAction::REFUSED, $second->fresh()->status);
        $this->assertNull(DB::table('connector_actions')->where('id', $second->id)->value('arguments'));
        $this->assertSame(0, AgentRun::query()->where('connector_action_id', $second->id)->count());
        $this->actingAs($user)->get(route('settings.connectors'))->assertSee('data-journal-action="refuse"', false);

        // Un autre client ne peut jamais autoriser l'action de quelqu'un d'autre.
        $this->assertSame($run3->project_id, $project->id);
    }

    public function test_with_a_connector_the_day_ceiling_of_the_paid_model_stops_instead_of_using_a_free_model(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $this->connected($user);
        [$project, $run] = $this->chat($user);
        app(Settings::class)->set('openai_daily_cap_usd', 0);

        $this->useRealFirstResponder();
        Http::fake();
        RespondToMessage::dispatchSync($run);

        Http::assertNothingSent();
        $this->assertSame([AgentRun::STATUS_STOPPED, AgentRun::STOP_MODEL_CAP], [$run->fresh()->status, $run->fresh()->stop_reason]);

        // Sans connecteur actif, une question garde son secours gratuit, comme avant.
        $project->forceFill(['connectors' => []])->save();
        Http::fake(['runner.test/repondre' => Http::response(['ok' => true, 'decision' => 'repondre', 'reponse' => 'Bonjour !', 'fournisseur' => 'gemini', 'modele' => 'gemini-3.5-flash-lite', 'refus' => []])]);
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Bonjour']);
        RespondToMessage::dispatchSync($project->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION]));
        Http::assertSent(fn (Request $request) => $request['connecteurs'] === [] && collect($request['fournisseurs'])->pluck('fournisseur')->doesntContain('openai'));
    }

    public function test_an_expired_token_is_renewed_by_the_runner_and_never_sent_when_it_cannot_be(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $connector = $this->connected($user, 'notion', ['token_expires_at' => now()->subMinute()]);
        Http::fake(['runner.test/connecteurs/renouveler' => Http::sequence()
            ->push(['ok' => true, 'jeton' => 'jeton-neuf', 'renouvellement' => null, 'expire_dans' => 3600])
            ->push(['ok' => false])]);

        $this->assertSame('jeton-neuf', app(Connectors::class)->forRunner($user, null)[0]['jeton']);
        $this->assertSame('jeton-renouvellement', $connector->fresh()->refresh_token, 'le jeton de renouvellement est gardé');

        $connector->fresh()->forceFill(['token_expires_at' => now()->subMinute()])->save();
        $this->assertSame([], app(Connectors::class)->forRunner($user->fresh(), null));
    }
}
