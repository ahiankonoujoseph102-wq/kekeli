<?php

namespace Tests\Feature;

use App\Agent\FirstResponder;
use App\Agent\RunnerEventHandler;
use App\Jobs\GenerateDocument;
use App\Jobs\RespondToMessage;
use App\Jobs\SendAgentRun;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Chantier « vitesse », partie 5 : une demande floue, répétée ou brouillée ne lance jamais la création. Kekeli pose
 * une seule question courte, avec le bouton « Oui, créer le site » (ou l'image, le document…).
 */
class VagueRequestsTest extends TestCase
{
    use RefreshDatabase;

    private function ask(User $user, string $text): Project
    {
        Volt::actingAs($user)->test('site-prompt')->set('description', $text)->call('build');

        return $user->projects()->latest('id')->firstOrFail();
    }

    public function test_a_repeated_site_request_asks_first_then_the_button_starts_the_site(): void
    {
        Bus::fake([SendAgentRun::class]);
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->asksToConfirm('creer_site', 'J’ai compris : « crée un site pour mon restaurant ». Voulez-vous que je crée le site maintenant ?');
        $chat = $this->ask($user, 'crée un site crée un site pour mon restaurant crée un site pour mon restaurant');

        $chat->refresh();
        $this->assertTrue($chat->isChat(), 'rien n’est créé sans confirmation');
        Bus::assertNotDispatched(SendAgentRun::class);
        $offer = $chat->agentRuns()->sole();
        $this->assertSame('creer_site', $offer->report['offer']);
        $this->actingAs($user)->get(route('projects.show', $chat))->assertSee('data-accept-offer="creer_site"', false)->assertSee('Oui, créer le site');

        Volt::actingAs($user)->test('projects.show', ['project' => $chat])->call('acceptOffer', $offer->id)->assertHasNoErrors();
        $this->assertFalse($chat->fresh()->isChat());
        Bus::assertDispatched(SendAgentRun::class);
        $this->assertCount(1, $this->model->received, 'le modèle n’est pas redemandé');
    }

    public function test_a_vague_poster_request_asks_first_then_the_button_creates_the_image_with_its_settings(): void
    {
        Bus::fake([GenerateDocument::class, SendAgentRun::class]);
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->asksToConfirm('creer_image', 'Voulez-vous que je crée l’image maintenant ?', ['format' => 'vertical', 'nom' => 'Affiche']);
        $chat = $this->ask($user, 'fais une affiche');
        $offer = $chat->agentRuns()->sole();
        $this->assertSame(['format' => 'vertical', 'nom' => 'Affiche'], $offer->report['offer_arguments']);
        $this->actingAs($user)->get(route('projects.show', $chat))->assertSee('Oui, créer l’image');

        Volt::actingAs($user)->test('projects.show', ['project' => $chat])->call('acceptOffer', $offer->id);
        $chat->refresh();
        $this->assertTrue($chat->isImage());
        $this->assertSame('vertical', $chat->agentRuns()->where('purpose', AgentRun::PURPOSE_DOCUMENT)->sole()->document_options['format']);
        $this->assertSame(['fais une affiche', 'Oui, créer l’image'], $chat->messages()->where('role', Message::ROLE_USER)->pluck('content')->all());
        Bus::assertDispatched(GenerateDocument::class);
    }

    public function test_only_creations_of_the_discussion_can_be_proposed(): void
    {
        $this->assertNull(RespondToMessage::proposal(['outil' => 'creer_application'], 'discussion'));
        $this->assertNull(RespondToMessage::proposal(['outil' => 'creer_site'], 'site'));
        $this->assertNull(RespondToMessage::proposal('creer_site', 'discussion'));
        $this->assertSame(['tool' => 'creer_video', 'arguments' => ['format' => 'vertical']], RespondToMessage::proposal(['outil' => 'creer_video', 'arguments' => ['format' => 'vertical', 'Mauvais-Nom' => 'x', 'liste' => ['a']]], 'discussion'));
    }

    public function test_the_button_is_only_for_the_last_answer_and_the_owner(): void
    {
        Bus::fake([SendAgentRun::class]);
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->asksToConfirm('creer_site', 'Voulez-vous que je crée le site maintenant ?')->answers('Bien sûr.');
        $chat = $this->ask($user, 'site');
        $offer = $chat->agentRuns()->sole();
        Volt::actingAs($user)->test('projects.show', ['project' => $chat])->set('content', 'en fait non, une question')->call('send');
        (new RespondToMessage($chat->agentRuns()->get()->last()))->handle(app(FirstResponder::class), app(RunnerEventHandler::class));

        Volt::actingAs($user)->test('projects.show', ['project' => $chat])->call('acceptOffer', $offer->id);
        $this->assertTrue($chat->fresh()->isChat(), 'une ancienne question ne lance rien');
        Bus::assertNotDispatched(SendAgentRun::class);
        Volt::actingAs(User::factory()->create())->test('projects.show', ['project' => $chat])->assertStatus(404);
    }
}
