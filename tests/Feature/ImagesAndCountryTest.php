<?php

namespace Tests\Feature;

use App\Agent\Energy;
use App\Agent\RunnerSignature;
use App\Jobs\SendAgentRun;
use App\Models\AgentRun;
use App\Models\AgentStep;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ImagesAndCountryTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'secret-de-test-tres-long-pour-les-signatures-0123456789';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'kekeli.runner.secret' => self::SECRET,
            'kekeli.runner.url' => 'http://runner.test',
            'kekeli.providers_order' => 'gemini',
        ]);
        Storage::fake('local');
    }

    // Pays du client

    public function test_a_new_account_chooses_its_country(): void
    {
        $this->signUpWithCode('ama@exemple.tg', 'Ama Mensah', 'CI')->assertHasNoErrors();
        $this->assertSame('CI', User::where('email', 'ama@exemple.tg')->value('country'));

        $this->signUpWithCode('kofi@exemple.tg', 'Kofi', 'XX')->assertHasErrors('country');
    }

    public function test_the_country_can_be_changed_in_the_profile(): void
    {
        $user = User::factory()->create();

        Volt::actingAs($user)->test('settings.profile')
            ->assertSet('country', 'TG')
            ->set('country', 'BF')
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $this->assertSame('BF', $user->fresh()->country);
    }

    public function test_an_account_without_country_is_asked_before_the_first_request(): void
    {
        Http::fake(['runner.test/*' => Http::response(['accepte' => true], 202)]);
        $user = User::factory()->create(['country' => null]);
        $project = $user->projects()->create(['name' => 'Restaurant']);

        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertSee('Dans quel pays se trouve votre activité ?');

        $component = Volt::actingAs($user)->test('projects.show', ['project' => $project])
            ->set('content', 'Crée mon site')
            ->call('send')
            ->assertHasErrors('country');
        $this->assertSame(0, AgentRun::count());

        $component->set('country', 'SN')->call('saveCountry')->assertHasNoErrors();
        $this->assertSame('SN', $user->fresh()->country);

        $component->set('content', 'Crée mon site')->call('send')->assertHasNoErrors();
        // Le premier répondant, puis le travail sur le site.
        $this->assertSame(1, AgentRun::whereNull('purpose')->count());
    }

    public function test_each_request_tells_the_runner_the_country_and_the_client_photos(): void
    {
        Http::fake(['runner.test/*' => Http::response(['accepte' => true], 202)]);
        $user = User::factory()->create(['country' => 'TG']);
        $project = $user->projects()->create(['name' => 'Restaurant']);
        $photo = $project->photos()->create(['name' => 'poisson-braise.jpg', 'path' => 'photos-clients/x/a.jpg', 'size' => 1000]);

        Volt::actingAs($user)->test('projects.show', ['project' => $project])
            ->set('content', 'Crée le site du restaurant')
            ->call('send');

        Http::assertSent(fn (Request $request) => $request->url() === 'http://runner.test/taches'
            && $request['pays'] === [
                'code' => 'TG',
                'nom' => 'Togo',
                'region' => "Afrique de l'Ouest",
                'ville' => 'Lomé',
                'anglais' => 'Togo, West Africa',
                'monnaie' => 'franc CFA (FCFA)',
                'telephone' => '+228 90 12 34 56',
            ]
            && $request['photos_client'] === [['nom' => 'poisson-braise.jpg', 'url' => route('runner.photos.show', [$project, $photo])]]
            && $request['modele_vision'] === 'gemini-3.1-flash-lite');
    }

    // Photos du client

    public function test_the_client_joins_photos_in_the_message_field_and_finds_them_in_the_discussion(): void
    {
        Bus::fake([SendAgentRun::class]);
        $user = User::factory()->create();
        $project = $user->projects()->create(['name' => 'Restaurant']);

        // Plus de fenêtre « Mes photos » : le bouton photo est dans la zone de saisie.
        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertDontSee('Mes photos')
            ->assertSee('data-prompt-attach', false);

        $component = Volt::actingAs($user)->test('projects.show', ['project' => $project]);

        // Seuls des fichiers d'image sont acceptés.
        $component->set('uploads', [UploadedFile::fake()->create('menu.pdf', 100, 'application/pdf')])
            ->set('content', 'Voici mon menu.')
            ->call('send')
            ->assertHasErrors('uploads.0');
        $component->call('removeUpload', 0)->assertSet('uploads', []);

        // Des photos sans texte : Kekeli les place dans le site.
        $component->set('content', '')
            ->set('uploads', [
                UploadedFile::fake()->image('Poisson Braisé.JPG', 1200, 900),
                UploadedFile::fake()->image('poisson braisé.jpg', 800, 600),
            ])
            ->call('send')
            ->assertHasNoErrors()
            ->assertSet('uploads', []);

        $message = $project->messages()->sole();
        $this->assertSame('Voici mes photos : placez-les dans mon site.', $message->content);
        $photos = $project->photos()->orderBy('id')->get();
        $this->assertSame(['poisson-braise.jpg', 'poisson-braise-2.jpg'], $photos->pluck('name')->all());
        $this->assertSame([$message->id, $message->id], $photos->pluck('message_id')->all());
        foreach ($photos as $photo) {
            Storage::disk('local')->assertExists($photo->path);
        }

        // Les photos sont dans le message, dans la discussion.
        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertSee('data-message-photos', false)
            ->assertSee(route('projects.photos.show', [$project, $photos[0]]));

        // Le propriétaire voit ses photos ; un autre utilisateur, jamais.
        $this->actingAs($user)->get(route('projects.photos.show', [$project, $photos[1]]))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('projects.photos.show', [$project, $photos[1]]))->assertNotFound();
    }

    public function test_a_project_keeps_a_limited_number_of_photos(): void
    {
        config(['kekeli.client_photos.max_per_project' => 2]);
        $user = User::factory()->create();
        $project = $user->projects()->create(['name' => 'Restaurant']);

        Volt::actingAs($user)->test('projects.show', ['project' => $project])
            ->set('uploads', [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg'), UploadedFile::fake()->image('c.jpg')])
            ->set('content', 'Mes photos.')
            ->call('send')
            ->assertHasErrors('uploads');

        $this->assertSame(0, $project->photos()->count());
        $this->assertSame(0, $project->messages()->count());
    }

    public function test_the_runner_fetches_a_client_photo_only_with_a_valid_signature(): void
    {
        $user = User::factory()->create();
        $project = $user->projects()->create(['name' => 'Restaurant']);
        Storage::disk('local')->put('photos-clients/'.$project->id.'/a.jpg', 'octets-de-la-photo');
        $photo = $project->photos()->create(['name' => 'plat.jpg', 'path' => 'photos-clients/'.$project->id.'/a.jpg', 'size' => 18]);
        $url = route('runner.photos.show', [$project, $photo]);

        $this->get($url)->assertUnauthorized();

        $server = [];
        foreach (RunnerSignature::headers(self::SECRET, '') as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }
        $response = $this->call('GET', $url, server: $server);
        $response->assertOk();
        $this->assertSame('octets-de-la-photo', $response->streamedContent());

        // Une photo d'un autre projet n'est jamais servie avec l'adresse de celui-ci.
        $other = $user->projects()->create(['name' => 'Autre']);
        $this->call('GET', route('runner.photos.show', [$other, $photo]), server: $server)->assertNotFound();
    }

    // Images créées et énergie

    public function test_created_images_are_counted_in_the_energy(): void
    {
        config(['kekeli.settings.image_price_usd' => 0.002, 'kekeli.settings.usd_to_fcfa' => 600]);
        $user = User::factory()->create();
        $project = $user->projects()->create(['name' => 'Restaurant']);
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Crée le site']);
        $run = $project->agentRuns()->create(['message_id' => $message->id, 'status' => AgentRun::STATUS_RUNNING]);

        $this->event($run, ['type' => 'images', 'nombre' => 3])->assertOk();
        $this->event($run, ['type' => 'etape', 'genre' => 'image_creee', 'cible' => 'restaurant terrace'])->assertOk();

        $run->refresh();
        $this->assertSame(3, $run->images_created);
        $this->assertEqualsWithDelta(0.006, $run->image_cost_usd, 0.000001);
        // 3 images à 0,002 $ = 0,006 $, soit 3,6 FCFA d'énergie.
        $this->assertEqualsWithDelta(3.6, app(Energy::class)->usedFcfa($user), 0.001);
        $this->assertSame('Image créée', $run->steps()->sole()->label());
    }

    public function test_every_step_of_the_image_tools_has_a_french_label(): void
    {
        foreach (['photos', 'photos_client', 'image_creee', 'icones', 'relance', 'typographie'] as $kind) {
            $step = new AgentStep(['kind' => $kind]);
            $this->assertNotSame(AgentStep::LABELS[$kind], $step->label(), $kind);
        }
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function event(AgentRun $run, array $event): TestResponse
    {
        $body = json_encode(['tache' => $run->id, 'evenement' => $event], JSON_UNESCAPED_UNICODE);
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

        foreach (RunnerSignature::headers(self::SECRET, $body) as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call('POST', '/runner/evenements', server: $server, content: $body);
    }
}
