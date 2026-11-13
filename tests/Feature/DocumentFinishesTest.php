<?php

namespace Tests\Feature;

use App\Models\AgentRun;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Finitions des documents (pull request « Images et affiches ») : la carte du fichier dans la discussion, « Récents »
 * avec une icône par type, pas de « Brouillon » sous le titre d'un fichier, un seul « Télécharger » en haut, la ligne
 * d'actions d'un fichier.
 */
class DocumentFinishesTest extends TestCase
{
    use RefreshDatabase;

    private function document(User $user, string $type = 'document', int $versions = 1): Project
    {
        $project = $user->projects()->make(['name' => 'Devis Pagnes Ama']);
        $project->forceFill(['kind' => Project::KIND_DOCUMENT, 'document_type' => $type])->save();
        for ($n = 1; $n <= $versions; $n++) {
            $message = $project->messages()->create(['role' => 'user', 'content' => $n === 1 ? 'un devis' : 'mets le total en gras']);
            $run = $project->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_DOCUMENT]);
            $run->forceFill(['status' => AgentRun::STATUS_COMPLETED, 'finished_at' => now(), 'report' => ['done' => 'Votre devis est prêt.', 'files' => [], 'tests' => [], 'problems' => []]])->save();
            $run->steps()->create(['kind' => 'narration', 'target' => 'J’écris le contenu.']);
            $project->documentVersions()->create(['agent_run_id' => $run->id, 'number' => $n, 'title' => 'Devis Pagnes Ama', 'files' => $type === 'presentation' ? ['pptx' => 'presentation.pptx', 'pdf' => 'presentation.pdf'] : ['docx' => 'document.docx', 'pdf' => 'document.pdf'], 'previews' => ['apercu-1.jpg'], 'pages' => 1]);
        }

        return $project;
    }

    public function test_a_card_of_the_file_under_the_answer_with_its_formats_open_and_download(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $project = $this->document($user);

        $this->actingAs($user)->get(route('projects.show', $project))->assertOk()
            ->assertSee('data-file-card="1"', false)
            ->assertSee('Word · PDF')
            ->assertSee('data-file-card-open', false)
            ->assertSee('Ouvrir');
        Volt::actingAs($user)->test('projects.show', ['project' => $project])->call('showVersion', 1)->assertSet('versionNumber', 1);
    }

    public function test_the_line_of_actions_says_what_was_done_to_the_file(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $this->actingAs($user)->get(route('projects.show', $this->document($user, 'document', 2)))->assertOk()
            ->assertSee('A créé le document · Word, PDF')
            ->assertSee('A modifié le document · version 2')
            ->assertDontSee('Kekeli a fait');
        $this->actingAs($user)->get(route('projects.show', $this->document($user, 'presentation')))->assertSee('A créé la présentation · PowerPoint, PDF');
    }

    public function test_no_draft_state_under_the_title_of_a_file_and_one_download_button(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $html = $this->actingAs($user)->get(route('projects.show', $this->document($user)))->assertOk()->getContent();
        $this->assertStringNotContainsString('data-project-state', $html);
        // Rien à télécharger dans la barre du haut : seulement dans le panneau du fichier (et sur la carte).
        $bar = substr($html, strpos($html, 'data-project-bar'), strpos($html, '</header>') - strpos($html, 'data-project-bar'));
        $this->assertStringNotContainsString('data-download', $bar);
        $this->assertStringContainsString('data-document-preview', $html);

        $site = $user->projects()->make(['name' => 'Restaurant']);
        $site->forceFill(['kind' => Project::KIND_SITE])->save();
        $this->actingAs($user)->get(route('projects.show', $site))->assertSee('data-project-state', false);
    }

    public function test_recents_show_one_icon_per_type_and_only_the_filters_of_existing_types(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        foreach ([
            ['Restaurant', ['kind' => Project::KIND_SITE]],
            ['Rendez-vous', ['kind' => Project::KIND_WEBAPP, 'is_application' => true]],
            ['Assistante', ['kind' => Project::KIND_WEBAPP, 'is_agent' => true]],
            ['Conseils du matin', ['kind' => Project::KIND_WEBAPP, 'is_automation' => true]],
            ['Lettre', ['kind' => Project::KIND_DOCUMENT, 'document_type' => 'document']],
            ['Pitch', ['kind' => Project::KIND_DOCUMENT, 'document_type' => 'presentation']],
            ['Affiche', ['kind' => Project::KIND_DOCUMENT, 'document_type' => 'image']],
            ['Question', ['kind' => Project::KIND_CHAT]],
        ] as [$name, $attributes]) {
            $user->projects()->make(['name' => $name])->forceFill($attributes)->save();
        }

        $component = Volt::actingAs($user)->test('navigation.recents');
        foreach (['window', 'device-phone-mobile', 'chat-bubble-oval-left-ellipsis', 'arrow-path-rounded-square', 'document-text', 'presentation-chart-bar', 'photo', 'chat-bubble-left-right'] as $icon) {
            $component->assertSee('data-recent-icon="'.$icon.'"', false);
        }
        $this->assertSame(['all', 'site', 'application', 'agent', 'automation', 'document', 'presentation', 'image', 'chat'], array_keys($component->get('filters')));
        $component->call('setFilter', 'presentation')->assertSee('Pitch')->assertDontSee('Lettre');
        $component->call('setFilter', 'site')->assertSee('Restaurant')->assertDontSee('Rendez-vous');
        $component->call('setFilter', 'application')->assertSee('Rendez-vous')->assertDontSee('Assistante');

        // Un client qui n'a que des documents ne voit que « Tous » et « Documents ».
        $other = User::factory()->create();
        $other->projects()->make(['name' => 'CV'])->forceFill(['kind' => Project::KIND_DOCUMENT, 'document_type' => 'document'])->save();
        $this->assertSame(['all', 'document'], array_keys(Volt::actingAs($other)->test('navigation.recents')->get('filters')));
    }
}
