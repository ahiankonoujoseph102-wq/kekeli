<?php

namespace Tests\Feature;

use App\Agent\RunnerClient;
use App\Agent\RunnerEventHandler;
use App\Agent\RunReport;
use App\Jobs\RespondToMessage;
use App\Jobs\SendAgentRun;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use App\WebApps\ChatTitles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Finitions professionnelles de la discussion et de la recherche (12 novembre 2026), comme Claude, ChatGPT, Perplexity,
 * Manus, Lovable et Bolt.new : pastilles de renvoi, « A cherché sur Internet · N sources », limite de recherche
 * jamais mentionnée, vrais titres, « Copier », « Régénérer », « Oui, créer le site », « Arrêter ».
 */
class ProFinishesTest extends TestCase
{
    use RefreshDatabase;

    private const SOURCES = [
        ['numero' => 1, 'titre' => 'Prix du ciment au Togo', 'url' => 'https://www.togofirst.com/ciment', 'date' => 'juin 2025'],
        ['numero' => 2, 'titre' => 'Ministère du commerce', 'url' => 'https://commerce.gouv.tg/prix'],
    ];

    private function ask(User $user, string $question): Project
    {
        Volt::actingAs($user)->test('site-prompt')->set('description', $question)->call('build');

        return $user->projects()->latest('id')->firstOrFail();
    }

    public function test_the_references_become_small_clickable_pills_that_open_the_source_in_a_new_tab(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->answersWithSources('Selon un article de juin 2025, le sac coûte 4 500 FCFA [1]. Le prix officiel est fixé par l’État [1, 2]. Autre renvoi [7].', self::SOURCES);
        $chat = $this->ask($user, 'prix du sac de ciment à Lomé en ce moment');

        $html = $this->actingAs($user)->get(route('projects.show', $chat))->assertOk()->getContent();
        $this->assertStringContainsString('<a href="https://www.togofirst.com/ciment" target="_blank" rel="noopener noreferrer nofollow" class="kekeli-renvoi"', $html);
        $this->assertSame(2, substr_count($html, 'data-citation="1"'));
        $this->assertSame(1, substr_count($html, 'data-citation="2"'));
        // Plus de crochets dans le texte ; un numéro sans source disparaît.
        $answer = (string) str($html)->after('data-question-answer')->before('data-sources');
        $this->assertStringNotContainsString('[1', $answer);
        $this->assertStringNotContainsString('[7]', $answer);
        // La date de la source est montrée quand elle est connue.
        $this->assertStringContainsString('togofirst.com · juin 2025', $html);
    }

    public function test_the_pills_never_carry_a_dangerous_address_nor_raw_html(): void
    {
        $line = (string) RunReport::display('Prix <b>fort</b> [1] et [2].', [
            ['title' => 'Titre "piégé" <script>', 'url' => 'https://exemple.tg/a?x="y"'],
            ['title' => 'Mauvais', 'url' => 'javascript:alert(1)'],
        ]);

        $this->assertStringContainsString('&lt;b&gt;fort&lt;/b&gt;', $line);
        $this->assertStringContainsString('href="https://exemple.tg/a?x=&quot;y&quot;"', $line);
        $this->assertStringNotContainsString('javascript:', $line);
        $this->assertStringNotContainsString('<script>', $line);
        $this->assertSame(1, substr_count($line, 'kekeli-renvoi'));
    }

    public function test_a_search_is_summed_up_as_searched_the_internet_with_the_query_and_the_sites(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->answersWithSources('Le sac coûte 4 500 FCFA [1] [2].', self::SOURCES);
        $chat = $this->ask($user, 'prix du sac de ciment à Lomé en ce moment');
        // Le Runner annonce la recherche pendant qu'elle se fait : une étape « recherche_web ».
        $chat->agentRuns()->sole()->steps()->create(['kind' => 'recherche_web', 'target' => 'recherche']);

        $this->actingAs($user)->get(route('projects.show', $chat))->assertOk()
            ->assertSee('A cherché sur Internet · 2 sources')
            ->assertDontSee('Kekeli a fait 1 action')
            ->assertSee('data-search-query', false)
            ->assertSeeInOrder(['Sites consultés', 'togofirst.com', 'commerce.gouv.tg']);

        // Une réponse sans recherche n'a pas ce résumé.
        $this->model->answers('Un nom de domaine est l’adresse de votre site.');
        $other = $this->ask($user, 'c’est quoi un nom de domaine');
        $this->actingAs($user)->get(route('projects.show', $other))->assertOk()->assertDontSee('A cherché sur Internet');
    }

    public function test_the_searches_left_show_only_in_the_account_menu_never_in_an_answer(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $this->actingAs($user)->get(route('projects.index'))->assertOk()
            ->assertSee('data-account-searches="3"', false)
            ->assertSee('3 restantes aujourd’hui');

        $this->model->answersWithSources('Réponse [1].', self::SOURCES, searches: 3);
        $chat = $this->ask($user, 'actualités du Togo');

        // Limite du jour atteinte : le menu du compte le dit, la discussion n'en parle jamais.
        $this->model->answers('En 2024, le sac de ciment coûtait environ 4 500 FCFA. Vérifiez le prix chez votre vendeur au moment de l’achat.');
        Volt::actingAs($user)->test('projects.show', ['project' => $chat])->set('content', 'et le prix du ciment ?')->call('send')->assertHasNoErrors();

        $page = $this->actingAs($user)->get(route('projects.show', $chat))->assertOk()
            ->assertSee('data-account-searches="0"', false)
            ->assertSee('aucune restante aujourd’hui')
            ->assertSee('En 2024, le sac de ciment coûtait environ 4 500 FCFA.');
        $discussion = str($page->getContent())->after('data-conversation')->before('data-prompt-box')->lower();
        foreach (['limite', 'quota', 'pas pu vérifier', 'pas pu le vérifier'] as $word) {
            $this->assertFalse($discussion->contains($word), "La discussion ne doit pas contenir « {$word} ».");
        }
    }

    public function test_the_model_gives_the_real_title_of_a_discussion_with_its_first_answer(): void
    {
        Http::fake(['runner.test/*' => Http::response(['ok' => false], 503)]);
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->answers('En 2024, environ 4 500 FCFA le sac.', 'Prix du ciment à Lomé');
        $chat = $this->ask($user, 'bonjour je voudrais savoir combien coûte le sac de ciment à Lomé en ce moment');

        $this->assertSame('Prix du ciment à Lomé', $chat->fresh()->name);
        $this->assertFalse(app(ChatTitles::class)->isTemporary($chat->fresh()));

        // Ensuite, le titre ne change plus tout seul.
        $this->model->answers('Oui.', 'Autre titre proposé');
        Volt::actingAs($user)->test('projects.show', ['project' => $chat->fresh()])->set('content', 'et à Kara ?')->call('send');
        $this->assertSame('Prix du ciment à Lomé', $chat->fresh()->name);
    }

    public function test_a_title_chosen_by_the_client_or_an_unusable_title_is_never_replaced(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        // Titre inutilisable (un seul mot) : le titre provisoire reste.
        $this->model->answers('Bonjour !', 'Salutations.');
        $chat = $this->ask($user, 'comment attirer plus de clients dans mon restaurant');
        $temporary = $chat->fresh()->name;
        $this->assertTrue(app(ChatTitles::class)->isTemporary($chat->fresh()));

        // Le client renomme : le modèle ne remplace jamais son choix.
        $chat->update(['name' => 'Mon restaurant']);
        $this->model->answers('Voici des idées.', 'Conseils pour un restaurant');
        Volt::actingAs($user)->test('projects.show', ['project' => $chat->fresh()])->set('content', 'des idées ?')->call('send');
        $this->assertSame('Mon restaurant', $chat->fresh()->name);
        $this->assertNotSame('Mon restaurant', $temporary);

        $titles = app(ChatTitles::class);
        $this->assertSame('Prix du ciment à Lomé', $titles->fromModel(' « prix du ciment à Lomé ». '));
        $this->assertNull($titles->fromModel('Oui'));
        $this->assertNull($titles->fromModel(str_repeat('mot ', 12)));
        $this->assertNull($titles->fromModel(null));
    }

    public function test_the_runner_is_asked_for_a_title_only_while_the_discussion_has_its_temporary_title(): void
    {
        config(['kekeli.runner.secret' => 'secret-de-test-tres-long-pour-les-signatures-0123456789', 'kekeli.runner.url' => 'http://runner.test']);
        $this->useRealFirstResponder();
        $bodies = [];
        Http::fake(function (Request $request) use (&$bodies) {
            $bodies[] = json_decode($request->body(), true);

            return Http::response(['ok' => true, 'decision' => 'repondre', 'reponse' => 'Réponse.', 'titre' => 'Prix du ciment à Lomé', 'fournisseur' => 'gemini', 'modele' => 'gemini-3.5-flash-lite', 'tokens_entree' => 100, 'tokens_sortie' => 10, 'refus' => []]);
        });
        $user = User::factory()->create(['country' => 'TG']);
        $chat = $this->ask($user, 'combien coûte le ciment à Lomé');
        Volt::actingAs($user)->test('projects.show', ['project' => $chat->fresh()])->set('content', 'et à Kara ?')->call('send');

        $this->assertTrue($bodies[0]['contexte']['titre'] ?? false);
        $this->assertArrayNotHasKey('titre', $bodies[1]['contexte']);
        $this->assertSame('Prix du ciment à Lomé', $chat->fresh()->name);
    }

    public function test_copy_and_regenerate_are_under_a_written_answer_only(): void
    {
        Bus::fake([SendAgentRun::class]);
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->answers('Un nom de domaine est l’adresse de votre site.');
        $chat = $this->ask($user, 'c’est quoi un nom de domaine');

        $this->actingAs($user)->get(route('projects.show', $chat))->assertOk()
            ->assertSee('data-copy-answer', false)
            ->assertSee('data-regenerate', false)
            ->assertSee('aria-label="Copier"', false)
            ->assertSee('aria-label="Régénérer"', false);

        // « Régénérer » seulement sous la dernière réponse ; « Copier » sous chacune.
        $this->model->answers('Entre 10 $ et 20 $ par an.');
        Volt::actingAs($user)->test('projects.show', ['project' => $chat->fresh()])->set('content', 'et le prix ?')->call('send');
        $html = $this->actingAs($user)->get(route('projects.show', $chat))->getContent();
        $this->assertSame(2, substr_count($html, 'data-copy-answer'));
        $this->assertSame(1, substr_count($html, 'data-regenerate'));

        // Le travail sur un site n'a ni « Copier » ni « Régénérer ».
        $site = $user->projects()->create(['name' => 'Mon site']);
        $message = $site->messages()->create(['role' => Message::ROLE_USER, 'content' => 'ajoute une page']);
        $run = $site->agentRuns()->create(['message_id' => $message->id]);
        $run->forceFill(['status' => AgentRun::STATUS_COMPLETED, 'report' => ['done' => 'La page est ajoutée.', 'note' => null, 'files' => [], 'files_count' => 0, 'tests' => [], 'problems' => []]])->save();
        $this->actingAs($user)->get(route('projects.show', $site))->assertOk()
            ->assertSee('La page est ajoutée.')
            ->assertDontSee('data-copy-answer', false)
            ->assertDontSee('data-regenerate', false);
    }

    public function test_copy_gives_the_answer_with_the_addresses_of_its_sources(): void
    {
        $text = RunReport::copyText('Le sac coûte 4 500 FCFA [1].', [['title' => 'Prix du ciment', 'url' => 'https://www.togofirst.com/ciment']]);

        $this->assertSame("Le sac coûte 4 500 FCFA [1].\n\nSources :\n1. Prix du ciment — https://www.togofirst.com/ciment", $text);
    }

    public function test_regenerate_replaces_the_last_answer_and_costs_energy_like_a_new_message(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->answers('Première réponse.');
        $chat = $this->ask($user, 'donne moi une idée de slogan pour ma boutique');
        $first = $chat->agentRuns()->sole();

        $this->model->answers('Deuxième réponse.');
        Volt::actingAs($user)->test('projects.show', ['project' => $chat])->call('regenerate', $first->id)->assertHasNoErrors();

        $runs = $chat->agentRuns()->get();
        $this->assertCount(2, $runs);
        $this->assertSame($first->message_id, $runs->last()->message_id);
        $this->assertSame(1, $runs->last()->requests);
        // Les deux réponses ont consommé : l'énergie compte les deux.
        $this->assertSame(2, (int) $chat->agentRuns()->sum('requests'));
        $this->assertSame(['Deuxième réponse.'], $chat->messages()->where('role', Message::ROLE_ASSISTANT)->pluck('content')->all());
        $this->assertSame(['donne moi une idée de slogan pour ma boutique'], $chat->messages()->where('role', Message::ROLE_USER)->pluck('content')->all());
        $this->assertCount(2, $this->model->received);

        $this->actingAs($user)->get(route('projects.show', $chat))->assertOk()
            ->assertSee('Deuxième réponse.')
            ->assertDontSee('Première réponse.');

        // Une ancienne réponse ne se régénère pas ; un autre client non plus.
        Volt::actingAs($user)->test('projects.show', ['project' => $chat])->call('regenerate', $first->id);
        $this->assertCount(2, $chat->agentRuns()->get());
        Volt::actingAs(User::factory()->create())->test('projects.show', ['project' => $chat])->assertStatus(404);
    }

    public function test_when_kekeli_offers_to_create_the_site_a_button_says_yes_create_the_site(): void
    {
        Bus::fake([SendAgentRun::class]);
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->answers('Un restaurant gagne à avoir un site. Voulez-vous que je crée le site de votre restaurant ?');
        $chat = $this->ask($user, "j'ai un restaurant à Lomé qui s'appelle Le Palmier");
        $offer = $chat->agentRuns()->sole();
        $this->assertSame('creer_site', $offer->report['offer']);

        $this->actingAs($user)->get(route('projects.show', $chat))->assertOk()
            ->assertSee('data-accept-site', false)
            ->assertSee('Oui, créer le site');

        Volt::actingAs($user)->test('projects.show', ['project' => $chat])->call('acceptSiteOffer', $offer->id)->assertHasNoErrors();

        // Le site est créé sans redemander au modèle, avec tout l'historique.
        $chat->refresh();
        $this->assertFalse($chat->isChat());
        $this->assertCount(1, $this->model->received);
        $this->assertSame(["j'ai un restaurant à Lomé qui s'appelle Le Palmier", 'Oui, créer le site'], $chat->messages()->where('role', Message::ROLE_USER)->pluck('content')->all());
        $work = $chat->agentRuns()->get()->last();
        $this->assertFalse($work->isQuestion());
        Bus::assertDispatched(SendAgentRun::class, fn (SendAgentRun $job) => $job->run->is($work));
        // Le bouton ne reste pas sous l'ancienne question.
        $this->actingAs($user)->get(route('projects.show', $chat))->assertOk()->assertDontSee('data-accept-site', false);
    }

    public function test_the_button_shows_only_for_an_offer_to_create_a_site_in_a_discussion(): void
    {
        $this->assertTrue(RespondToMessage::offersSite('Bonjour. Voulez-vous que je crée le site de votre salon ?'));
        $this->assertTrue(RespondToMessage::offersSite('Voulez-vous que je crée le site, ou seulement une réponse ?'));
        $this->assertFalse(RespondToMessage::offersSite('Voulez-vous que je crée le site ? Voici aussi des conseils.'));
        $this->assertFalse(RespondToMessage::offersSite('Quel est le nom de votre restaurant ?'));
        $this->assertFalse(RespondToMessage::offersSite('Un site se crée en quelques minutes.'));

        // Dans un projet de site, la même phrase ne propose pas de créer un autre site.
        $user = User::factory()->create(['country' => 'TG']);
        $site = $user->projects()->create(['name' => 'Mon site']);
        $this->model->answers('Voulez-vous que je crée le site autrement ?');
        Volt::actingAs($user)->test('projects.show', ['project' => $site])->set('content', 'hum')->call('send');
        $this->assertNull($site->agentRuns()->sole()->report['offer']);
        $this->actingAs($user)->get(route('projects.show', $site))->assertDontSee('data-accept-site', false);
    }

    public function test_stop_drops_an_answer_being_written(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $chat = $user->projects()->make(['name' => 'Discussion']);
        $chat->forceFill(['kind' => Project::KIND_CHAT])->save();
        Bus::fake([RespondToMessage::class]);
        Volt::actingAs($user)->test('projects.show', ['project' => $chat])->set('content', 'raconte moi une longue histoire')->call('send');
        $run = $chat->agentRuns()->sole();

        // Pendant que Kekeli répond : le bouton d'envoi devient « Arrêter ».
        $this->actingAs($user)->get(route('projects.show', $chat))->assertOk()
            ->assertSee('data-prompt-stop', false)
            ->assertSee('aria-label="Arrêter"', false)
            ->assertDontSee('data-prompt-send', false);

        // Le client arrête pendant que le modèle réfléchit : la réponse qui arrive ensuite n'est pas affichée.
        $this->model->answers('Il était une fois…');
        $this->model->during = function () use ($user, $chat) {
            Volt::actingAs($user)->test('projects.show', ['project' => $chat])->call('stop');
        };
        (new RespondToMessage($run))->handle($this->model, app(RunnerEventHandler::class));

        $run->refresh();
        $this->assertSame(AgentRun::STATUS_STOPPED, $run->status);
        $this->assertTrue($run->stoppedByClient());
        $this->assertSame(0, $chat->messages()->where('role', Message::ROLE_ASSISTANT)->count());
        // La requête faite reste comptée dans l'énergie.
        $this->assertSame(1, $run->requests);

        $this->actingAs($user)->get(route('projects.show', $chat))->assertOk()
            ->assertSee('Vous avez arrêté Kekeli.')
            ->assertDontSee('Il était une fois')
            ->assertDontSee('data-continue', false)
            ->assertSee('data-prompt-send', false)
            ->assertDontSee('data-prompt-stop', false);
    }

    public function test_stop_ends_a_work_on_a_site_at_the_next_step_and_keeps_what_is_done(): void
    {
        Bus::fake([SendAgentRun::class]);
        $user = User::factory()->create(['country' => 'TG']);
        $site = $user->projects()->create(['name' => 'Mon site']);
        Volt::actingAs($user)->test('projects.show', ['project' => $site])->set('content', 'ajoute une page de contact')->call('send');
        $work = $site->agentRuns()->get()->last();
        $this->assertFalse($work->isQuestion());
        $work->forceFill(['status' => AgentRun::STATUS_RUNNING, 'sent_at' => now(), 'started_at' => now()])->save();

        Volt::actingAs($user)->test('projects.show', ['project' => $site])->call('stop');
        $work->refresh();
        $this->assertSame(AgentRun::STATUS_RUNNING, $work->status);
        $this->assertTrue($work->stoppedByClient());
        $this->actingAs($user)->get(route('projects.show', $site))->assertSee('Arrêt en cours…');

        // Au prochain signe de vie du Runner, le site répond « arrêter » ; le Runner sauvegarde puis confirme.
        $events = app(RunnerEventHandler::class);
        $this->assertSame(['arreter' => true, 'raison' => 'client'], $events->stopRequest($work));
        $events->handle($work, ['type' => 'arretee', 'raison' => 'client']);

        $work->refresh();
        $this->assertSame(AgentRun::STATUS_STOPPED, $work->status);
        $this->actingAs($user)->get(route('projects.show', $site))->assertOk()
            ->assertSee('Vous avez arrêté le travail. Ce qui était déjà fait est gardé.')
            ->assertDontSee('data-continue', false)
            ->assertDontSee('data-run-stopped>', false);

        // Une demande arrêtée ne se reprend pas avec « Continuer ».
        Volt::actingAs($user)->test('projects.show', ['project' => $site])->call('continueRun', $work->id);
        $this->assertSame(AgentRun::STATUS_STOPPED, $work->fresh()->status);
    }

    public function test_stop_before_the_work_is_sent_stops_at_once_and_only_the_owner_can_stop(): void
    {
        Bus::fake([SendAgentRun::class]);
        $user = User::factory()->create(['country' => 'TG']);
        $site = $user->projects()->create(['name' => 'Mon site']);
        Volt::actingAs($user)->test('projects.show', ['project' => $site])->set('content', 'ajoute une page de contact')->call('send');
        $work = $site->agentRuns()->get()->last();

        $admin = User::factory()->create(['is_admin' => true]);
        Volt::actingAs($admin)->test('projects.show', ['project' => $site])->call('stop')->assertStatus(403);
        $this->assertTrue($work->fresh()->isActive());

        Volt::actingAs($user)->test('projects.show', ['project' => $site])->call('stop');
        $this->assertSame(AgentRun::STATUS_STOPPED, $work->fresh()->status);

        // Le travail en file ne part plus vers le Runner.
        Http::fake();
        (new SendAgentRun($work->fresh()))->handle(app(RunnerClient::class));
        Http::assertNothingSent();
    }

    public function test_on_a_phone_opening_search_closes_the_side_menu_and_the_menu_icon_stays(): void
    {
        $user = User::factory()->create(['country' => 'TG']);

        $this->actingAs($user)->get(route('projects.index'))->assertOk()
            ->assertSee('data-nav-search', false)
            ->assertSee('data-close-menu-first', false)
            ->assertSee('data-nav-toggle', false);
    }
}
