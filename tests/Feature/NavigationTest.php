<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Navigation professionnelle, comme Claude, ChatGPT, Lovable, Bolt.new, Cursor et Manus : la même barre sur toutes
 * les pages connectées, la recherche, « Récents » filtré, le menu « + » de la zone de saisie, les réglages par
 * onglets et l'apparence gardée pour le compte. Jamais rien qui ne fonctionne pas encore.
 */
class NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_appearance_follows_the_system_by_default_and_is_kept_for_the_account(): void
    {
        $user = User::factory()->create();
        $this->assertSame('system', $user->fresh()->appearance);

        // Le choix du compte est donné à Flux dans l'en-tête, avant l'affichage : aucun flash de la mauvaise couleur.
        $html = $this->actingAs($user)->get(route('projects.index'))->assertOk()->getContent();
        $this->assertStringContainsString("localStorage.setItem('flux.appearance', 'system')", $html);
        $this->assertLessThan(strpos($html, 'window.Flux'), strpos($html, "localStorage.setItem('flux.appearance'"));
        $this->assertStringNotContainsString('<html lang="fr" class="dark">', $html);

        Volt::actingAs($user)->test('settings.appearance')
            ->assertSet('appearance', 'system')
            ->assertSeeInOrder(['Système', 'Clair', 'Sombre'])
            ->set('appearance', 'dark')
            ->assertHasNoErrors();
        $this->assertSame('dark', $user->fresh()->appearance);
        $this->assertStringContainsString("localStorage.setItem('flux.appearance', 'dark')", $this->actingAs($user->fresh())->get(route('projects.index'))->getContent());

        Volt::actingAs($user)->test('settings.appearance')->set('appearance', 'rose')->assertHasErrors('appearance');
        $this->assertSame('dark', $user->fresh()->appearance);
    }

    public function test_every_logged_in_page_has_the_same_navigation(): void
    {
        $user = User::factory()->create();
        $project = $user->projects()->create(['name' => 'Restaurant Le Palmier']);

        foreach ([route('projects.index'), route('plans'), route('settings.profile'), route('projects.show', $project)] as $page) {
            $this->actingAs($user)->get($page)->assertOk()
                ->assertSee('data-navigation', false)
                ->assertSeeInOrder(['data-nav-new', 'data-nav-search', 'data-nav-plans', 'data-nav-account'], false)
                ->assertSee('data-nav-toggle', false)
                ->assertDontSee('Mes projets');
        }

        // L'accueil connecté mène à « Nouveau » (plus de bouton « Mes projets » à la place du menu ☰).
        $this->actingAs($user)->get('/')->assertRedirect(route('projects.index'));
    }

    public function test_search_looks_in_titles_and_messages_of_the_clients_projects_only(): void
    {
        $user = User::factory()->create();
        $restaurant = $user->projects()->create(['name' => 'Restaurant Le Palmier']);
        $chat = $user->projects()->make(['name' => 'Combien coûte un nom de domaine']);
        $chat->forceFill(['kind' => Project::KIND_CHAT])->save();
        $restaurant->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Ajoute le MENU du jour avec le poulet braisé.']);
        $other = User::factory()->create()->projects()->create(['name' => 'Menu secret d’un autre client']);
        $other->messages()->create(['role' => Message::ROLE_USER, 'content' => 'menu']);

        $search = Volt::actingAs($user)->test('navigation.search');
        $search->set('query', 'm')->assertDontSee('data-search-results', false);

        // Dans le contenu d'un message, sans distinguer majuscules et minuscules, avec un extrait.
        $search->set('query', 'menu')
            ->assertSee('Restaurant Le Palmier')
            ->assertSee('Ajoute le MENU du jour')
            ->assertDontSee('Menu secret')
            ->assertDontSee('Combien coûte');

        // Dans le titre.
        $search->set('query', 'DOMAINE')->assertSee('Combien coûte un nom de domaine')->assertDontSee('Restaurant Le Palmier');

        // Rien trouvé, et les caractères spéciaux sont cherchés tels quels.
        $search->set('query', '100%')->assertSee('Aucun résultat pour « 100% ».');
    }

    public function test_recent_lists_sites_and_discussions_with_simple_filters(): void
    {
        $user = User::factory()->create();
        $site = $user->projects()->create(['name' => 'Restaurant Le Palmier']);
        $chat = $user->projects()->make(['name' => 'Question sur Google']);
        $chat->forceFill(['kind' => Project::KIND_CHAT])->save();
        $this->travel(1)->minute();
        $site->touch();

        $recents = Volt::actingAs($user)->test('navigation.recents', ['current' => $site->id])
            ->assertSeeInOrder(['Tous', 'Sites', 'Discussions'])
            ->assertSeeInOrder(['Restaurant Le Palmier', 'Question sur Google'])
            ->assertSeeHtml('aria-current="page"');

        $recents->call('setFilter', 'chat')->assertSee('Question sur Google')->assertDontSee('Restaurant Le Palmier');
        $recents->call('setFilter', 'site')->assertSee('Restaurant Le Palmier')->assertDontSee('Question sur Google');
        $recents->call('setFilter', 'tout-et-nimporte-quoi')->assertSet('filter', 'all');

        // Sans aucun site ni discussion : pas de section vide.
        Volt::actingAs(User::factory()->create())->test('navigation.recents')->assertDontSee('Récents')->assertDontSee('data-recents-filter', false);
    }

    public function test_the_plus_button_gathers_what_already_works_and_nothing_else(): void
    {
        $user = User::factory()->create();

        // Accueil connecté : « + » avec « Ajouter une image » et GitHub ; plus d'autre bouton que l'envoi.
        $html = $this->actingAs($user)->get(route('projects.index'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'data-prompt-plus'));
        $this->assertStringContainsString('data-prompt-add-image', $html);
        $this->assertStringContainsString('data-github-chip', $html);
        $this->assertStringContainsString(route('projects.open-repository'), $html);
        $this->assertMatchesRegularExpression('/<span class="hidden" data-prompt-attach>/', $html);
        foreach (['Document', 'Présentation', 'Connecteur', 'Bientôt'] as $absent) {
            $this->assertStringNotContainsString($absent, strip_tags($html));
        }

        // Page d'un projet : « + » avec seulement « Ajouter une image ».
        $project = $user->projects()->create(['name' => 'Le Palmier']);
        $page = $this->actingAs($user)->get(route('projects.show', $project))->getContent();
        $this->assertSame(1, substr_count($page, 'data-prompt-plus'));
        $this->assertStringContainsString('data-prompt-add-image', $page);
        $this->assertStringNotContainsString('data-github-chip', $page);

        // Un visiteur : « + » avec GitHub seulement (les photos viennent après la connexion).
        $this->app['auth']->forgetGuards();
        $home = $this->get('/')->getContent();
        $this->assertStringContainsString('data-prompt-plus', $home);
        $this->assertStringNotContainsString('data-prompt-add-image', $home);
    }

    public function test_settings_are_in_tabs_and_connectors_stay_hidden_until_one_works(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('settings.profile'))->assertOk()
            ->assertSeeInOrder(['Profil', 'Mot de passe', 'Apparence', 'Formule'])
            ->assertDontSee('data-settings-tab="settings.connectors"', false)
            ->assertDontSee('Connecteurs');
        $this->actingAs($user)->get(route('settings.connectors'))->assertNotFound();

        $this->actingAs($user)->get(route('settings.plan'))->assertOk()
            ->assertSee('data-settings-plan="Gratuite"', false)
            ->assertSee('Voir les formules')
            ->assertSee(route('plans'));

        // Le jour où un connecteur fonctionne vraiment, l'onglet apparaît tout seul.
        config(['kekeli.connectors' => ['essai' => ['name' => 'Connecteur d’essai', 'enabled' => true], 'eteint' => ['name' => 'Éteint', 'enabled' => false]]]);
        $this->actingAs($user)->get(route('settings.profile'))->assertSee('data-settings-tab="settings.connectors"', false);
        $this->actingAs($user)->get(route('settings.connectors'))->assertOk()->assertSee('Connecteur d’essai')->assertDontSee('Éteint');
    }
}
