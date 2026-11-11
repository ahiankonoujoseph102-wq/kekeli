<?php

namespace Tests\Feature;

use App\Agent\ProjectBackup;
use App\Agent\ProjectFiles;
use App\Jobs\ExportToGithub;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
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

        $this->actingAs($free)->get(route('projects.show', $project))->assertSee('Gratuit, pour toutes les formules.');
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

    // GitHub, pour toutes les formules

    public function test_one_gesture_connects_github_and_sends_the_code_even_with_the_free_formula(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $project = $this->builtProject($user);

        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertSee('data-tab="code"', false)
            ->assertSee('Connecter GitHub');

        // Connexion : GitHub demande l'accord du client, puis revient sur Kekeli avec un code à usage unique.
        $redirect = $this->actingAs($user)->get(route('github.connect', ['projet' => $project->id]));
        $this->assertStringStartsWith('https://github.com/login/oauth/authorize?', $redirect->headers->get('Location'));
        parse_str(parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame('repo', $query['scope']);

        Http::fake([
            'github.com/login/oauth/access_token' => Http::response(['access_token' => 'cle-du-client']),
            'api.github.com/user' => Http::response(['login' => 'ama-mensah']),
        ]);
        $this->actingAs($user)->get(route('github.callback', ['code' => 'code-unique', 'state' => $query['state']]))
            ->assertRedirect(route('projects.show', $project));

        $user->refresh();
        $this->assertSame('ama-mensah', $user->github_login);
        $this->assertSame('cle-du-client', $user->github_token);
        // La clé est chiffrée dans la base de données.
        $this->assertStringNotContainsString('cle-du-client', (string) $user->getRawOriginal('github_token'));

        // Le code part aussitôt, sans autre geste du client.
        $this->assertSame(Project::GITHUB_IN_PROGRESS, $project->fresh()->github_status);
        Queue::assertPushed(ExportToGithub::class, fn (ExportToGithub $job) => $job->project->is($project));
        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertSee('Compte GitHub connecté : ama-mensah')
            ->assertSee('Envoi de votre code sur GitHub…');

        // Plus tard, « Envoyer à nouveau » renvoie la dernière version.
        $project->forceFill(['github_status' => Project::GITHUB_DONE, 'github_repo' => 'ama-mensah/le-palmier'])->save();
        Volt::actingAs($user)->test('projects.show', ['project' => $project])->call('exportToGithub');
        Queue::assertPushed(ExportToGithub::class, 2);

        // Le projet d'un autre utilisateur n'est jamais envoyé.
        $other = $this->builtProject(User::factory()->create());
        $redirect = $this->actingAs($user)->get(route('github.connect', ['projet' => $other->id]));
        parse_str(parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->actingAs($user)->get(route('github.callback', ['code' => 'code-2', 'state' => $query['state']]));
        $this->assertNull($other->fresh()->github_status);
    }

    public function test_a_wrong_state_is_refused_and_never_redirects_to_another_site(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('github.connect', ['retour' => 'https://site-pirate.example/vol']));
        $this->actingAs($user)->get(route('github.callback', ['code' => 'x', 'state' => 'faux']))
            ->assertRedirect(route('projects.index'));
        $this->assertNull($user->fresh()->github_token);
    }

    public function test_the_code_is_put_in_a_private_repository_of_the_client(): void
    {
        $user = User::factory()->create(['github_login' => 'ama-mensah', 'github_token' => 'cle-du-client']);
        $project = $this->builtProject($user);
        $name = $project->pagesName();

        Http::fake([
            "api.github.com/repos/ama-mensah/{$name}-*" => Http::response([], 404),
            'api.github.com/user/repos' => Http::response(['full_name' => "ama-mensah/{$name}"], 201),
            "api.github.com/repos/ama-mensah/{$name}" => Http::response(['default_branch' => 'main']),
            "api.github.com/repos/ama-mensah/{$name}/git/ref/heads/main" => Http::response(['object' => ['sha' => 'commit-initial']]),
            "api.github.com/repos/ama-mensah/{$name}/git/blobs" => Http::response(['sha' => 'blob']),
            "api.github.com/repos/ama-mensah/{$name}/git/trees" => Http::response(['sha' => 'arbre']),
            "api.github.com/repos/ama-mensah/{$name}/git/commits" => Http::response(['sha' => 'nouveau-commit']),
            "api.github.com/repos/ama-mensah/{$name}/git/refs/heads/main" => Http::response([]),
        ]);

        app()->call([new ExportToGithub($project), 'handle']);

        $project->refresh();
        $this->assertSame(Project::GITHUB_DONE, $project->github_status);
        $this->assertSame("ama-mensah/{$name}", $project->github_repo);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.github.com/user/repos'
            && $request['private'] === true && $request['name'] === $name
            && $request->header('Authorization')[0] === 'Bearer cle-du-client');
        // Les fichiers du site et un fichier README.md d'explication, sans les fichiers cachés.
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/git/trees')
            && collect($request['tree'])->pluck('path')->sort()->values()->all() === ['README.md', 'images/accueil.jpg', 'index.html', 'pages/un-nom-de-dossier-assez-long-pour-depasser-cent-caracteres-dans-une-archive-tar/menu.html']);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/git/commits') && $request['parents'] === ['commit-initial']);
        Http::assertSent(fn (Request $request) => $request->method() === 'PATCH' && $request['sha'] === 'nouveau-commit');

        $this->actingAs($user)->get(route('projects.show', $project))->assertSee("github.com/ama-mensah/{$name}");
    }

    public function test_a_withdrawn_github_access_asks_to_connect_again(): void
    {
        $user = User::factory()->create(['github_login' => 'ama', 'github_token' => 'cle-retiree']);
        $project = $this->builtProject($user);
        Http::fake(['api.github.com/*' => Http::response(['message' => 'Bad credentials'], 401)]);

        app()->call([new ExportToGithub($project), 'handle']);

        $this->assertSame(Project::GITHUB_FAILED, $project->fresh()->github_status);
        $this->assertNull($user->fresh()->github_token);
    }

    public function test_without_the_github_application_the_client_is_told_it_comes_soon(): void
    {
        config(['services.github.client_id' => null]);
        $user = User::factory()->create();
        $project = $this->builtProject($user);

        $this->actingAs($user)->get(route('projects.show', $project))->assertSee("L'envoi vers GitHub sera disponible très bientôt.");
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

        $this->actingAs($user)->get(route('projects.show', $project))->assertSee('Disponible avec les formules payantes : votre site à votre propre adresse');
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
