<?php

namespace Tests\Feature;

use App\Agent\RunReport;
use App\Models\AgentRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Finitions du design : le message final de Kekeli garde ses retours à la ligne, ses paragraphes et ses listes,
 * avec un affichage sûr (le texte est toujours échappé, jamais de HTML brut).
 */
class FinalMessageDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_line_breaks_paragraphs_and_lists_are_kept_and_shown(): void
    {
        $message = "Votre site est prêt.\nVos clients peuvent :\n- voir le menu avec les prix ;\n- réserver une table sur WhatsApp ;\n- trouver le plan.\n\nVous pouvez le mettre en ligne quand vous voulez.";

        $this->assertSame([
            ['type' => 'paragraph', 'lines' => ['Votre site est prêt.', 'Vos clients peuvent :']],
            ['type' => 'list', 'items' => ['voir le menu avec les prix ;', 'réserver une table sur WhatsApp ;', 'trouver le plan.']],
            ['type' => 'paragraph', 'lines' => ['Vous pouvez le mettre en ligne quand vous voulez.']],
        ], RunReport::blocks($message));

        $html = $this->pageWith($message);
        $this->assertMatchesRegularExpression('/Votre site est prêt\.\s*<br>\s*Vos clients peuvent&nbsp;:/u', $html);
        $this->assertStringContainsString('data-report-list', $html);
        $this->assertSame(3, substr_count($this->between($html, 'data-report-list', '</ul>'), '<li>'));
        $this->assertMatchesRegularExpression('/<li>voir le menu avec les prix&nbsp;;<\/li>/u', $html);
        $this->assertSame(2, substr_count($html, 'data-report-paragraph'));
    }

    public function test_numbered_lists_and_star_bullets_become_a_list(): void
    {
        $blocks = RunReport::blocks("Voici ce qui est prêt :\n1. Le menu\n2) Les horaires\n* Le plan");

        $this->assertSame(['Le menu', 'Les horaires', 'Le plan'], $blocks[1]['items']);
    }

    public function test_html_written_by_the_model_is_never_rendered(): void
    {
        $html = $this->pageWith("Votre site est prêt.\n- <b>Le menu</b> avec les prix\n- <script>alert(1)</script>Le plan");

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<b>Le menu</b>', $html);
    }

    public function test_a_question_mark_is_never_alone_at_the_start_of_a_line(): void
    {
        $html = $this->pageWith('Voulez-vous que je modifie la page contact de votre site web ? Dites « oui » !');

        $this->assertStringContainsString('site web&nbsp;?', $html);
        $this->assertStringContainsString('«&nbsp;oui&nbsp;»&nbsp;!', $html);
        $this->assertStringNotContainsString("\u{00A0}", $html);
    }

    public function test_the_final_message_is_stored_with_its_shape(): void
    {
        $user = User::factory()->create();
        $project = $user->projects()->create(['name' => 'Le Palmier']);
        $run = $project->agentRuns()->create([]);

        $report = RunReport::build($run, "Votre site est prêt.\n\n- Le menu avec les prix\n- Le bouton WhatsApp");

        $this->assertSame("Votre site est prêt.\n\n- Le menu avec les prix\n- Le bouton WhatsApp", $report['done']);
    }

    private function pageWith(string $message): string
    {
        $user = User::factory()->create();
        $project = $user->projects()->create(['name' => 'Le Palmier']);
        $request = $project->messages()->create(['role' => 'user', 'content' => 'Un site pour mon restaurant.']);
        $project->agentRuns()->create(['message_id' => $request->id])
            ->forceFill(['status' => AgentRun::STATUS_COMPLETED, 'report' => ['done' => $message, 'files' => [], 'tests' => [], 'problems' => []]])
            ->save();

        $html = $this->actingAs($user)->get(route('projects.show', $project))->assertOk()->getContent();

        // Les repères invisibles ajoutés par Livewire (commentaires HTML) ne comptent pas.
        return preg_replace('/<!--.*?-->/s', '', $html);
    }

    private function between(string $html, string $start, string $end): string
    {
        $from = strpos($html, $start);

        return substr($html, $from, strpos($html, $end, $from) - $from);
    }
}
