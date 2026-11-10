<?php

namespace Tests\Feature;

use App\Agent\ProjectBackup;
use App\Agent\ProjectFiles;
use App\Jobs\PublishProject;
use App\Jobs\SendAgentRun;
use App\Models\AgentRun;
use App\Models\Project;
use App\Models\User;
use App\Templates\TemplateGallery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TemplateGalleryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config([
            'kekeli.runner.secret' => 'secret-de-test-tres-long-pour-les-signatures-0123456789',
            'kekeli.runner.url' => 'http://runner.test',
            'kekeli.providers_order' => 'gemini',
        ]);
    }

    public function test_the_gallery_has_twelve_templates_made_for_africa(): void
    {
        $templates = app(TemplateGallery::class)->all();

        $this->assertCount(12, $templates);
        foreach ($templates as $slug => $template) {
            $html = file_get_contents(app(TemplateGallery::class)->siteDirectory($slug).'/index.html');
            if (! in_array($slug, ['cv-portfolio', 'ong-association', 'cabinet-conseil', 'eglise', 'btp'], true)) {
                $this->assertStringContainsString('FCFA', $html, $slug);
            }
            $this->assertStringContainsString('+228 90 12 34 56', $html, $slug);
            $this->assertStringContainsString('href="css/base.css"', $html, $slug);
            $this->assertStringContainsString('<svg class="icone', $html, $slug);
            // Jamais d'émoji dans un site : seulement des icônes Lucide (le symbole ☰ du menu n'est pas un émoji).
            $this->assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{262F}\x{2631}-\x{26FF}\x{2700}-\x{27BF}]/u', $html, $slug);
            $this->assertNotEmpty($template['questions']['nom']);
            $this->assertContains('telephone', array_column($template['exemples'], 'genre'));
        }
    }

    public function test_everyone_sees_the_gallery_and_the_preview_without_an_account(): void
    {
        $this->get(route('templates.index'))
            ->assertOk()
            ->assertSee('Modèles de sites')
            ->assertSee('Restaurant ou maquis')
            ->assertSee('Salon de coiffure et de tresses')
            ->assertSee('Église');

        $this->get(route('templates.show', 'restaurant'))
            ->assertOk()
            ->assertSee('Utiliser ce modèle')
            ->assertSee(route('templates.preview', ['restaurant', 'index.html']));

        $this->get(route('templates.preview', ['restaurant', 'index.html']))->assertOk()->assertHeader('Content-Type', 'text/html; charset=utf-8');
        $this->get(route('templates.preview', ['restaurant', 'css/base.css']))->assertOk();
        // Aucun fichier en dehors du site du modèle.
        $this->get(route('templates.preview', ['restaurant', '../modele.json']))->assertNotFound();
        $this->get(route('templates.preview', ['inconnu', 'index.html']))->assertNotFound();
        $this->get(route('templates.show', 'inconnu'))->assertNotFound();
    }

    public function test_a_visitor_creates_an_account_then_comes_back_to_the_template(): void
    {
        Volt::test('templates.show', ['slug' => 'restaurant'])
            ->call('useTemplate')
            ->assertRedirect(route('register'));

        Volt::test('auth.register')
            ->set('name', 'Afi')
            ->set('email', 'afi@exemple.tg')
            ->set('password', 'motdepasse-solide-2026')
            ->set('password_confirmation', 'motdepasse-solide-2026')
            ->set('terms', true)
            ->call('register')
            ->assertRedirect(route('templates.show', 'restaurant'));
    }

    public function test_using_a_template_creates_a_project_with_its_files_then_asks_one_question_at_a_time(): void
    {
        Queue::fake();
        $user = User::factory()->create(['country' => 'TG']);

        Volt::actingAs($user)->test('templates.show', ['slug' => 'restaurant'])->call('useTemplate');

        $project = $user->projects()->sole();
        $this->assertSame('restaurant', $project->template);
        $this->assertSame(0, $project->template_step);
        $this->assertNotNull($project->backup_at);
        $files = app(ProjectFiles::class)->read($project);
        $this->assertArrayHasKey('index.html', $files);
        $this->assertArrayHasKey('css/modele.css', $files);
        $this->assertArrayNotHasKey('modele.json', $files);

        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertSee('Question 1 sur 6')
            ->assertSee("Comment s'appelle votre restaurant ?")
            ->assertDontSee('data-message-input', false)
            // Pas de publication tant que le site n'est pas personnalisé.
            ->assertDontSee('data-publish', false);

        $page = Volt::actingAs($user)->test('projects.show', ['project' => $project])
            ->call('answerQuestion')->assertHasErrors('nom')
            ->set('answer', 'Maquis Le Baobab')->call('answerQuestion')->assertHasNoErrors()
            ->assertSet('answer', '')
            ->set('answer', 'Lomé, Agoè')->call('answerQuestion')
            ->set('answer', 'pas un numéro')->call('answerQuestion')->assertHasErrors('whatsapp')
            ->set('answer', '+228 91 23 45 67')->call('answerQuestion')->assertHasNoErrors()
            // On peut revenir en arrière sans rien perdre.
            ->call('previousQuestion')->assertSet('answer', '+228 91 23 45 67')->call('answerQuestion')
            ->set('answer', "Riz sauce tomate – 1 500 FCFA\nAttiéké poisson – 2 000 FCFA")->call('answerQuestion')
            ->set('colour', 'vert')->call('answerQuestion');

        $project->refresh();
        $this->assertSame('Maquis Le Baobab', $project->name);
        $this->assertSame(5, $project->template_step);
        $this->assertSame('vert', $project->template_answers['couleurs']);
        Queue::assertNothingPushed();

        $page->call('finishQuestions');

        $project->refresh();
        $this->assertNull($project->template_step);
        $run = AgentRun::sole();
        $this->assertStringContainsString('Personnalise mon site avec mes informations', $run->message->content);
        $this->assertStringContainsString('+228 91 23 45 67', $run->message->content);
        $this->assertStringContainsString('« Chez Tante Afi »', $run->instructions);
        $this->assertStringContainsString('n\'invente jamais de témoignage', $run->instructions);
        $this->assertStringContainsString('« Vert »', $run->instructions);
        Queue::assertPushed(SendAgentRun::class);
    }

    public function test_the_runner_receives_the_message_and_the_instructions(): void
    {
        Http::fake(['runner.test/*' => Http::response(['accepte' => true], 202)]);
        $user = User::factory()->create(['country' => 'TG']);
        $project = app(TemplateGallery::class)->createProject($user, 'salon-coiffure');
        $project->forceFill(['template_step' => 5, 'template_answers' => ['nom' => 'Salon Grâce', 'ville' => 'Kara', 'whatsapp' => '+228 92 00 11 22', 'offre' => 'Tresses – 8 000 FCFA', 'couleurs' => 'modele']])->save();

        Volt::actingAs($user)->test('projects.show', ['project' => $project])->call('finishQuestions');

        Http::assertSent(fn (Request $request) => $request->url() === 'http://runner.test/taches'
            && str_contains($request['message'], 'Salon Grâce')
            && str_contains($request['message'], 'Remplace absolument tout le contenu d\'exemple')
            && str_contains($request['message'], '« Salon Belle Tresse »')
            && $request['sauvegarde'] !== null);
    }

    public function test_publishing_is_refused_while_example_content_remains(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $project = app(TemplateGallery::class)->createProject($user, 'restaurant');
        $project->forceFill(['template_step' => null])->save();

        $gallery = app(TemplateGallery::class);
        $remaining = collect($gallery->remainingExamples($project->fresh()));
        $this->assertEqualsCanonicalizing(['nom', 'adresse', 'telephone', 'courriel', 'temoignage', 'temoignage', 'presentation'], $remaining->pluck('genre')->all());

        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertSee('Votre site contient encore du contenu d\'exemple du modèle')
            ->assertSee('le nom d\'exemple « Chez Tante Afi »')
            ->assertSee('le numéro d\'exemple +228 90 12 34 56');

        Volt::actingAs($user)->test('projects.show', ['project' => $project->fresh()])
            ->call('publish')
            ->assertHasErrors('publish');
        $this->assertNull($project->fresh()->publish_status);
        Queue::assertNotPushed(PublishProject::class);

        // Kekeli remplace ce qui reste quand le client le demande.
        Volt::actingAs($user)->test('projects.show', ['project' => $project->fresh()])->call('replaceExamples');
        $this->assertStringContainsString('le nom d\'exemple « Chez Tante Afi »', AgentRun::sole()->instructions);
    }

    public function test_a_fully_personalized_site_can_be_published(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $project = app(TemplateGallery::class)->createProject($user, 'restaurant');
        $project->forceFill(['template_step' => null])->save();

        // Le site personnalisé : le numéro écrit autrement est quand même repéré, puis tout est remplacé.
        $this->backup($project, ['index.html' => '<h1>Maquis Le Baobab</h1><a href="https://wa.me/22890123456">WhatsApp</a>']);
        $this->assertSame(['telephone'], array_column(app(TemplateGallery::class)->remainingExamples($project->fresh()), 'genre'));

        $this->backup($project, ['index.html' => '<h1>Maquis Le Baobab</h1><p>Agoè, Lomé – <a href="https://wa.me/22891234567">+228 91 23 45 67</a></p>']);
        $this->assertSame([], app(TemplateGallery::class)->remainingExamples($project->fresh()));

        Volt::actingAs($user)->test('projects.show', ['project' => $project->fresh()])->call('publish')->assertHasNoErrors();
        Queue::assertPushed(PublishProject::class);
    }

    /**
     * @param  array<string, string>  $files
     */
    private function backup(Project $project, array $files): void
    {
        $folder = sys_get_temp_dir().'/kekeli-test-modele-'.bin2hex(random_bytes(4));
        mkdir($folder);
        foreach ($files as $name => $content) {
            file_put_contents($folder.'/'.$name, $content);
        }
        exec('tar -czf '.escapeshellarg($folder.'.tar.gz').' -C '.escapeshellarg($folder).' .');
        $backups = app(ProjectBackup::class);
        $backups->disk()->put($backups->path($project->id), file_get_contents($folder.'.tar.gz'));
        exec('rm -rf '.escapeshellarg($folder).' '.escapeshellarg($folder.'.tar.gz'));
        $project->forceFill(['backup_at' => now()->addSeconds(random_int(1, 1000))])->save();
    }
}
