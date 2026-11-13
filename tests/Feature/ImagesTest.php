<?php

namespace Tests\Feature;

use App\Agent\AnswerModels;
use App\Agent\RunnerClient;
use App\Agent\RunnerSignature;
use App\Agent\Tools\CreateDocument;
use App\Agent\Tools\CreateImage;
use App\Jobs\GenerateDocument;
use App\Models\AgentRun;
use App\Models\Project;
use App\Models\ProjectPhoto;
use App\Models\ProviderUsage;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Images et affiches, comme Claude et ChatGPT : l'outil « creer_image », les formats selon l'usage, un PNG (et un PDF
 * pour imprimer), des versions, le logo joint, le coût réel du générateur, le plafond du jour, les fournisseurs cachés.
 * Le modèle et le Runner sont simulés ; le texte exact posé par le code est vérifié dans runner/tests/images.test.js.
 */
class ImagesTest extends TestCase
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

    private function ask(User $user, string $text, array $photos = []): Project
    {
        Volt::actingAs($user)->test('site-prompt')->set('description', $text)->set('photos', $photos)->call('build');

        return $user->projects()->latest('id')->firstOrFail();
    }

    private function image(User $user): Project
    {
        $project = $user->projects()->make(['name' => 'Affiche promotion pagnes']);
        $project->forceFill(['kind' => Project::KIND_DOCUMENT, 'document_type' => 'image'])->save();

        return $project;
    }

    public function test_a_poster_request_uses_creer_image_at_once_with_the_format_of_its_use(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->callsToolWith('creer_image', ['format' => 'vertical', 'nom' => 'Affiche promotion pagnes']);
        $project = $this->ask($user, 'une affiche pour mon statut WhatsApp : promotion sur les pagnes à 9 600 FCFA');

        $project->refresh();
        $this->assertTrue($project->isImage());
        $this->assertSame('image', $project->typeKey());
        $this->assertSame('photo', $project->typeIcon());
        $run = $project->agentRuns()->where('purpose', AgentRun::PURPOSE_DOCUMENT)->sole();
        $this->assertSame('vertical', $run->document_options['format']);
        $this->assertSame('image', $run->document_options['type']);
        Bus::assertDispatched(GenerateDocument::class);
        $this->assertFalse(CreateImage::isLong(['taille' => 'long']), 'jamais de plan pour une image');
    }

    public function test_an_unknown_format_lets_kekeli_choose(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->callsToolWith('creer_image', ['format' => 'gigantesque', 'nom' => 'Bannière']);
        $project = $this->ask($user, 'une bannière pour ma page Facebook');

        $this->assertSame('auto', $project->fresh()->agentRuns()->where('purpose', AgentRun::PURPOSE_DOCUMENT)->sole()->document_options['format']);
    }

    public function test_the_runner_gets_the_generator_of_the_formula_the_joined_logo_and_the_previous_image(): void
    {
        $user = User::factory()->create(['country' => 'TG', 'plan' => 'pro', 'plan_expires_at' => now()->addDays(10)]);
        $project = $this->image($user);
        $project->documentVersions()->create(['number' => 1, 'title' => 'Affiche', 'files' => ['png' => 'image.png', 'fond' => 'fond.png'], 'previews' => [], 'structure' => ['type' => 'image', 'format' => 'a4', 'methode' => 'modele']]);
        $message = $project->messages()->create(['role' => 'user', 'content' => 'ajoute mon logo et mets le prix en plus gros']);
        ProjectPhoto::store($project, UploadedFile::fake()->image('logo.png', 300, 300), $message);
        $run = CreateDocument::start($project, $message, ['type' => 'image', 'action' => 'modifier', 'formats' => [], 'fichier' => null, 'recherche' => '']);

        Http::fake(['runner.test/*' => Http::response(['accepte' => true], 202)]);
        app(RunnerClient::class)->document($run);
        Http::assertSent(function (Request $r) use ($project) {
            return str_ends_with($r->url(), '/documents')
                && $r['images']['generateur'] === 'openai/gpt-image-2.6-flare'
                && $r['images']['verification'] === ['fournisseur' => 'openai', 'modele' => 'gpt-6.1-terra']
                && $r['images']['secours'] === 'cloudflare/flux-1-schnell'
                && count($r['document']['joints']) === 1
                && str_contains($r['document']['joints'][0]['url'], '/runner/projets/'.$project->id.'/photos/')
                && str_contains($r['document']['fond_precedent'], '/versions/')
                && $r['document']['precedente']['methode'] === 'modele'
                && ! str_contains($r->body(), self::SECRET);
        });
        // Les fichiers du client et l'image précédente ne se lisent qu'avec une signature.
        $sent = collect(Http::recorded())->first()[0];
        $this->get($sent['document']['joints'][0]['url'])->assertStatus(401);
        $this->get($sent['document']['fond_precedent'])->assertStatus(401);
    }

    public function test_the_free_formula_uses_the_free_generator_and_the_day_ceiling_moves_paid_ones_to_it(): void
    {
        $free = User::factory()->create(['country' => 'TG']);
        $this->assertSame(['generateur' => 'cloudflare/flux-1-schnell', 'verification' => null, 'secours' => 'cloudflare/flux-1-schnell'], app(AnswerModels::class)->imageFor($free));

        $paid = User::factory()->create(['country' => 'TG', 'plan' => 'expert', 'plan_expires_at' => now()->addDays(10)]);
        $this->assertSame('openai/gpt-image-2.6-flare', app(AnswerModels::class)->imageFor($paid)['generateur']);
        app(Settings::class)->set('openai_daily_cap_usd', 0);
        $this->assertSame('cloudflare/flux-1-schnell', app(AnswerModels::class)->imageFor($paid)['generateur'], 'une image se finit avec le générateur gratuit');

        // Une image n'est jamais arrêtée par le plafond : elle part au Runner.
        $project = $this->image($paid);
        $run = CreateDocument::start($project, $project->messages()->create(['role' => 'user', 'content' => 'une affiche']), ['type' => 'image', 'action' => 'creer', 'formats' => [], 'fichier' => null, 'recherche' => '']);
        Http::fake(['runner.test/*' => Http::response(['accepte' => true], 202)]);
        (new GenerateDocument($run))->handle(app(RunnerClient::class), app(AnswerModels::class));
        $this->assertNotSame(AgentRun::STATUS_STOPPED, $run->fresh()->status);
        Http::assertSent(fn (Request $r) => $r['images']['generateur'] === 'cloudflare/flux-1-schnell');
    }

    public function test_a_png_version_with_its_pdf_to_print_is_shown_with_one_download_and_the_file_card(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $project = $this->image($user);
        $run = CreateDocument::start($project, $project->messages()->create(['role' => 'user', 'content' => 'une affiche A4 pour mon école']), ['type' => 'image', 'action' => 'creer', 'formats' => [], 'fichier' => null, 'recherche' => '']);
        foreach (['image.png' => 'png', 'image.pdf' => 'pdf', 'fond.jpg' => 'fond', 'apercu-1.jpg' => 'apercu'] as $name => $content) {
            $this->upload($project, $run, $name, $content)->assertOk();
        }
        $this->upload($project, $run, 'image.svg', 'x')->assertStatus(422);
        $this->event($run, ['type' => 'narration', 'texte' => 'Je prépare votre image.'])->assertOk();
        $this->event($run, ['type' => 'document', 'titre' => 'Affiche inscriptions', 'message' => 'Votre affiche est prête.', 'fichiers' => ['png' => 'image.png', 'pdf' => 'image.pdf', 'fond' => 'fond.jpg'], 'apercus' => ['apercu-1.jpg'], 'pages' => 1, 'structure' => ['type' => 'image', 'format' => 'a4', 'methode' => 'code']])->assertOk();
        $this->event($run, ['type' => 'terminee', 'reponse' => 'Votre affiche est prête.'])->assertOk();

        $version = $project->documentVersions()->sole();
        $this->assertSame(['png' => 'PNG', 'pdf' => 'PDF'], $version->formats(), 'le fond n’est pas proposé au téléchargement');
        $html = $this->actingAs($user)->get(route('projects.show', $project).'?vue=apercu')->assertOk()
            ->assertSee('data-document-preview="image"', false)
            ->assertSee('A4 (à imprimer)')
            ->assertSee('data-download="png"', false)
            ->assertSee('data-file-card="1"', false)
            ->assertSee('A créé l’image · PNG, PDF')
            ->assertDontSee('Mettre en ligne')
            ->getContent();
        $this->assertDoesNotMatchRegularExpression('/(openai|gpt-image|flux-[12]|black-forest|workers ai)/i', strip_tags($html), 'fournisseurs cachés');
        $this->actingAs($user)->get(route('projects.versions.download', [$project, 1, 'png']))->assertOk()->assertDownload('affiche-inscriptions.png');
        $this->actingAs($user)->get(route('projects.versions.download', [$project, 1, 'fond']))->assertNotFound();
    }

    public function test_a_change_makes_a_new_version(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $project = $this->image($user);
        foreach ([1, 2] as $n) {
            $run = CreateDocument::start($project, $project->messages()->create(['role' => 'user', 'content' => $n === 1 ? 'une affiche' : 'fond vert']), ['type' => 'image', 'action' => $n === 1 ? 'creer' : 'modifier', 'formats' => [], 'fichier' => null, 'recherche' => '']);
            $this->upload($project, $run, 'image.png', 'png '.$n)->assertOk();
            $this->event($run, ['type' => 'narration', 'texte' => 'Je prépare votre image.'])->assertOk();
            $this->event($run, ['type' => 'document', 'titre' => 'Affiche', 'fichiers' => ['png' => 'image.png'], 'apercus' => [], 'structure' => ['type' => 'image', 'format' => 'carre']])->assertOk();
            $this->event($run, ['type' => 'terminee', 'reponse' => 'C’est fait.'])->assertOk();
        }
        $this->assertSame([1, 2], $project->documentVersions()->pluck('number')->all());
        $this->assertSame('png 1', $this->actingAs($user)->get(route('projects.versions.download', [$project, 1, 'png']))->streamedContent());
        $this->actingAs($user)->get(route('projects.show', $project))->assertSee('A modifié l’image · version 2');
    }

    public function test_the_real_cost_of_a_paid_image_is_counted_in_the_energy_and_the_day_spending(): void
    {
        $user = User::factory()->create(['country' => 'TG', 'plan' => 'pro', 'plan_expires_at' => now()->addDays(10)]);
        $project = $this->image($user);
        $run = CreateDocument::start($project, $project->messages()->create(['role' => 'user', 'content' => 'une affiche']), ['type' => 'image', 'action' => 'creer', 'formats' => [], 'fichier' => null, 'recherche' => '']);

        $this->event($run, ['type' => 'image_payante', 'fournisseur' => 'openai', 'modele' => 'gpt-image-2.6-flare', 'tokens_texte' => 200, 'tokens_image_entree' => 0, 'tokens_sortie' => 1400])->assertOk();
        $this->event($run, ['type' => 'image_payante', 'fournisseur' => 'openai', 'modele' => 'inconnu', 'tokens_sortie' => 99999])->assertOk();
        $this->event($run, ['type' => 'images', 'nombre' => 1])->assertOk();

        $run->refresh();
        $this->assertSame(2, (int) $run->images_created);
        // 200 × 5 + 1 400 × 30 dollars par million de tokens, plus l'image gratuite comptée au prix réglé (0,001 $).
        $this->assertEqualsWithDelta(0.043 + 0.001, (float) $run->image_cost_usd, 0.000001);
        $this->assertEqualsWithDelta(0.043, (float) ProviderUsage::query()->where('provider', 'openai')->sum('cost_usd'), 0.000001);
        $this->assertEqualsWithDelta(0.043, app(AnswerModels::class)->openAiSpentToday(), 0.000001);
    }

    public function test_the_generator_is_chosen_by_formula_in_the_administration(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.settings'))->assertOk()->assertSee('data-plan-image-model', false)->assertSee('Générateur des images et des affiches');
        Volt::actingAs($admin)->test('admin.settings')->set('plans.pro.image_model', 'openai/dall-e-ultra')->call('save')->assertHasErrors('plans.pro.image_model');
        Volt::actingAs($admin)->test('admin.settings')->set('plans.pro.image_model', 'cloudflare/flux-2-klein-4b')->call('save')->assertHasNoErrors();
        $user = User::factory()->create(['plan' => 'pro', 'plan_expires_at' => now()->addDays(10)]);
        $this->assertSame('cloudflare/flux-2-klein-4b', app(AnswerModels::class)->imageFor($user)['generateur']);
    }

    private function upload(Project $project, AgentRun $run, string $name, string $content): TestResponse
    {
        $server = ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json'];
        foreach (RunnerSignature::headers(self::SECRET, $content) as $header => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $header))] = $value;
        }

        return $this->call('POST', '/runner/projets/'.$project->id.'/documents/'.$run->id.'?nom='.urlencode($name), server: $server, content: $content);
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
