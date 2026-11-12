<?php

namespace Tests\Feature;

use App\Agent\ProjectBackup;
use App\Agent\ProjectFiles;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;
use ZipArchive;

class ClientCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'kekeli.runner.secret' => 'secret-de-test-tres-long-pour-les-signatures-0123456789',
            'kekeli.runner.url' => 'http://runner.test',
            'services.github.app_slug' => 'kekeli-essai',
            'services.github.client_id' => 'numero-application',
            'services.github.client_secret' => 'secret-application',
        ]);
    }

    // Fichiers de la sauvegarde et fichier ZIP

    public function test_the_files_are_read_from_the_backup_without_hidden_or_technical_files(): void
    {
        $project = $this->builtProject(User::factory()->create());

        $files = app(ProjectFiles::class)->read($project);

        $this->assertSame(['images/accueil.jpg', 'index.html', 'pages/un-nom-de-dossier-assez-long-pour-depasser-cent-caracteres-dans-une-archive-tar/menu.html'], array_keys($files));
        $this->assertSame('<h1>Le Palmier</h1>', $files['index.html']);
    }

    public function test_every_formula_downloads_the_code_in_a_zip_file_for_free(): void
    {
        $free = User::factory()->create();
        $project = $this->builtProject($free);

        $this->actingAs($free)->get(route('projects.show', $project))->assertSee('data-zip-download', false);
        $response = $this->actingAs($free)->get(route('projects.code.zip', $project));
        $response->assertOk()->assertDownload($project->pagesName().'.zip');

        $zip = new ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());
        $this->assertSame('<h1>Le Palmier</h1>', $zip->getFromName($project->pagesName().'/index.html'));
        $this->assertFalse($zip->getFromName($project->pagesName().'/.env'));
        $zip->close();

        // Un autre utilisateur ne voit jamais le projet.
        $this->actingAs(User::factory()->create())->get(route('projects.code.zip', $project))->assertNotFound();
    }

    public function test_without_the_github_application_nothing_about_github_is_shown(): void
    {
        config(['services.github.client_id' => null]);
        $user = User::factory()->create();
        $project = $this->builtProject($user);

        // Aucune fonction « bientôt disponible » : GitHub reste caché tant qu'il ne fonctionne pas.
        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertDontSee('disponible très bientôt')
            ->assertDontSee('data-github=', false)
            ->assertSee('data-zip-download', false);
        $this->actingAs($user)->get(route('github.connect'))->assertNotFound();
    }

    // Nom de domaine, formules payantes

    public function test_paid_formulas_connect_their_own_domain_name(): void
    {
        $user = User::factory()->create(['plan' => 'pro', 'plan_expires_at' => now()->addDays(10)]);
        $project = $this->builtProject($user);
        $project->forceFill(['published_url' => 'https://'.$project->pagesName().'.pages.dev', 'publish_status' => Project::PUBLISH_DONE, 'published_at' => now()->subDay()])->save();

        Http::fake(['runner.test/domaine' => Http::sequence()
            ->push(['ok' => true, 'domaine' => 'www.le-palmier.tg', 'statut' => 'pending', 'cible' => $project->pagesName().'.pages.dev', 'explication' => 'CNAME record not set'])
            ->push(['ok' => true, 'domaine' => 'www.le-palmier.tg', 'statut' => 'active', 'cible' => $project->pagesName().'.pages.dev', 'explication' => null]),
        ]);

        $component = Volt::actingAs($user)->test('projects.show', ['project' => $project])
            ->set('newDomain', 'le-palmier.tg')
            ->call('addDomain')
            ->assertHasErrors('newDomain')
            ->set('newDomain', 'https://WWW.Le-Palmier.tg/')
            ->call('addDomain')
            ->assertHasNoErrors();

        $project->refresh();
        $this->assertSame('www.le-palmier.tg', $project->custom_domain);
        $this->assertSame('pending', $project->custom_domain_status);
        Http::assertSent(fn (Request $request) => $request['action'] === 'ajouter' && $request['nom'] === $project->pagesName() && $request['domaine'] === 'www.le-palmier.tg');

        // Le client voit exactement quoi ajouter chez le vendeur de son nom de domaine.
        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertSee('CNAME')
            ->assertSee($project->pagesName().'.pages.dev')
            ->assertSee('Vérifier maintenant');

        $component->call('checkDomain');
        $this->assertSame('active', $project->fresh()->custom_domain_status);
        $this->actingAs($user)->get(route('projects.show', $project))->assertSee('Votre site est en ligne à votre adresse.');
    }

    public function test_the_free_formula_cannot_connect_a_domain_name(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $project = $this->builtProject($user);
        $project->forceFill(['published_url' => 'https://x.pages.dev'])->save();

        $this->actingAs($user)->get(route('projects.show', $project))->assertSee('data-domain-upgrade', false)->assertSee('Formules payantes');
        Volt::actingAs($user)->test('projects.show', ['project' => $project])
            ->set('newDomain', 'www.le-palmier.tg')
            ->call('addDomain')
            ->assertForbidden();
        Http::assertNothingSent();
    }

    /**
     * A project with a real backup (archive made by the « tar » command, as in the computers of Kekeli).
     */
    private function builtProject(User $user): Project
    {
        $project = $user->projects()->create(['name' => 'Le Palmier']);

        $folder = sys_get_temp_dir().'/kekeli-test-'.bin2hex(random_bytes(4));
        $long = $folder.'/pages/un-nom-de-dossier-assez-long-pour-depasser-cent-caracteres-dans-une-archive-tar';
        mkdir($long, 0777, true);
        mkdir($folder.'/images');
        mkdir($folder.'/.kekeli');
        mkdir($folder.'/node_modules');
        file_put_contents($folder.'/index.html', '<h1>Le Palmier</h1>');
        file_put_contents($long.'/menu.html', '<h1>Menu</h1>');
        file_put_contents($folder.'/images/accueil.jpg', 'octets');
        file_put_contents($folder.'/.env', 'SECRET=jamais');
        file_put_contents($folder.'/.kekeli/journal.jsonl', '{}');
        file_put_contents($folder.'/node_modules/a.js', '');
        exec('tar -czf '.escapeshellarg($folder.'.tar.gz').' -C '.escapeshellarg($folder).' .');

        $backups = app(ProjectBackup::class);
        $backups->disk()->put($backups->path($project->id), file_get_contents($folder.'.tar.gz'));
        exec('rm -rf '.escapeshellarg($folder).' '.escapeshellarg($folder.'.tar.gz'));
        $project->forceFill(['backup_at' => now()])->save();

        return $project;
    }
}
