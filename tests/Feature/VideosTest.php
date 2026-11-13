<?php

namespace Tests\Feature;

use App\Agent\Energy;
use App\Agent\RunnerClient;
use App\Agent\RunnerSignature;
use App\Agent\Tools\CreateDocument;
use App\Agent\Tools\CreateVideo;
use App\Jobs\GenerateDocument;
use App\Models\AgentRun;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\ProjectAttachment;
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
 * Montage vidéo et motion design, comme Claude Code : l'outil « creer_video », le plan d'une vidéo longue, les formats,
 * la durée maximale par formule, les vidéos et la voix du client, la vidéo MP4 reçue en morceaux, les versions, le
 * lecteur, les médias avec leur licence, le coût de la voix, l'énergie épuisée, les fournisseurs cachés. Le modèle et le
 * Runner sont simulés ; le montage lui-même est vérifié dans runner/tests/videos.test.js et par l'essai réel.
 */
class VideosTest extends TestCase
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

    private function video(User $user): Project
    {
        $project = $user->projects()->make(['name' => 'Publicité Ama Pagnes']);
        $project->forceFill(['kind' => Project::KIND_DOCUMENT, 'document_type' => 'video'])->save();

        return $project;
    }

    public function test_a_short_video_uses_creer_video_at_once_with_its_format_and_length(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->callsToolWith('creer_video', ['duree' => '25', 'format' => 'vertical', 'nom' => 'Publicité Ama Pagnes']);
        $project = $this->ask($user, 'une publicité TikTok de 25 secondes pour ma boutique de pagnes');

        $project->refresh();
        $this->assertTrue($project->isVideo());
        $this->assertSame('video', $project->typeKey());
        $this->assertSame('film', $project->typeIcon());
        $run = $project->agentRuns()->where('purpose', AgentRun::PURPOSE_DOCUMENT)->sole();
        $this->assertSame(['video', 'vertical', 25], [$run->document_options['type'], $run->document_options['format'], $run->document_options['duree']]);
        Bus::assertDispatched(GenerateDocument::class);
        $this->assertSame(1, Project::query()->ofType('video')->count());
        $this->assertSame(0, Project::query()->ofType('document')->count());
    }

    public function test_a_video_longer_than_30_seconds_first_gets_a_plan_with_build_this_plan(): void
    {
        $user = User::factory()->create(['country' => 'TG', 'plan' => 'pro', 'plan_expires_at' => now()->addDays(10)]);
        $plan = "## Scènes\n1. Accroche (3 s)\n2. Les formations (15 s)\n## Musique\nEntraînante\n## Format\nHorizontal";
        $this->model->callsToolWith('creer_video', ['duree' => '50', 'format' => 'paysage', 'plan' => $plan, 'nom' => 'Promotion Lomé IA School']);
        $project = $this->ask($user, 'une vidéo promotionnelle animée de 50 secondes pour mon école');

        $this->assertTrue($project->fresh()->isChat(), 'rien n’est construit avant « Construire ce plan »');
        $this->assertSame(0, $project->agentRuns()->where('purpose', AgentRun::PURPOSE_DOCUMENT)->count());
        $offer = $project->agentRuns()->get()->last();
        $this->assertSame(CreateVideo::OFFER, $offer->report['offer']);
        $this->actingAs($user)->get(route('projects.show', $project))->assertSee('Construire ce plan');

        Volt::actingAs($user)->test('projects.show', ['project' => $project])->call('acceptPlan', $offer->id);
        $run = $project->fresh()->agentRuns()->where('purpose', AgentRun::PURPOSE_DOCUMENT)->sole();
        $this->assertTrue($project->fresh()->isVideo());
        $this->assertSame(['paysage', 50], [$run->document_options['format'], $run->document_options['duree']]);
        $this->assertStringContainsString('Les formations', $run->document_options['plan']);
        $this->assertFalse(CreateVideo::isLong(['duree' => '30']));
        $this->assertTrue(CreateVideo::isLong(['duree' => '31 secondes']));
    }

    public function test_the_longest_video_depends_on_the_formula_and_can_be_changed(): void
    {
        $free = User::factory()->create(['country' => 'TG']);
        $paid = User::factory()->create(['country' => 'TG', 'plan' => 'expert', 'plan_expires_at' => now()->addDays(10)]);
        $this->assertSame([30, 180], [CreateVideo::maxSeconds($free), CreateVideo::maxSeconds($paid)]);

        $this->model->callsToolWith('creer_video', ['duree' => '120', 'format' => 'auto', 'confirme' => 'oui', 'plan' => '## Scènes', 'nom' => 'Vidéo']);
        $project = $this->ask($free, 'une vidéo de 2 minutes, construis le plan');
        $run = $project->fresh()->agentRuns()->where('purpose', AgentRun::PURPOSE_DOCUMENT)->sole();
        $this->assertSame(30, $run->document_options['duree'], 'ramenée à la durée de la formule Gratuite');
        $this->assertSame('auto', $run->document_options['format']);

        app(Settings::class)->set('video_max_seconds_free', 45);
        $this->assertSame(45, CreateVideo::maxSeconds($free));
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.settings'))->assertSee('data-setting="video_max_seconds_free"', false)->assertSee('data-setting="video_max_seconds_paid"', false);
    }

    public function test_the_runner_gets_the_settings_of_the_formula_and_the_clients_media_in_order(): void
    {
        $user = User::factory()->create(['country' => 'TG', 'plan' => 'pro', 'plan_expires_at' => now()->addDays(10)]);
        $project = $this->video($user);
        $message = $project->messages()->create(['role' => 'user', 'content' => 'monte mes 3 vidéos et mes 2 photos avec une musique']);
        $this->travel(-3)->minutes();
        ProjectAttachment::store($project, UploadedFile::fake()->create('clip-1.mp4', 500, 'video/mp4'), $message);
        $this->travelBack();
        ProjectPhoto::store($project, UploadedFile::fake()->image('boutique.jpg', 400, 300), $message);
        $this->travel(1)->minutes();
        ProjectAttachment::store($project, UploadedFile::fake()->create('ma-voix.m4a', 50, 'audio/mp4'), $message);
        ProjectAttachment::store($project, UploadedFile::fake()->create('tarifs.pdf', 50, 'application/pdf'), $message);
        $run = CreateDocument::start($project, $message, ['type' => 'video', 'action' => 'creer', 'formats' => [], 'fichier' => null, 'recherche' => '', 'format' => 'carre', 'duree' => 20]);

        Http::fake(['runner.test/*' => Http::response(['accepte' => true], 202)]);
        app(RunnerClient::class)->document($run);
        Http::assertSent(function (Request $r) {
            $joints = $r['document']['joints'];

            return str_ends_with($r->url(), '/documents')
                && $r['document']['type'] === 'video'
                && $r['document']['format'] === 'carre'
                && $r['document']['duree_voulue'] === 20
                && $r['video']['duree_max'] === 180
                && $r['video']['voix'] === ['permise' => true, 'modele' => 'gpt-4o-mini-tts', 'voix' => 'marin']
                && $r['video']['transcription']['modele'] === 'whisper-1'
                // Les voix gratuites de Gemini d'abord ; le générateur d'images des affiches de la formule (payant ici).
                && $r['video']['voix_gratuites'][0] === ['modele' => 'gemini-3.8-flash-tts', 'voix' => 'Kore']
                && $r['video']['images']['generateur'] === 'openai/gpt-image-2.6-flare'
                // Le PDF n'est pas un média de la vidéo ; la vidéo envoyée la première vient en premier.
                && array_column($joints, 'type') === ['video', 'image', 'audio']
                && $joints[0]['nom'] === 'clip-1.mp4'
                && ! str_contains($r->body(), self::SECRET);
        });
        $sent = collect(Http::recorded())->first()[0];
        $this->get($sent['document']['joints'][0]['url'])->assertStatus(401);

        // Plafond OpenAI du jour atteint : pas de voix off (textes et musique seulement).
        app(Settings::class)->set('openai_daily_cap_usd', 0);
        Http::fake(['runner.test/*' => Http::response(['accepte' => true], 202)]);
        app(RunnerClient::class)->document($run);
        Http::assertSent(fn (Request $r) => $r['video']['voix']['permise'] === false);
    }

    public function test_videos_and_sounds_can_be_joined_with_plus_but_not_any_file(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $project = $this->video($user);
        Volt::actingAs($user)->test('projects.show', ['project' => $project])
            ->set('attachments', [UploadedFile::fake()->create('clip.mov', 40000, 'video/quicktime'), UploadedFile::fake()->create('voix.mp3', 900, 'audio/mpeg')])
            ->set('content', 'ajoute ces fichiers')
            ->call('send')
            ->assertHasNoErrors();
        $this->assertSame(['mov', 'mp3'], $project->attachments()->orderBy('id')->pluck('format')->all());
        $this->assertSame(['video', 'audio'], $project->attachments()->orderBy('id')->get()->map->mediaType()->all());

        Volt::actingAs($user)->test('projects.show', ['project' => $project])
            ->set('attachments', [UploadedFile::fake()->create('rapport.pdf', 25000, 'application/pdf')])
            ->set('content', 'un document trop gros')
            ->call('send')
            ->assertHasErrors('attachments.0');
        $this->actingAs($user)->get(route('projects.show', $project))->assertSee('Enregistrer ma voix')->assertSee('data-prompt-record-voice', false);
    }

    public function test_the_mp4_arrives_in_parts_and_becomes_a_version_with_its_player_media_and_subtitles(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $project = $this->video($user);
        $message = $project->messages()->create(['role' => 'user', 'content' => 'une publicité pour mes pagnes']);
        $run = CreateDocument::start($project, $message, ['type' => 'video', 'action' => 'creer', 'formats' => [], 'fichier' => null, 'recherche' => '', 'format' => 'vertical', 'duree' => 25]);
        $run->update(['status' => AgentRun::STATUS_RUNNING, 'started_at' => now()]);

        // La vidéo arrive en 3 morceaux, réunis à la fin ; un nom inconnu est refusé.
        $mp4 = str_repeat('a', 1000).str_repeat('b', 1000).str_repeat('c', 500);
        foreach (str_split($mp4, 1000) as $i => $part) {
            $this->upload($project, $run, 'video.mp4', $part, "&partie={$i}&parties=3")->assertOk();
        }
        $this->upload($project, $run, '../.env', 'x')->assertStatus(422);
        foreach (['sous-titres.srt' => "1\n00:00:00,250 --> 00:00:02,100\nLe wax à prix réduit !\n", 'medias.json' => '{}', 'media-2.jpg' => 'jpg', 'apercu-1.jpg' => 'jpg'] as $name => $content) {
            $this->upload($project, $run, $name, $content)->assertOk();
        }
        $folder = DocumentVersion::folder($project, $run->id);
        $this->assertSame($mp4, Storage::disk('local')->get($folder.'/video.mp4'));
        $this->assertSame([], array_values(array_filter(Storage::disk('local')->files($folder), fn ($f) => str_contains($f, '.partie-'))));

        // Le rendu en direct, puis la version.
        $this->event($run, ['type' => 'rendu', 'pourcentage' => 60])->assertOk();
        $this->event($run, ['type' => 'rendu', 'pourcentage' => 80])->assertOk();
        $this->assertSame(['80'], $run->steps()->where('kind', 'rendu')->pluck('target')->all(), 'une seule ligne, mise à jour');
        $this->actingAs($user)->get(route('projects.show', $project))->assertSee('Rendu… 80 %');

        $structure = ['type' => 'video', 'format' => 'vertical', 'duree' => 26.8, 'voix_ia' => true, 'medias' => [
            ['scene' => 2, 'type' => 'image', 'source' => 'Image créée par Kekeli', 'licence' => 'Créé par Kekeli pour le client', 'fichier_version' => 'media-2.jpg'],
            ['scene' => 3, 'type' => 'video', 'source' => 'Pexels', 'licence' => 'Licence Pexels', 'page' => 'https://www.pexels.com/video/123/', 'auteur' => 'Kofi'],
            ['scene' => null, 'type' => 'musique', 'titre' => 'Upbeat loop', 'source' => 'Freesound (trouvée avec Openverse)', 'licence' => 'CC0 1.0 (domaine public)', 'page' => 'https://freesound.org/people/x/sounds/512340'],
        ]];
        $this->event($run, ['type' => 'document', 'titre' => 'Publicité Ama Pagnes', 'message' => 'Voici la vidéo.', 'fichiers' => ['mp4' => 'video.mp4', 'srt' => 'sous-titres.srt', 'medias' => 'medias.json', 'media-2' => 'media-2.jpg'], 'apercus' => ['apercu-1.jpg'], 'pages' => 27, 'structure' => $structure])->assertOk();
        $this->event($run, ['type' => 'terminee', 'reponse' => 'Voici la vidéo.'])->assertOk();

        $version = $project->documentVersions()->sole();
        $this->assertSame(['mp4' => 'MP4', 'srt' => 'Sous-titres'], $version->formats());
        $this->assertSame('media-2.jpg', $version->files['media-2']);
        $page = $this->actingAs($user)->get(route('projects.show', $project))->assertOk();
        $page->assertSee('data-video-player', false)
            ->assertSee(route('projects.versions.video', ['project' => $project, 'number' => 1]), false)
            ->assertSee('Vertical (9:16) · 27 secondes')
            ->assertSee('Voix off créée par intelligence artificielle.')
            ->assertSee('Médias utilisés et leur licence')
            ->assertSee('Licence Pexels')
            ->assertSee('CC0 1.0 (domaine public)')
            ->assertSee('data-file-card="1"', false)
            ->assertSee('A créé la vidéo · MP4, Sous-titres');

        // Le lecteur lit la vidéo (par morceaux), et elle se télécharge en MP4 ; les sous-titres en SRT.
        $this->actingAs($user)->get(route('projects.versions.video', ['project' => $project, 'number' => 1]))->assertOk()->assertHeader('Content-Type', 'video/mp4');
        $this->actingAs($user)->get(route('projects.versions.download', ['project' => $project, 'number' => 1, 'format' => 'mp4']))->assertOk()->assertDownload('publicite-ama-pagnes.mp4');
        $this->actingAs($user)->get(route('projects.versions.download', ['project' => $project, 'number' => 1, 'format' => 'srt']))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('projects.versions.video', ['project' => $project, 'number' => 1]))->assertNotFound();
    }

    public function test_a_change_makes_a_new_version_and_gives_back_the_images_created_before(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $project = $this->video($user);
        $first = $project->documentVersions()->create(['number' => 1, 'title' => 'Publicité', 'files' => ['mp4' => 'video.mp4', 'media-2' => 'media-2.jpg'], 'previews' => [], 'structure' => ['type' => 'video', 'scenario' => ['format' => 'vertical'], 'medias' => []]]);
        Storage::disk('local')->put($first->path('media-2.jpg'), 'image');
        $message = $project->messages()->create(['role' => 'user', 'content' => 'change la musique']);
        $run = CreateDocument::start($project, $message, ['type' => 'video', 'action' => 'modifier', 'formats' => [], 'fichier' => null, 'recherche' => '']);

        Http::fake(['runner.test/*' => Http::response(['accepte' => true], 202)]);
        app(RunnerClient::class)->document($run);
        Http::assertSent(fn (Request $r) => $r['document']['action'] === 'modifier'
            && $r['document']['precedente']['scenario']['format'] === 'vertical'
            && str_contains($r['document']['medias_precedents']['media-2.jpg'], '/versions/'.$first->id.'/medias/media-2.jpg'));
        $url = collect(Http::recorded())->first()[0]['document']['medias_precedents']['media-2.jpg'];
        $this->get($url)->assertStatus(401);
    }

    public function test_the_voice_is_counted_in_the_energy_and_in_the_days_openai_spending(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $project = $this->video($user);
        $message = $project->messages()->create(['role' => 'user', 'content' => 'une publicité']);
        $run = CreateDocument::start($project, $message, ['type' => 'video', 'action' => 'creer', 'formats' => [], 'fichier' => null, 'recherche' => '']);
        $run->update(['status' => AgentRun::STATUS_RUNNING, 'started_at' => now()]);
        $before = app(Energy::class)->usedFcfa($user);

        $this->event($run, ['type' => 'voix', 'fournisseur' => 'openai', 'modele' => 'gpt-4o-mini-tts', 'secondes' => 60, 'usage' => 'voix'])->assertOk();
        $this->event($run, ['type' => 'voix', 'fournisseur' => 'openai', 'modele' => 'whisper-1', 'secondes' => 30, 'usage' => 'transcription'])->assertOk();

        $this->assertEqualsWithDelta(0.015 + 0.003, $run->fresh()->voice_cost_usd, 0.0001);
        $this->assertGreaterThan($before, app(Energy::class)->usedFcfa($user));
        $this->assertEqualsWithDelta(0.018, (float) ProviderUsage::query()->where('provider', 'openai')->sum('cost_usd'), 0.0001);
    }

    public function test_a_gemini_voice_is_free_but_counted_in_the_requests_of_the_provider(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $project = $this->video($user);
        $message = $project->messages()->create(['role' => 'user', 'content' => 'une publicité']);
        $run = CreateDocument::start($project, $message, ['type' => 'video', 'action' => 'creer', 'formats' => [], 'fichier' => null, 'recherche' => '']);
        $run->update(['status' => AgentRun::STATUS_RUNNING, 'started_at' => now()]);

        $this->event($run, ['type' => 'voix', 'fournisseur' => 'gemini', 'modele' => 'gemini-3.8-flash-tts', 'secondes' => 30, 'usage' => 'voix'])->assertOk();

        $this->assertSame(0.0, (float) $run->fresh()->voice_cost_usd);
        $this->assertSame(1, (int) ProviderUsage::query()->where('provider', 'gemini')->sum('requests'));
        $this->assertSame(0.0, (float) ProviderUsage::query()->where('provider', 'openai')->sum('cost_usd'));
    }

    public function test_energy_used_up_stops_the_rendering_and_the_runner_closes_the_work(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $project = $this->video($user);
        $message = $project->messages()->create(['role' => 'user', 'content' => 'une publicité']);
        $run = CreateDocument::start($project, $message, ['type' => 'video', 'action' => 'creer', 'formats' => [], 'fichier' => null, 'recherche' => '']);
        $run->update(['status' => AgentRun::STATUS_RUNNING, 'started_at' => now(), 'sent_at' => now()]);
        app(Energy::class)->startWindowIfNeeded($user);
        $run->forceFill(['model_cost_usd' => 0.5])->save();

        $answer = $this->event($run, ['type' => 'rendu', 'pourcentage' => 40])->assertOk()->json();
        $this->assertTrue($answer['arreter']);
        $this->event($run, ['type' => 'arretee'])->assertOk();
        $this->assertSame(AgentRun::STATUS_STOPPED, $run->fresh()->status);
        $this->assertSame(0, $project->documentVersions()->count());
        $this->assertTrue(app(Energy::class)->isExhausted($user), 'épuisée');
        $this->actingAs($user)->get(route('projects.show', $project))->assertSee('Énergie épuisée');
    }

    public function test_the_providers_stay_hidden_from_the_client(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $project = $this->video($user);
        $message = $project->messages()->create(['role' => 'user', 'content' => 'une publicité']);
        $run = CreateDocument::start($project, $message, ['type' => 'video', 'action' => 'creer', 'formats' => [], 'fichier' => null, 'recherche' => '']);
        $run->update(['status' => AgentRun::STATUS_RUNNING, 'started_at' => now()]);
        $this->event($run, ['type' => 'fournisseur', 'fournisseur' => 'openai', 'modele' => 'gpt-6.1-terra'])->assertOk();
        $this->event($run, ['type' => 'voix', 'fournisseur' => 'openai', 'modele' => 'gpt-4o-mini-tts', 'secondes' => 20, 'usage' => 'voix'])->assertOk();
        $this->event($run, ['type' => 'narration', 'texte' => 'J’enregistre la voix off.'])->assertOk();

        $html = $this->actingAs($user)->get(route('projects.show', $project))->assertOk()->getContent();
        // (« openai » reste seulement dans le réglage caché de la clé du client, jamais à l'écran.)
        foreach (['OpenAI', 'gpt-4o', 'GPT-6', 'Revideo', 'E2B', 'Cloudflare', 'FLUX'] as $name) {
            $this->assertStringNotContainsString($name, $html, "« $name » ne doit pas apparaître");
        }
    }

    private function upload(Project $project, AgentRun $run, string $name, string $content, string $extra = ''): TestResponse
    {
        $server = ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json'];
        foreach (RunnerSignature::headers(self::SECRET, $content) as $header => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $header))] = $value;
        }

        return $this->call('POST', '/runner/projets/'.$project->id.'/documents/'.$run->id.'?nom='.urlencode($name).$extra, server: $server, content: $content);
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
