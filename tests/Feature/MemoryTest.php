<?php

namespace Tests\Feature;

use App\Agent\AnswerModels;
use App\Agent\RunnerClient;
use App\Agent\Tools\CreateDocument;
use App\Jobs\GenerateDocument;
use App\Memory\UserMemory;
use App\Models\AgentRun;
use App\Models\Memory;
use App\Models\Message;
use App\Models\Project;
use App\Models\ProjectPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Mémoire de Kekeli, comme Claude et ChatGPT : les informations utiles et durables données par le client sont retenues
 * (chiffrées, propres à chaque client), réutilisées dans chaque type de création, modifiables et effaçables dans
 * Réglages → Mémoire, jamais un secret ni une information sensible sans demande explicite. Le Runner est simulé.
 */
class MemoryTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'secret-de-test-tres-long-pour-les-signatures-0123456789';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Bus::fake([GenerateDocument::class]);
        config(['kekeli.runner.secret' => self::SECRET, 'kekeli.runner.url' => 'http://runner.test']);
    }

    private function ask(User $user, string $text): Project
    {
        Volt::actingAs($user)->test('site-prompt')->set('description', $text)->call('build');

        return $user->projects()->latest('id')->firstOrFail();
    }

    private function remember(User $user, string ...$texts): void
    {
        foreach ($texts as $text) {
            $user->memories()->create(['content' => $text, 'category' => 'identite']);
        }
    }

    public function test_kekeli_remembers_what_the_client_says_encrypted_and_shows_a_discreet_line(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->answers('Enchanté, Ama ! Je retiens votre boutique.')->remembers([
            ['action' => 'ajouter', 'texte' => "Sa boutique s'appelle Ama Pagnes, au grand marché de Lomé.", 'categorie' => 'identite'],
            ['action' => 'ajouter', 'texte' => 'Son numéro Flooz est le 96 11 22 33.', 'categorie' => 'paiement'],
        ]);
        $chat = $this->ask($user, "Ma boutique s'appelle Ama Pagnes, au grand marché de Lomé. Mon numéro Flooz est le 96 11 22 33.");

        $this->assertSame(["Sa boutique s'appelle Ama Pagnes, au grand marché de Lomé.", 'Son numéro Flooz est le 96 11 22 33.'], $user->memories()->pluck('content')->all());
        // Chiffrés dans la base : jamais lisibles en clair.
        $this->assertStringNotContainsString('Ama Pagnes', (string) DB::table('memories')->value('content'));
        $this->assertStringNotContainsString('Ama Pagnes', (string) DB::table('messages')->whereNotNull('memory_updates')->value('memory_updates'));
        // L'extraction est comptée dans l'énergie, comme la réponse.
        $run = $chat->agentRuns()->sole();
        $this->assertSame(2, $run->requests);
        $this->assertSame(1100 + 600, $run->input_tokens);

        $this->actingAs($user)->get(route('projects.show', $chat))->assertOk()
            ->assertSee('Mémoire mise à jour')
            ->assertSee('Retenu : Son numéro Flooz est le 96 11 22 33.')
            ->assertSee('Gérer la mémoire');
    }

    public function test_a_memory_is_changed_or_forgotten_and_only_the_clients_own_memories_can_be(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $other = User::factory()->create(['country' => 'TG']);
        $this->remember($user, 'Son numéro Flooz est le 96 11 22 33.', 'Ses couleurs sont le vert et l’or.');
        $this->remember($other, 'Son garage s’appelle Kossi Motos.');
        [$flooz, $colors] = $user->memories()->pluck('id')->all();
        $foreign = $other->memories()->value('id');

        $this->model->answers('C’est noté, et vos couleurs sont oubliées.')->remembers([
            ['action' => 'modifier', 'id' => $flooz, 'texte' => 'Son numéro Flooz est le 99 88 77 66.', 'categorie' => 'paiement'],
            ['action' => 'oublier', 'id' => $colors],
            ['action' => 'oublier', 'id' => $foreign],
        ]);
        $this->ask($user, 'Mon nouveau numéro Flooz est le 99 88 77 66. Oublie mes couleurs.');

        $this->assertSame(['Son numéro Flooz est le 99 88 77 66.'], $user->memories()->pluck('content')->all());
        $this->assertSame(['Son garage s’appelle Kossi Motos.'], $other->memories()->pluck('content')->all(), 'jamais le souvenir d’un autre client');
        $updates = Message::query()->whereNotNull('memory_updates')->sole()->memory_updates;
        $this->assertSame(['changed', 'forgotten'], array_column($updates, 'action'));
    }

    public function test_passwords_keys_codes_card_and_bank_numbers_are_never_kept_and_sensitive_information_only_when_asked(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $message = $user->projects()->create(['name' => 'Discussion'])->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Mon mot de passe est Ama2026! et je suis diabétique.']);
        $memory = app(UserMemory::class);

        $memory->apply($user, $message, [
            ['action' => 'ajouter', 'texte' => 'Son mot de passe Kekeli est Ama2026!'],
            ['action' => 'ajouter', 'texte' => 'Sa carte bancaire est la 4970 1012 3456 7890.'],
            ['action' => 'ajouter', 'texte' => 'Son numéro de carte : 4111 1111 1111 1111.'],
            ['action' => 'ajouter', 'texte' => 'Son IBAN est TG53 TG01 2345 6789 0123 4567 89.'],
            ['action' => 'ajouter', 'texte' => 'Sa clé est sk-proj-abcdefghijklmnopqrstuvwxyz0123.'],
            ['action' => 'ajouter', 'texte' => 'Son code PIN Mixx est 4521.'],
            ['action' => 'ajouter', 'texte' => 'Elle est diabétique.'],
            ['action' => 'ajouter', 'texte' => 'Sa boutique ferme à 18 h.'],
        ]);
        $this->assertSame(['Sa boutique ferme à 18 h.'], $user->memories()->pluck('content')->all());

        // Une information sensible, seulement quand le client demande explicitement de la retenir (jamais un secret).
        $asked = $message->project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Retiens que je suis chrétienne : pas de publicité le dimanche matin.']);
        $memory->apply($user, $asked, [['action' => 'ajouter', 'texte' => 'Elle est chrétienne : pas de publicité le dimanche matin.']]);
        $this->assertContains('Elle est chrétienne : pas de publicité le dimanche matin.', $user->memories()->pluck('content')->all());
        $this->assertNull(UserMemory::refusal('Son numéro WhatsApp est le +228 90 12 34 56.'));
        $this->assertSame('secret', UserMemory::refusal('Retiens mon mot de passe : Ama2026!', 'retiens mon mot de passe'));
    }

    public function test_the_switch_turns_the_memory_off_nothing_is_kept_or_sent(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $this->remember($user, 'Sa boutique s’appelle Ama Pagnes.');
        Volt::actingAs($user)->test('settings.memory')->assertSet('enabled', true)->set('enabled', false);
        $this->assertFalse($user->fresh()->memory_enabled);

        $this->model->answers('Bonjour !')->remembers([['action' => 'ajouter', 'texte' => 'Son garage est à Kara.']]);
        $chat = $this->ask($user->fresh(), 'Mon garage est à Kara.');
        $this->assertSame(['Sa boutique s’appelle Ama Pagnes.'], $user->memories()->pluck('content')->all());

        $run = $chat->agentRuns()->sole();
        Http::fake(['runner.test/*' => Http::response(['ok' => true, 'decision' => 'repondre', 'reponse' => 'Bonjour'], 200)]);
        app(RunnerClient::class)->respond($run, 'discussion');
        Http::assertSent(fn (Request $r) => $r['memoire'] === ['active' => false, 'souvenirs' => [], 'logo' => false] && ! str_contains($r->body(), 'Ama Pagnes'));
        $this->assertNull(app(UserMemory::class)->block($user->fresh()));
    }

    public function test_the_runner_gets_only_the_clients_own_memories_and_the_cheapest_model_of_the_formula(): void
    {
        $ama = User::factory()->create(['country' => 'TG']);
        $kossi = User::factory()->create(['country' => 'TG', 'plan' => 'pro', 'plan_expires_at' => now()->addDays(10)]);
        $this->remember($ama, 'Sa boutique s’appelle Ama Pagnes.');
        $this->remember($kossi, 'Son garage s’appelle Kossi Motos.');

        foreach ([[$ama, 'Ama Pagnes', 'Kossi Motos'], [$kossi, 'Kossi Motos', 'Ama Pagnes']] as [$user, $own, $foreign]) {
            $project = $user->projects()->make(['name' => 'Discussion']);
            $project->forceFill(['kind' => Project::KIND_CHAT])->save();
            $project->messages()->create(['role' => Message::ROLE_ASSISTANT, 'content' => 'Quel est votre numéro ?']);
            $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'C’est le 90 12 34 56.']);
            $run = $project->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
            Http::fake(['runner.test/*' => Http::response(['ok' => true, 'decision' => 'repondre', 'reponse' => 'Noté'], 200)]);
            app(RunnerClient::class)->respond($run, 'discussion');
            Http::assertSent(fn (Request $r) => $r['memoire']['active'] === true
                && str_contains(json_encode($r['memoire']['souvenirs'], JSON_UNESCAPED_UNICODE), $own)
                && ! str_contains($r->body(), $foreign)
                && $r['memoire']['precedent'] === 'Quel est votre numéro ?');
        }

        // Gratuite : le modèle gratuit (aucune dépense) ; formule payante : GPT-6 Luna, jamais une offre gratuite.
        $this->assertNotContains('openai', array_column(app(AnswerModels::class)->memoryFor($ama), 'fournisseur'));
        $this->assertSame([['openai', 'gpt-6-luna']], array_map(fn ($m) => [$m['fournisseur'], $m['modele']], app(AnswerModels::class)->memoryFor($kossi)));
    }

    public function test_the_memories_are_used_in_every_kind_of_creation_and_the_logo_on_posters(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $this->remember($user, 'Sa boutique s’appelle Ama Pagnes.', 'Son numéro Mixx by Yas est le 70 45 45 45.');
        Storage::disk(ProjectPhoto::disk())->put('memoire/'.$user->id.'/logo.png', 'logo');
        $user->forceFill(['memory_logo_path' => 'memoire/'.$user->id.'/logo.png'])->save();
        Http::fake(['runner.test/*' => Http::response(['accepte' => true], 202)]);

        // Sites, applications, agents et automatisations : après la demande du client.
        foreach (['site' => [], 'application' => ['is_application' => true], 'agent' => ['is_agent' => true], 'automatisation' => ['is_automation' => true]] as $kind => $flags) {
            $project = $user->projects()->make(['name' => ucfirst($kind)]);
            $project->forceFill(['kind' => $kind === 'site' ? Project::KIND_SITE : Project::KIND_WEBAPP, 'app_stack' => $kind === 'site' ? null : 'laravel'] + $flags)->save();
            $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Crée-le pour ma boutique.']);
            app(RunnerClient::class)->send($project->agentRuns()->create(['message_id' => $message->id]));
            Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/taches') && $r['projet'] === $project->id
                && str_starts_with($r['message'], 'Crée-le pour ma boutique.')
                && str_contains($r['message'], 'Souvenirs du client') && str_contains($r['message'], '70 45 45 45'));
        }

        // Documents, présentations, images et vidéos : dans la demande donnée au modèle ; le logo sur les affiches.
        foreach (['document', 'presentation', 'image', 'video'] as $type) {
            $project = $user->projects()->make(['name' => ucfirst($type)]);
            $project->forceFill(['kind' => Project::KIND_DOCUMENT, 'document_type' => $type])->save();
            $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Fais-le pour ma boutique.']);
            $run = CreateDocument::start($project, $message, ['type' => $type, 'action' => 'creer', 'formats' => [], 'fichier' => null, 'recherche' => '']);
            app(RunnerClient::class)->document($run);
            Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/documents') && $r['projet'] === $project->id
                && str_contains($r['document']['demande'], 'Ama Pagnes')
                && ($type !== 'image' || str_contains((string) $r['document']['logo_memoire'], '/runner/projets/'.$project->id.'/logo-memoire')));
        }

        // Le Runner lit le logo avec une requête signée ; jamais un code GitHub (mode développeur) avec les souvenirs.
        $repository = $user->projects()->make(['name' => 'Dépôt']);
        $repository->forceFill(['kind' => Project::KIND_REPOSITORY, 'repository' => 'ama/site'])->save();
        $message = $repository->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Corrige le test.']);
        app(RunnerClient::class)->send($repository->agentRuns()->create(['message_id' => $message->id]));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/taches') && $r['projet'] === $repository->id && $r['message'] === 'Corrige le test.');
    }

    public function test_the_memory_logo_is_read_by_the_runner_with_a_signature_and_shown_only_to_its_owner(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $other = User::factory()->create(['country' => 'TG']);
        $project = $user->projects()->create(['name' => 'Affiche']);
        $photo = $project->photos()->create(['name' => 'logo.png', 'path' => 'photos-clients/'.$project->id.'/logo.png', 'size' => 4]);
        Storage::disk(ProjectPhoto::disk())->put($photo->path, 'LOGO');
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Voici mon logo, garde-le.']);
        $photo->forceFill(['message_id' => $message->id])->save();

        app(UserMemory::class)->apply($user, $message, [], logo: true);
        $this->assertNotNull($user->fresh()->memory_logo_path);
        $this->assertSame(['logo'], array_column($message->fresh()->memory_updates, 'action'));

        $this->get(route('runner.memory.logo', $project))->assertUnauthorized();
        $this->actingAs($user)->get(route('memory.logo'))->assertOk();
        $this->actingAs($other)->get(route('memory.logo'))->assertNotFound();
    }

    public function test_settings_list_change_delete_and_erase_everything_with_confirmation(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $other = User::factory()->create(['country' => 'TG']);
        $this->remember($user, 'Sa boutique s’appelle Ama Pagnes.', 'Ses couleurs sont le vert et l’or.');
        $this->remember($other, 'Son garage s’appelle Kossi Motos.');
        Storage::disk(ProjectPhoto::disk())->put('memoire/'.$user->id.'/logo.png', 'logo');
        $user->forceFill(['memory_logo_path' => 'memoire/'.$user->id.'/logo.png'])->save();
        [$shop, $colors] = $user->memories()->pluck('id')->all();
        $foreign = $other->memories()->value('id');

        $this->actingAs($user)->get(route('settings.memory'))->assertOk()
            ->assertSee('Utiliser la mémoire')
            ->assertSee('Sa boutique s’appelle Ama Pagnes.')
            ->assertSee('Votre logo')
            ->assertSee('Tout effacer')
            ->assertDontSee('Kossi Motos');

        $page = Volt::actingAs($user)->test('settings.memory');
        $page->call('edit', $shop)->set('draft', 'Sa boutique s’appelle Ama Pagnes Deluxe.')->call('save')->assertHasNoErrors();
        $page->call('edit', $colors)->set('draft', 'Son mot de passe est Ama2026!')->call('save')->assertHasErrors('draft');
        $this->assertSame(['Sa boutique s’appelle Ama Pagnes Deluxe.', 'Ses couleurs sont le vert et l’or.'], $user->memories()->pluck('content')->all());
        $page->call('cancel')->call('delete', $colors);
        $this->assertSame(1, $user->memories()->count());

        // Jamais le souvenir d'un autre client.
        Volt::actingAs($user)->test('settings.memory')->call('edit', $foreign)->assertNotFound();
        Volt::actingAs($user)->test('settings.memory')->call('delete', $foreign);
        $this->assertSame(1, $other->memories()->count());

        Volt::actingAs($user)->test('settings.memory')->call('forgetAll');
        $this->assertSame(0, $user->memories()->count());
        $this->assertNull($user->fresh()->memory_logo_path);
        Storage::disk(ProjectPhoto::disk())->assertMissing('memoire/'.$user->id.'/logo.png');
        $this->assertSame(1, $other->memories()->count());
    }

    public function test_the_memories_and_the_logo_are_deleted_with_the_account(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $this->remember($user, 'Sa boutique s’appelle Ama Pagnes.');
        Storage::disk(ProjectPhoto::disk())->put('memoire/'.$user->id.'/logo.png', 'logo');
        $user->forceFill(['memory_logo_path' => 'memoire/'.$user->id.'/logo.png'])->save();

        Volt::actingAs($user)->test('settings.delete-user-form')->set('password', 'password')->call('deleteUser')->assertHasNoErrors();

        $this->assertSame(0, Memory::count());
        Storage::disk(ProjectPhoto::disk())->assertMissing('memoire/'.$user->id.'/logo.png');
    }

    public function test_the_privacy_policy_explains_the_memory_and_how_to_erase_it(): void
    {
        $this->get(route('legal.privacy'))->assertOk()
            ->assertSee('La mémoire de Kekeli', false)
            ->assertSee('Réglages → Mémoire', false);
    }
}
