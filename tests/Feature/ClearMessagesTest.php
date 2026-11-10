<?php

namespace Tests\Feature;

use App\Agent\Diff;
use App\Agent\RunnerSignature;
use App\Agent\RunReport;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ClearMessagesTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'secret-de-test-tres-long-pour-les-signatures-0123456789';

    private const DIFF = "diff --git a/index.html b/index.html\nindex 1..2 100644\n--- a/index.html\n+++ b/index.html\n@@ -1,2 +1,2 @@\n <h1>Bonjour</h1>\n-<p>Ancien texte</p>\n+<p>Nouveau texte</p>\ndiff --git a/images/accueil.jpg b/images/accueil.jpg\nnew file mode 100644\nBinary files /dev/null and b/images/accueil.jpg differ\n";

    protected function setUp(): void
    {
        parent::setUp();
        config(['kekeli.runner.secret' => self::SECRET, 'kekeli.runner.url' => 'http://runner.test']);
    }

    public function test_kekeli_says_what_it_does_while_it_works_with_the_technical_details_folded(): void
    {
        [$user, $project, $run] = $this->running();

        $this->event($run, ['type' => 'narration', 'texte' => 'Je regarde comment la page d’accueil est construite.'])->assertOk();
        $this->event($run, ['type' => 'etape', 'genre' => 'fichier_lu', 'cible' => 'index.html'])->assertOk();
        $this->event($run, ['type' => 'narration', 'texte' => 'J’ajoute le bouton WhatsApp en bas de chaque page.'])->assertOk();
        $this->event($run, ['type' => 'etape', 'genre' => 'fichier_modifie', 'cible' => 'index.html'])->assertOk();
        $this->event($run, ['type' => 'etape', 'genre' => 'commande', 'cible' => 'npm test'])->assertOk();

        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertSeeInOrder(['Je regarde comment la page d’accueil est construite.', '1 détail', 'J’ajoute le bouton WhatsApp en bas de chaque page.', '2 détails'])
            ->assertSee('data-narration', false);
    }

    public function test_the_report_is_always_in_the_same_order_with_buttons_to_see_copy_and_share(): void
    {
        [$user, $project, $run] = $this->running();
        $this->event($run, ['type' => 'narration', 'texte' => 'J’ajoute le bouton WhatsApp.'])->assertOk();
        $this->event($run, ['type' => 'etape', 'genre' => 'fichier_modifie', 'cible' => 'index.html'])->assertOk();
        $this->event($run, ['type' => 'etape', 'genre' => 'commande', 'cible' => 'cd /home/user/projet && npm test 2>&1'])->assertOk();
        $this->event($run, ['type' => 'etape', 'genre' => 'qualite_ok', 'cible' => '3 page(s), 2 image(s) vérifiée(s)'])->assertOk();
        $this->event($run, ['type' => 'modifications', 'fichiers' => [
            ['chemin' => 'index.html', 'etat' => 'modifie', 'ajouts' => 1, 'suppressions' => 1, 'binaire' => false],
            ['chemin' => 'images/accueil.jpg', 'etat' => 'ajoute', 'ajouts' => 0, 'suppressions' => 0, 'binaire' => true],
        ], 'nombre_fichiers' => 2, 'diff' => self::DIFF, 'tronque' => false])->assertOk();
        $answer = "## Ce que j'ai fait\nJ'ai ajouté un bouton WhatsApp vert en bas de chaque page.\n## Fichiers modifiés\n- index.html\n## Tests et vérifications\n- Les tests passent.\n- Le style est vérifié.\n## Ce qui n'a pas marché ou reste à faire\n- La photo de l'équipe manque encore.\n## À vérifier de votre côté\n- Touchez le bouton WhatsApp pour vérifier votre numéro.";
        $this->event($run, ['type' => 'terminee', 'reponse' => $answer])->assertOk();

        $run->refresh();
        $report = $run->report;
        $this->assertSame("J'ai ajouté un bouton WhatsApp vert en bas de chaque page.", $report['done']);
        $this->assertSame(['index.html', 'images/accueil.jpg'], array_column($report['files'], 'chemin'));
        $this->assertSame(['Commande lancée : npm test', 'Qualité des pages vérifiée (3 page(s), 2 image(s) vérifiée(s)).'], array_column($report['tests'], 'text'));
        $this->assertSame("Les tests passent.\nLe style est vérifié.", $report['tests_text']);
        $this->assertSame(["La photo de l'équipe manque encore."], $report['problems']);
        $this->assertSame(['Touchez le bouton WhatsApp pour vérifier votre numéro.'], $report['check']);

        // La réponse de l'agent n'est pas affichée deux fois : le compte rendu la remplace.
        $this->assertSame($run->id, Message::where('role', Message::ROLE_ASSISTANT)->sole()->agent_run_id);
        $page = $this->actingAs($user)->get(route('projects.show', $project));
        $page->assertSeeInOrder(['Compte rendu de Kekeli', 'Ce qui a été fait', 'Fichiers modifiés (2)', 'Tests et vérifications', 'Ce qui n\'a pas marché ou reste à faire', 'À vérifier de votre côté'])
            ->assertSee('Voir les modifications')
            ->assertSee('Copier le compte rendu')
            ->assertSee('https://wa.me/?text=', false);
        $this->assertSame(1, substr_count($page->getContent(), 'J&#039;ai ajouté un bouton WhatsApp vert en bas de chaque page.'));

        // Le texte à copier ou à partager reprend les cinq parties, dans le même ordre.
        $text = RunReport::text($run);
        $positions = array_map(fn ($title) => strpos($text, $title), ['Demande : Ajoute un bouton WhatsApp', 'Ce qui a été fait :', 'Fichiers modifiés :', 'Tests et vérifications :', 'Ce qui n\'a pas marché ou reste à faire :', 'À vérifier de votre côté :']);
        $this->assertSame($positions, collect($positions)->sort()->values()->all());
        $this->assertStringContainsString('- images/accueil.jpg (ajouté)', $text);
        $this->assertStringContainsString("- Les tests passent.\n- Le style est vérifié.", $text);

        // « Voir les modifications » : en vert ce qui est ajouté, en rouge ce qui est retiré.
        Volt::actingAs($user)->test('projects.show', ['project' => $project])
            ->call('showChanges', $run->id)
            ->assertSee('2 fichiers modifiés par cette demande')
            ->assertSee('<p>Nouveau texte</p>')
            ->assertSee('Image ou fichier qui n\'est pas du texte');
    }

    public function test_a_report_is_written_even_when_the_agent_does_not_follow_the_titles_or_when_the_request_fails(): void
    {
        [$user, $project, $run] = $this->running();
        $this->event($run, ['type' => 'etape', 'genre' => 'fichier_ecrit', 'cible' => 'contact.html'])->assertOk();
        $this->event($run, ['type' => 'terminee', 'reponse' => 'J’ai créé la page de contact.'])->assertOk();

        $report = $run->fresh()->report;
        $this->assertSame('J’ai créé la page de contact.', $report['done']);
        $this->assertSame([['chemin' => 'contact.html', 'etat' => 'modifie']], $report['files']);
        $this->assertSame([], $report['problems']);
        $this->assertStringContainsString('Ouvrez l\'aperçu sur votre téléphone', $report['check'][0]);

        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Encore']);
        $failed = $project->agentRuns()->create(['message_id' => $message->id, 'status' => AgentRun::STATUS_RUNNING]);
        $this->event($failed, ['type' => 'narration', 'texte' => 'Je copie le modèle de style de base.'])->assertOk();
        $this->event($failed, ['type' => 'etape', 'genre' => 'installation_echec', 'cible' => 'npm run build (1 s)'])->assertOk();
        $this->event($failed, ['type' => 'erreur', 'raison' => 'duree', 'detail' => 'La tâche a dépassé la durée maximale.'])->assertOk();
        $this->assertSame([
            'La demande n\'a pas pu aller au bout : la demande a dépassé la durée maximale',
            'Une installation a échoué : npm run build',
        ], $failed->fresh()->report['problems']);
        $this->assertSame("Avant de s'arrêter, Kekeli avait fait ceci :\n- Je copie le modèle de style de base.", $failed->fresh()->report['done']);
    }

    public function test_the_reasoning_of_the_agent_before_its_report_is_left_out(): void
    {
        [, , $run] = $this->running();
        $answer = "Everything is correct. Let's output the final report.## Ce que j'ai fait\nJ'ai ajouté la section Livraison.\n\n## Fichiers modifiés\n- `index.html`\n\n## Ce qui n'a pas marché ou reste à faire\nRien.\n\n## À vérifier de votre côté\n- Regardez la couleur verte.";
        $this->event($run, ['type' => 'terminee', 'reponse' => $answer])->assertOk();

        $report = $run->fresh()->report;
        $this->assertSame("J'ai ajouté la section Livraison.", $report['done']);
        $this->assertSame([], $report['problems']);
        $this->assertSame(['Regardez la couleur verte.'], $report['check']);
    }

    public function test_the_history_of_the_reports_stays_available(): void
    {
        [$user, $project, $run] = $this->running();
        $this->event($run, ['type' => 'terminee', 'reponse' => "## Ce que j'ai fait\nPremière version du site."])->assertOk();
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Change les couleurs']);
        $second = $project->agentRuns()->create(['message_id' => $message->id, 'status' => AgentRun::STATUS_RUNNING]);
        $this->event($second, ['type' => 'terminee', 'reponse' => "## Ce que j'ai fait\nCouleurs changées en vert."])->assertOk();

        $this->actingAs($user)->get(route('projects.show', $project))->assertSee(route('projects.reports', $project));
        $this->actingAs($user)->get(route('projects.reports', $project))
            ->assertOk()
            ->assertSeeInOrder(['Change les couleurs', 'Couleurs changées en vert.', 'Ajoute un bouton WhatsApp', 'Première version du site.']);
        $this->actingAs(User::factory()->create())->get(route('projects.reports', $project))->assertNotFound();
    }

    public function test_the_differences_are_cut_into_files_and_lines(): void
    {
        $files = Diff::files(self::DIFF);
        $this->assertSame(['index.html', 'images/accueil.jpg'], array_keys($files));
        $this->assertSame(['gap', 'same', 'remove', 'add'], array_column($files['index.html'], 'type'));
        $this->assertSame('binary', $files['images/accueil.jpg'][0]['type']);
    }

    /**
     * @return array{0: User, 1: Project, 2: AgentRun}
     */
    private function running(): array
    {
        $user = User::factory()->create();
        $project = $user->projects()->create(['name' => 'Le Palmier']);
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Ajoute un bouton WhatsApp']);
        $run = $project->agentRuns()->create(['message_id' => $message->id, 'status' => AgentRun::STATUS_RUNNING, 'started_at' => now()]);

        return [$user, $project, $run];
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
