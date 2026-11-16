<?php

namespace Tests\Feature;

use App\Agent\Diff;
use App\Agent\RunnerSignature;
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
        // La même phrase dite deux fois n'est affichée qu'une fois.
        $this->event($run, ['type' => 'narration', 'texte' => 'J’ajoute le bouton WhatsApp en bas de chaque page.'])->assertOk();
        $this->event($run, ['type' => 'etape', 'genre' => 'commande', 'cible' => 'npm test'])->assertOk();

        $page = $this->actingAs($user)->get(route('projects.show', $project));
        // Pendant le travail, une seule ligne d'état (la dernière phrase), qu'on déplie pour voir les actions.
        $this->assertSame(1, substr_count($page->getContent(), 'data-run-working'));
        $page->assertSee('data-narration', false)
            ->assertDontSee('Kekeli a fait')
            ->assertSeeInOrder(['J’ajoute le bouton WhatsApp en bas de chaque page.', 'Je regarde comment la page d’accueil est construite.', 'Fichier lu', 'Fichier modifié', 'Travail technique'])
            // Des libellés simples : jamais de nom de fichier ni de commande, même replié.
            ->assertDontSee('index.html')
            ->assertDontSee('npm test');
        $this->assertSame(1, substr_count($page->getContent(), 'J’ajoute le bouton WhatsApp en bas de chaque page.</span>') + substr_count($page->getContent(), 'J’ajoute le bouton WhatsApp en bas de chaque page.'."\n"), 'phrase affichée une seule fois');
    }

    public function test_the_last_message_is_short_and_simple_and_the_changed_files_are_only_in_the_code_view(): void
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
        // Une ancienne réponse en cinq parties : seul « Ce que j'ai fait » est montré au client.
        $answer = "## Ce que j'ai fait\nJ'ai ajouté un bouton WhatsApp vert en bas de chaque page.\n## Fichiers modifiés\n- index.html\n## Tests et vérifications\n- Les tests passent.\n## Ce qui n'a pas marché ou reste à faire\n- La photo de l'équipe manque encore.\n## À vérifier de votre côté\n- Touchez le bouton WhatsApp pour vérifier votre numéro.";
        $this->event($run, ['type' => 'terminee', 'reponse' => $answer])->assertOk();

        $run->refresh();
        $report = $run->report;
        $this->assertSame("J'ai ajouté un bouton WhatsApp vert en bas de chaque page.", $report['done']);
        $this->assertSame('Quelques points n\'ont pas pu être terminés. Si vous le souhaitez, Kekeli peut les reprendre.', $report['note']);
        $this->assertSame(['index.html', 'images/accueil.jpg'], array_column($report['files'], 'chemin'));
        $this->assertSame(['npm test', 'Qualité vérifiée'], array_column($report['tests'], 'text'));
        $this->assertSame(["La photo de l'équipe manque encore."], $report['problems']);
        $this->assertArrayNotHasKey('check', $report);

        // La réponse de l'agent n'est pas affichée deux fois : le dernier message la remplace.
        $this->assertSame($run->id, Message::where('role', Message::ROLE_ASSISTANT)->sole()->agent_run_id);
        $page = $this->actingAs($user)->get(route('projects.show', $project));
        $page->assertSee('data-report-message', false)
            ->assertSee('data-report-note', false)
            ->assertDontSee('Compte rendu de Kekeli')
            ->assertDontSee('À vérifier de votre côté')
            ->assertDontSee('Touchez le bouton WhatsApp')
            ->assertDontSee('Copier le compte rendu')
            ->assertDontSee('Partager sur WhatsApp')
            ->assertDontSee('https://wa.me/?text=', false)
            ->assertDontSee('Voir les modifications');
        $this->assertSame(1, substr_count($page->getContent(), 'J&#039;ai ajouté un bouton WhatsApp vert en bas de chaque page.'));

        // Les fichiers modifiés ne sont que dans la vue « Code », et chacun s'ouvre sur ses modifications.
        $html = $page->getContent();
        $code = substr($html, strpos($html, 'data-panel="code"'));
        $this->assertStringContainsString('data-last-changes', $code);
        $this->assertStringContainsString('images/accueil.jpg', $code);
        $this->assertStringNotContainsString('images/accueil.jpg', substr($html, 0, strpos($html, 'data-panel="code"')));

        Volt::actingAs($user)->test('projects.show', ['project' => $project])
            ->call('showChanges', $run->id)
            ->assertSee('2 fichiers modifiés par cette demande')
            ->assertSee('<p>Nouveau texte</p>')
            ->assertSee('Image ou fichier qui n\'est pas du texte');
    }

    public function test_the_client_never_reads_jargon_emoji_non_breaking_spaces_or_the_same_line_twice(): void
    {
        [$user, $project, $run] = $this->running();
        $nbsp = "\u{00A0}";
        $answer = "Votre site est prêt{$nbsp}: le menu avec les prix, les horaires, le plan et le bouton WhatsApp pour réserver. 🍽️\n"
            ."Votre site est prêt{$nbsp}: le menu avec les prix, les horaires, le plan et le bouton WhatsApp pour réserver.\n"
            ."- La page d'accueil répond (code 200).\n- J'ai lancé php artisan test.\n- J'ai modifié `index.html` et css/base.css.\n"
            ."Pouvez-vous m'envoyer une photo de votre salle{$nbsp}?";
        $this->event($run, ['type' => 'terminee', 'reponse' => $answer])->assertOk();

        $this->assertSame(
            // Les retours à la ligne écrits par Kekeli sont gardés (finitions du design) ; le jargon reste retiré.
            "Votre site est prêt : le menu avec les prix, les horaires, le plan et le bouton WhatsApp pour réserver.\nPouvez-vous m'envoyer une photo de votre salle ?",
            $run->fresh()->report['done'],
        );
        $this->assertNull($run->fresh()->report['note']);

        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertDontSee('code 200')
            ->assertDontSee('php artisan')
            ->assertDontSee('🍽')
            ->assertDontSee($nbsp);
    }

    public function test_a_message_is_written_even_when_the_agent_does_not_follow_the_rules_or_when_the_request_fails(): void
    {
        [$user, $project, $run] = $this->running();
        $this->event($run, ['type' => 'etape', 'genre' => 'fichier_ecrit', 'cible' => 'contact.html'])->assertOk();
        $this->event($run, ['type' => 'terminee', 'reponse' => 'J’ai créé la page de contact.'])->assertOk();

        $report = $run->fresh()->report;
        $this->assertSame('J’ai créé la page de contact.', $report['done']);
        $this->assertSame([['chemin' => 'contact.html', 'etat' => 'modifie']], $report['files']);
        $this->assertSame([], $report['problems']);
        $this->assertNull($report['note']);

        // Une réponse qui ne contient que du technique : une phrase simple à la place.
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Encore une fois']);
        $technical = $project->agentRuns()->create(['message_id' => $message->id, 'status' => AgentRun::STATUS_RUNNING]);
        $this->event($technical, ['type' => 'terminee', 'reponse' => "## Fichiers modifiés\n- `index.html`"])->assertOk();
        $this->assertSame('C\'est fait : votre site est à jour.', $technical->fresh()->report['done']);

        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Encore']);
        $failed = $project->agentRuns()->create(['message_id' => $message->id, 'status' => AgentRun::STATUS_RUNNING]);
        $this->event($failed, ['type' => 'narration', 'texte' => 'Je copie le modèle de style de base.'])->assertOk();
        $this->event($failed, ['type' => 'etape', 'genre' => 'installation_echec', 'cible' => 'npm run build (1 s)'])->assertOk();
        $this->event($failed, ['type' => 'erreur', 'raison' => 'duree', 'detail' => 'La tâche a dépassé la durée maximale.'])->assertOk();
        $this->assertSame([
            'La demande n\'a pas pu aller au bout : la demande a dépassé la durée maximale',
            'Une installation n\'a pas abouti : npm run build (1 s)',
        ], $failed->fresh()->report['problems']);
        // Comme Claude Code : ce qui s'est passé, pourquoi, ce qui est gardé et ce que Kekeli propose, puis ce qu'il avait fait.
        $this->assertSame("Kekeli n’a pas pu finir dans le temps prévu pour une demande. Rien n’est perdu. Demandez une partie plus petite à la fois, ou touchez « Réessayer ».\n\nAvant de s'arrêter, Kekeli avait fait ceci : Je copie le modèle de style de base.", $failed->fresh()->report['done']);
        $this->assertNull($failed->fresh()->report['note']);
        $this->assertTrue($failed->fresh()->report['retry']);
    }

    /**
     * Une construction trop lourde pour le modèle du moment (aucun fichier écrit) n'est jamais livrée comme finie :
     * le travail échoue avec un message clair, sans boutons de suite (15 novembre 2026).
     */
    public function test_a_creation_too_large_for_the_model_stops_with_a_clear_message(): void
    {
        [$user, $project, $run] = $this->running();
        $this->event($run, ['type' => 'erreur', 'raison' => 'trop_lourd', 'detail' => 'Aucun fichier du projet créé ni modifié pendant la construction.'])->assertOk();

        $run->refresh();
        $this->assertSame(AgentRun::STATUS_FAILED, $run->status);
        $this->assertSame([], $run->report['next']);
        $this->assertStringContainsString('cette création est trop lourde pour Kekeli en ce moment : rien d’inachevé n’a été livré', implode(' ', $run->report['problems']));
        $this->assertStringNotContainsString('votre site est à jour', $run->report['done']);
    }

    public function test_the_reasoning_of_the_agent_before_its_report_is_left_out(): void
    {
        [, , $run] = $this->running();
        $answer = "Everything is correct. Let's output the final report.## Ce que j'ai fait\nJ'ai ajouté la section Livraison.\n\n## Fichiers modifiés\n- `index.html`\n\n## Ce qui n'a pas marché ou reste à faire\nRien.\n\n## À vérifier de votre côté\n- Regardez la couleur verte.";
        $this->event($run, ['type' => 'terminee', 'reponse' => $answer])->assertOk();

        $report = $run->fresh()->report;
        $this->assertSame("J'ai ajouté la section Livraison.", $report['done']);
        $this->assertSame([], $report['problems']);
        $this->assertNull($report['note']);
    }

    public function test_the_history_of_the_reports_stays_available(): void
    {
        [$user, $project, $run] = $this->running();
        $this->event($run, ['type' => 'terminee', 'reponse' => "## Ce que j'ai fait\nPremière version du site."])->assertOk();
        $this->travel(3)->minutes();
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Change les couleurs']);
        $second = $project->agentRuns()->create(['message_id' => $message->id, 'status' => AgentRun::STATUS_RUNNING]);
        $this->event($second, ['type' => 'terminee', 'reponse' => "## Ce que j'ai fait\nCouleurs changées en vert."])->assertOk();

        // Plus de page « Comptes rendus » à part : chaque compte rendu est le dernier message de Kekeli, dans la discussion.
        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertOk()
            ->assertSeeInOrder(['Ajoute un bouton WhatsApp', 'Première version du site.', 'Change les couleurs', 'Couleurs changées en vert.'])
            ->assertDontSee(route('projects.reports', $project));
        $this->actingAs($user)->get(route('projects.reports', $project))->assertRedirect(route('projects.show', $project));
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
    public function test_the_report_like_claude_code_with_next_steps_open_steps_while_working_and_the_preview(): void
    {
        [$user, $project, $run] = $this->running();
        $project->forceFill(['preview_url' => 'https://4100-apercu.e2b.app', 'preview_expires_at' => now()->addMinutes(20)])->save();
        $this->event($run, ['type' => 'narration', 'texte' => 'Je crée la page d’accueil.'])->assertOk();
        $this->event($run, ['type' => 'etape', 'genre' => 'fichier_ecrit', 'cible' => 'index.html'])->assertOk();

        // Pendant le travail : les étapes dépliées, et l'aperçu dès que la première page existe.
        $page = $this->actingAs($user)->get(route('projects.show', $project))->getContent();
        $this->assertMatchesRegularExpression('/<details class="group" data-narration\s+open/', $page);
        $this->assertStringContainsString('data-show-preview', $page);

        $answer = "Votre site de formations en IA est prêt.\n- Accueil : vos formations et un bouton WhatsApp\n- Contact : votre numéro et le plan\nJ'ai choisi le nom « Lomé IA Academy » et des prix d'exemple : donnez-moi les vrais.\nSUITES : Ajouter mes vrais prix | Changer les couleurs | Mettre en ligne";
        $this->event($run, ['type' => 'terminee', 'reponse' => $answer])->assertOk();
        $run->refresh();
        $this->assertSame(['Ajouter mes vrais prix', 'Changer les couleurs', 'Mettre en ligne'], $run->report['next']);
        $this->assertStringNotContainsString('SUITES', $run->report['done']);

        $page = $this->actingAs($user)->get(route('projects.show', $project))->assertSee('data-next-steps="3"', false)
            ->assertSee('Ajouter mes vrais prix')
            ->assertSee('data-next-step="publish"', false)
            ->assertSee('choisi le nom')
            ->assertSee('data-open-preview', false)
            ->assertSee('Kekeli a fait')
            ->getContent();
        // Après le compte rendu, les étapes se replient.
        $this->assertDoesNotMatchRegularExpression('/<details class="group" data-narration\s+open/', $page);
    }

    public function test_a_work_sentence_left_as_last_message_becomes_a_simple_report_with_the_pages(): void
    {
        [$user, $project, $run] = $this->running();
        $this->event($run, ['type' => 'modifications', 'fichiers' => [
            ['chemin' => 'index.html', 'etat' => 'ajoute', 'ajouts' => 10, 'suppressions' => 0, 'binaire' => false],
            ['chemin' => 'formations.html', 'etat' => 'ajoute', 'ajouts' => 10, 'suppressions' => 0, 'binaire' => false],
            ['chemin' => 'css/site.css', 'etat' => 'ajoute', 'ajouts' => 10, 'suppressions' => 0, 'binaire' => false],
        ], 'nombre_fichiers' => 3, 'diff' => '', 'tronque' => false])->assertOk();
        $this->event($run, ['type' => 'terminee', 'reponse' => 'La création d’image est indisponible ; je réalise un visuel original directement dans la page.'])->assertOk();

        $report = $run->fresh()->report;
        $this->assertSame("Votre site est prêt. Voici ce qu'il contient :\n- Accueil\n- Formations", $report['done']);
        $this->assertSame(['Ajouter mes vrais prix', 'Changer les couleurs', 'Mettre en ligne'], $report['next']);
    }

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
