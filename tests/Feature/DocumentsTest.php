<?php

namespace Tests\Feature;

use App\Agent\AnswerModels;
use App\Agent\FirstResponder;
use App\Agent\RunnerClient;
use App\Agent\RunnerEventHandler;
use App\Agent\RunnerSignature;
use App\Agent\Tools\CreateDocument;
use App\Agent\Tools\CreatePresentation;
use App\Jobs\GenerateDocument;
use App\Jobs\RespondToMessage;
use App\Models\AgentRun;
use App\Models\Project;
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
 * Documents et présentations, comme Claude et ChatGPT : de vrais fichiers (Word, PowerPoint, PDF), un aperçu des pages,
 * des versions, le téléchargement, et les fichiers joints corrigés dans leur format. Le modèle et le Runner sont simulés.
 */
class DocumentsTest extends TestCase
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

    private function ask(User $user, string $text, array $files = []): Project
    {
        Volt::actingAs($user)->test('site-prompt')->set('description', $text)->set('files', $files)->call('build');

        return $user->projects()->latest('id')->firstOrFail();
    }

    private function document(User $user, string $type = 'document'): Project
    {
        $project = $user->projects()->make(['name' => 'Lettre de motivation']);
        $project->forceFill(['kind' => Project::KIND_DOCUMENT, 'document_type' => $type])->save();

        return $project;
    }

    public function test_a_short_document_is_made_at_once_without_a_plan(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->callsToolWith('creer_document', ['taille' => 'court', 'plan' => '', 'formats' => 'docx,pdf', 'action' => 'creer', 'fichier' => '', 'recherche' => '', 'confirme' => 'non', 'nom' => 'Lettre de motivation']);
        $project = $this->ask($user, 'une lettre de motivation pour un poste de caissière au supermarché de Lomé');

        $project->refresh();
        $this->assertTrue($project->isDocument());
        $this->assertFalse($project->isPresentation());
        $run = $project->agentRuns()->where('purpose', AgentRun::PURPOSE_DOCUMENT)->sole();
        $this->assertSame(['type' => 'document', 'action' => 'creer', 'formats' => ['docx', 'pdf'], 'fichier' => null, 'recherche' => '', 'plan' => ''], $run->document_options);
        Bus::assertDispatched(GenerateDocument::class);
    }

    public function test_a_long_document_first_shows_a_short_plan_with_build_this_plan(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $plan = "## Plan\n1. Introduction\n2. Le marché de Lomé\n3. Recommandations\n\nEnviron 6 pages, avec un tableau.";
        $this->model->callsToolWith('creer_document', ['taille' => 'long', 'plan' => $plan, 'formats' => '', 'action' => 'creer', 'fichier' => '', 'recherche' => '', 'confirme' => 'non', 'nom' => 'Rapport sur le marché']);
        $chat = $this->ask($user, 'un rapport de 6 pages sur le commerce des pagnes à Lomé');

        $this->assertTrue($chat->fresh()->isChat(), 'rien n’est fabriqué avant l’accord du client');
        Bus::assertNotDispatched(GenerateDocument::class);
        $this->actingAs($user)->get(route('projects.show', $chat))->assertOk()->assertSee('data-build-plan', false)->assertSee('Construire ce plan');

        Volt::actingAs($user)->test('projects.show', ['project' => $chat])->call('acceptPlan', app(CreateDocument::class)->lastOffer($chat)->id);
        $run = $chat->fresh()->agentRuns()->where('purpose', AgentRun::PURPOSE_DOCUMENT)->sole();
        $this->assertStringContainsString('Le marché de Lomé', $run->document_options['plan']);
        $this->assertTrue($chat->fresh()->isDocument());
    }

    public function test_a_presentation_of_more_than_10_slides_needs_a_plan_and_8_slides_do_not(): void
    {
        $this->assertTrue(CreatePresentation::isLong(['taille' => '15']));
        $this->assertFalse(CreatePresentation::isLong(['taille' => '8']));
        $this->assertFalse(CreateDocument::isLong(['taille' => 'court']));

        $user = User::factory()->create(['country' => 'TG']);
        $this->model->callsToolWith('creer_presentation', ['taille' => '8', 'plan' => '', 'formats' => '', 'action' => 'creer', 'fichier' => '', 'recherche' => '', 'confirme' => 'non', 'nom' => 'Présentation de l’école']);
        $project = $this->ask($user, 'une présentation de 8 diapositives pour mon école de formation');

        $this->assertTrue($project->fresh()->isPresentation());
        Bus::assertDispatched(GenerateDocument::class);
    }

    public function test_the_runner_gets_the_build_model_the_previous_version_and_a_signed_address_never_a_key(): void
    {
        $user = User::factory()->create(['country' => 'TG', 'plan' => 'pro', 'plan_expires_at' => now()->addDays(10)]);
        $project = $this->document($user);
        $project->documentVersions()->create(['number' => 1, 'title' => 'Lettre', 'files' => ['docx' => 'document.docx'], 'previews' => [], 'structure' => ['type' => 'document', 'titre' => 'Lettre', 'blocs' => []]]);
        $message = $project->messages()->create(['role' => 'user', 'content' => 'mets le titre en bleu']);
        $run = CreateDocument::start($project, $message, ['type' => 'document', 'action' => 'modifier', 'formats' => [], 'fichier' => null, 'recherche' => '']);

        Http::fake(['runner.test/*' => Http::response(['accepte' => true], 202)]);
        app(RunnerClient::class)->document($run);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/documents')
            && $r['document']['action'] === 'modifier'
            && $r['document']['precedente']['titre'] === 'Lettre'
            && $r['fournisseurs'][0]['modele'] === 'gpt-6.1-sol'
            && str_contains($r['envoi'], '/runner/projets/'.$project->id.'/documents/'.$run->id)
            && ! str_contains($r->body(), self::SECRET));
    }

    public function test_the_files_arrive_signed_then_a_version_is_shown_with_its_pages_and_downloads(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $project = $this->document($user);
        $run = CreateDocument::start($project, $project->messages()->create(['role' => 'user', 'content' => 'une lettre']), ['type' => 'document', 'action' => 'creer', 'formats' => [], 'fichier' => null, 'recherche' => '']);

        foreach (['document.docx' => 'word', 'document.pdf' => 'pdf', 'apercu-1.jpg' => 'image'] as $name => $content) {
            $this->upload($project, $run, $name, $content)->assertOk();
        }
        // Jamais un chemin ni un autre nom que ceux du moteur de rendu, jamais sans signature.
        $this->upload($project, $run, '../.env', 'x')->assertStatus(422);
        $this->post('/runner/projets/'.$project->id.'/documents/'.$run->id.'?nom=document.pdf', [], ['CONTENT_TYPE' => 'application/octet-stream'])->assertStatus(401);

        $this->event($run, ['type' => 'document', 'titre' => 'Lettre de motivation', 'message' => 'Votre lettre est prête.', 'fichiers' => ['docx' => 'document.docx', 'pdf' => 'document.pdf', 'pptx' => 'absent.pptx'], 'apercus' => ['apercu-1.jpg', 'apercu-2.jpg'], 'pages' => 1, 'structure' => ['type' => 'document', 'titre' => 'Lettre', 'blocs' => []]])->assertOk();
        $this->event($run, ['type' => 'terminee', 'reponse' => 'Votre lettre est prête.'])->assertOk();

        $version = $project->documentVersions()->sole();
        $this->assertSame(['docx' => 'document.docx', 'pdf' => 'document.pdf'], $version->files);
        $this->assertSame(['apercu-1.jpg'], $version->previews, 'seulement les fichiers vraiment reçus');

        $this->actingAs($user)->get(route('projects.show', $project).'?vue=apercu')->assertOk()
            ->assertSee('data-document-preview="document"', false)
            ->assertSee('data-page="1"', false)
            ->assertSee('Télécharger')
            ->assertSee('data-download="docx"', false)
            ->assertSee('data-download="pdf"', false)
            ->assertDontSee('data-panel="code"', false)
            ->assertDontSee('Mettre en ligne');
        $this->actingAs($user)->get(route('projects.versions.download', [$project, 1, 'docx']))->assertOk()->assertDownload('lettre-de-motivation.docx');
        $this->actingAs($user)->get(route('projects.versions.page', [$project, 1, 1]))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->actingAs($user)->get(route('projects.versions.download', [$project, 1, 'pptx']))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('projects.versions.download', [$project, 1, 'pdf']))->assertNotFound();
    }

    public function test_each_change_makes_a_new_version_and_the_older_ones_stay_available(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $project = $this->document($user, 'presentation');
        foreach ([1, 2] as $n) {
            $run = CreateDocument::start($project, $project->messages()->create(['role' => 'user', 'content' => $n === 1 ? 'une présentation' : 'ajoute une diapositive sur les prix']), ['type' => 'presentation', 'action' => $n === 1 ? 'creer' : 'modifier', 'formats' => [], 'fichier' => null, 'recherche' => '']);
            $this->upload($project, $run, 'presentation.pptx', 'pptx '.$n)->assertOk();
            $this->upload($project, $run, 'apercu-1.jpg', 'image')->assertOk();
            $this->event($run, ['type' => 'document', 'titre' => 'Présentation de l’école', 'fichiers' => ['pptx' => 'presentation.pptx'], 'apercus' => ['apercu-1.jpg'], 'pages' => 8])->assertOk();
            $this->event($run, ['type' => 'terminee', 'reponse' => 'C’est fait.'])->assertOk();
        }

        $this->assertSame([1, 2], $project->documentVersions()->pluck('number')->all());
        $this->actingAs($user)->get(route('projects.show', $project).'?vue=apercu')->assertOk()
            ->assertSee('Version 2')
            ->assertSee('data-versions', false)
            ->assertSee('« ajoute une diapositive sur les prix »', false)
            ->assertSee('8 diapositives');
        Volt::actingAs($user)->test('projects.show', ['project' => $project])->call('showVersion', 1)->assertSee('Vous regardez une ancienne version.');
        $this->assertSame('pptx 1', $this->actingAs($user)->get(route('projects.versions.download', [$project, 1, 'pptx']))->streamedContent());
        $this->assertSame('pptx 2', $this->actingAs($user)->get(route('projects.versions.download', [$project, 2, 'pptx']))->streamedContent());
    }

    public function test_a_joined_word_file_is_corrected_in_its_own_format(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $file = UploadedFile::fake()->create('mon-rapport.docx', 120, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $this->model->callsToolWith('creer_document', ['taille' => 'court', 'plan' => '', 'formats' => '', 'action' => 'corriger', 'fichier' => 'mon-rapport.docx', 'recherche' => '', 'confirme' => 'non', 'nom' => 'Rapport corrigé']);
        $project = $this->ask($user, 'corrige les fautes de ce fichier', [$file]);

        $attachment = $project->attachments()->sole();
        $this->assertSame('docx', $attachment->format);
        $this->assertSame('mon-rapport.docx', $attachment->name);
        $this->actingAs($user)->get(route('projects.show', $project))->assertSee('data-message-file', false)->assertSee('mon-rapport.docx');

        $run = $project->fresh()->agentRuns()->where('purpose', AgentRun::PURPOSE_DOCUMENT)->sole();
        $this->assertSame('corriger', $run->document_options['action']);
        Http::fake(['runner.test/*' => Http::response(['accepte' => true], 202)]);
        app(RunnerClient::class)->document($run);
        $sent = collect(Http::recorded())->first()[0];
        $this->assertSame('docx', $sent['document']['source']['format']);
        // Le Runner télécharge le fichier par une adresse signée (jamais sans signature).
        $this->get($sent['document']['source']['url'])->assertStatus(401);
    }

    public function test_the_attachment_field_accepts_only_word_pdf_and_powerpoint(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $project = $user->projects()->make(['name' => 'Discussion']);
        $project->forceFill(['kind' => Project::KIND_CHAT])->save();
        Bus::fake([RespondToMessage::class, GenerateDocument::class]);

        Volt::actingAs($user)->test('projects.show', ['project' => $project])
            ->set('attachments', [UploadedFile::fake()->create('virus.exe', 10)])
            ->set('content', 'regarde')
            ->call('send')
            ->assertHasErrors('attachments.0');
        $this->actingAs($user)->get(route('projects.show', $project))->assertSee('Ajouter un fichier');
    }

    public function test_the_day_ceiling_stops_a_long_document_but_a_short_one_goes_on_with_the_free_providers(): void
    {
        $user = User::factory()->create(['country' => 'TG', 'plan' => 'pro', 'plan_expires_at' => now()->addDays(10)]);
        app(Settings::class)->set('openai_daily_cap_usd', 0);
        $project = $this->document($user);

        $long = CreateDocument::start($project, $project->messages()->create(['role' => 'user', 'content' => 'un rapport']), ['type' => 'document', 'action' => 'creer', 'formats' => [], 'fichier' => null, 'recherche' => '', 'plan' => '## Plan']);
        Http::fake(['runner.test/*' => Http::response(['accepte' => true], 202)]);
        (new GenerateDocument($long))->handle(app(RunnerClient::class), app(AnswerModels::class));
        $this->assertSame(AgentRun::STATUS_STOPPED, $long->fresh()->status);
        $this->assertSame(AgentRun::STOP_MODEL_CAP, $long->fresh()->stop_reason);

        $short = CreateDocument::start($project, $project->messages()->create(['role' => 'user', 'content' => 'une lettre']), ['type' => 'document', 'action' => 'creer', 'formats' => [], 'fichier' => null, 'recherche' => '', 'plan' => '']);
        (new GenerateDocument($short))->handle(app(RunnerClient::class), app(AnswerModels::class));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/documents') && collect($r['fournisseurs'])->doesntContain('fournisseur', 'openai'));
    }

    public function test_in_a_document_the_first_responder_makes_a_new_version_with_modifier_document(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $project = $this->document($user);
        $project->documentVersions()->create(['number' => 1, 'title' => 'Lettre', 'files' => ['docx' => 'document.docx'], 'previews' => [], 'structure' => ['type' => 'document', 'titre' => 'Lettre', 'blocs' => [['type' => 'paragraphe', 'texte' => 'Madame, Monsieur']]]]);
        $this->model->callsToolWith('modifier_document', ['formats' => 'pdf', 'recherche' => '']);
        Bus::fake([GenerateDocument::class]);

        Volt::actingAs($user)->test('projects.show', ['project' => $project])->set('content', 'exporte en PDF seulement')->call('send');
        (new RespondToMessage($project->agentRuns()->where('purpose', AgentRun::PURPOSE_QUESTION)->sole()))->handle(app(FirstResponder::class), app(RunnerEventHandler::class));

        $run = $project->agentRuns()->where('purpose', AgentRun::PURPOSE_DOCUMENT)->sole();
        $this->assertSame('modifier', $run->document_options['action']);
        $this->assertSame(['pdf'], $run->document_options['formats']);
        $this->assertSame('Madame, Monsieur', str_contains(RunnerClient::documentSummary($project->documentVersions()->first()->structure), 'Madame, Monsieur') ? 'Madame, Monsieur' : null);
    }

    public function test_the_client_never_sees_the_providers_of_kekeli_on_a_document(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $project = $this->document($user);
        $run = CreateDocument::start($project, $project->messages()->create(['role' => 'user', 'content' => 'une lettre']), ['type' => 'document', 'action' => 'creer', 'formats' => [], 'fichier' => null, 'recherche' => '']);
        $this->event($run, ['type' => 'fournisseur', 'fournisseur' => 'openai', 'modele' => 'gpt-6.1-sol'])->assertOk();
        $this->event($run, ['type' => 'terminee', 'reponse' => 'Votre lettre est prête.'])->assertOk();

        $html = $this->actingAs($user)->get(route('projects.show', $project))->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/\b(openai|gpt-?\d|gemini)\b/i', strip_tags($html));
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
