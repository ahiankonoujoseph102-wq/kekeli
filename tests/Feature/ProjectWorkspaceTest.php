<?php

namespace Tests\Feature;

use App\Agent\ProjectBackup;
use App\Models\AgentRun;
use App\Models\Project;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Pull request 4 : où chaque chose est placée, comme Claude Code, Lovable, Bolt.new, Manus, Cursor et Codex.
 */
class ProjectWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['kekeli.backups.disk' => 'local']);
    }

    public function test_the_project_page_is_a_full_screen_workspace_with_a_thin_bar_and_three_views(): void
    {
        $user = User::factory()->create();
        $project = $user->projects()->create(['name' => 'Restaurant Le Palmier']);

        $page = $this->actingAs($user)->get(route('projects.show', $project))->assertOk();

        // Barre fine : retour, nom, petit état, « Mettre en ligne », menu « ⋯ ».
        $page->assertSee('data-project-bar', false)
            ->assertSeeInOrder(['data-back', 'Restaurant Le Palmier', 'Brouillon', 'Mettre en ligne', 'data-project-more'], false)
            // Trois vues sur un téléphone ; plus de menu latéral dans l'espace de travail.
            ->assertSee('data-tab="discussion"', false)
            ->assertSee('data-tab="apercu"', false)
            ->assertSee('data-tab="code"', false)
            ->assertDontSee('data-flux-sidebar', false)
            // Plus de pages à part ni de doublons.
            ->assertDontSee('Mon code')
            ->assertDontSee('Comptes rendus')
            ->assertDontSee('Mes photos')
            // La zone de saisie est la même que sur l'accueil, avec l'énergie en petit.
            ->assertSee('data-prompt-box', false)
            ->assertSee('data-prompt-attach', false)
            ->assertSee('data-energy="100"', false)
            ->assertDontSee('role="meter"', false);
    }

    public function test_publishing_explanations_appear_only_in_the_put_online_window(): void
    {
        $user = User::factory()->create();
        $project = $user->projects()->create(['name' => 'Le Palmier']);
        $project->forceFill(['backup_at' => now(), 'published_url' => 'https://le-palmier.pages.dev', 'publish_status' => Project::PUBLISH_DONE, 'published_at' => now()])->save();

        $html = $this->actingAs($user)->get(route('projects.show', $project))->assertOk()->getContent();

        // La fenêtre « Mettre en ligne » contient l'adresse, la publication et le nom de domaine ; rien d'autre ne les montre.
        $sheet = substr($html, strpos($html, 'data-publish-sheet'));
        $this->assertStringContainsString('https://le-palmier.pages.dev', $sheet);
        $this->assertStringContainsString('Mettre à jour', $sheet);
        $this->assertStringContainsString('data-domain=', $sheet);
        $this->assertSame(1, substr_count($html, 'data-publication='));
        $this->assertStringContainsString('data-project-state="online"', $html);
    }

    public function test_the_code_view_shows_the_files_of_the_site_read_only_without_secrets(): void
    {
        $user = User::factory()->create();
        $project = $this->builtProject($user);

        // Les fichiers ne sont lus qu'une fois la vue « Code » ouverte.
        $component = Volt::actingAs($user)->test('projects.show', ['project' => $project])
            ->assertSet('codeLoaded', false)
            ->assertDontSee('wire:click="openFile', false)
            ->call('loadCode')
            ->assertSee('index.html')
            ->assertSee('css/style.css')
            ->assertSee('2 fichiers')
            ->assertDontSee('.env')
            ->assertDontSee('SECRET=jamais');

        $component->call('openFile', 'index.html')
            ->assertSet('openedFile', 'index.html')
            ->assertSee('&lt;h1&gt;Le Palmier&lt;/h1&gt;', false);

        // Un chemin qui n'est pas un fichier du site ne s'ouvre jamais.
        $component->call('openFile', '.env')->assertSet('openedFile', null);
        $component->call('openFile', '../../.env')->assertSet('openedFile', null);

        // La vue peut être demandée dans l'adresse.
        $this->actingAs($user)->get(route('projects.show', $project).'?vue=code')->assertOk()->assertSee('data-code-file', false);
    }

    public function test_the_work_of_kekeli_is_shown_as_checked_steps_and_the_report_is_the_last_message(): void
    {
        $user = User::factory()->create();
        $project = $user->projects()->create(['name' => 'Le Palmier']);
        $message = $project->messages()->create(['role' => 'user', 'content' => 'Un site pour mon restaurant.']);
        $run = $project->agentRuns()->create(['message_id' => $message->id]);
        $run->forceFill(['status' => AgentRun::STATUS_COMPLETED, 'report' => ['done' => 'Le site du restaurant est prêt.', 'files' => [], 'tests' => [], 'problems' => [], 'check' => []]])->save();
        $run->steps()->create(['kind' => 'narration', 'target' => 'Je prépare la page d’accueil.']);
        $run->steps()->create(['kind' => 'fichier_ecrit', 'target' => 'index.html']);

        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('data-step', false)
            ->assertSeeInOrder(['Un site pour mon restaurant.', 'Kekeli a fait 1 action', 'Je prépare la page d’accueil.', 'Fichier créé', 'Le site du restaurant est prêt.'])
            ->assertDontSee('index.html')
            ->assertDontSee('Compte rendu de Kekeli');
    }

    public function test_the_waiting_line_is_calm_and_says_the_place_in_line(): void
    {
        app(Settings::class)->set('max_concurrent_runs', 1);
        $user = User::factory()->create();
        $project = $user->projects()->create(['name' => 'Le Palmier']);
        $other = User::factory()->create()->projects()->create(['name' => 'Autre']);
        $first = $other->messages()->create(['role' => 'user', 'content' => 'Premier']);
        $other->agentRuns()->create(['message_id' => $first->id])->forceFill(['status' => AgentRun::STATUS_RUNNING, 'sent_at' => now()])->save();
        $message = $project->messages()->create(['role' => 'user', 'content' => 'Mon site']);
        $project->agentRuns()->create(['message_id' => $message->id, 'status' => AgentRun::STATUS_QUEUED]);

        $html = $this->actingAs($user)->get(route('projects.show', $project))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<p class="[^"]*text-zinc-500[^"]*" data-run-waiting-turn="1">/', $html);
        $this->assertStringContainsString('Votre demande est la prochaine.', $html);
    }

    public function test_the_site_can_be_renamed_from_the_menu(): void
    {
        $user = User::factory()->create();
        $project = $user->projects()->create(['name' => 'Mon restaurant']);

        Volt::actingAs($user)->test('projects.show', ['project' => $project])
            ->call('startRenaming')
            ->assertSet('newName', 'Mon restaurant')
            ->set('newName', '  Restaurant Le Palmier ')
            ->call('renameProject')
            ->assertHasNoErrors();

        $this->assertSame('Restaurant Le Palmier', $project->fresh()->name);
    }

    public function test_github_is_a_chip_in_the_field_and_the_energy_detail_is_in_the_account_menu(): void
    {
        $this->get('/')->assertOk()->assertSee('data-github-chip', false)->assertDontSee('Ouvrir un dépôt GitHub');

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('projects.index'))
            ->assertOk()
            ->assertSee('data-github-chip', false)
            ->assertSee(route('projects.open-repository'))
            ->assertDontSee('Ouvrir un dépôt GitHub')
            ->assertSee('data-account-energy', false)
            ->assertSee('de nouveau pleine à');
    }

    public function test_errors_have_a_soft_colour_and_a_kind_tone(): void
    {
        Volt::actingAs(User::factory()->create())->test('site-prompt')
            ->call('build')
            ->assertSee('kekeli-erreur')
            ->assertDontSee('text-red-600');

        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('[data-flux-error]', $css);
        $this->assertStringContainsString('--color-orange-800', $css);
    }

    private function builtProject(User $user): Project
    {
        $project = $user->projects()->create(['name' => 'Le Palmier']);
        $folder = sys_get_temp_dir().'/kekeli-test-'.bin2hex(random_bytes(4));
        mkdir($folder.'/css', 0777, true);
        file_put_contents($folder.'/index.html', '<h1>Le Palmier</h1>');
        file_put_contents($folder.'/css/style.css', 'body { color: black; }');
        file_put_contents($folder.'/.env', 'SECRET=jamais');
        exec('tar -czf '.escapeshellarg($folder.'.tar.gz').' -C '.escapeshellarg($folder).' .');
        $backups = app(ProjectBackup::class);
        $backups->disk()->put($backups->path($project->id), file_get_contents($folder.'.tar.gz'));
        exec('rm -rf '.escapeshellarg($folder).' '.escapeshellarg($folder.'.tar.gz'));
        $project->forceFill(['backup_at' => now()])->save();

        return $project;
    }
}
