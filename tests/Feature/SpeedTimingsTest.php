<?php

namespace Tests\Feature;

use App\Agent\RunStarter;
use App\Agent\Timings;
use App\Jobs\RespondToMessage;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Chantier « vitesse », partie 1 : le temps de chaque étape d'une réponse est noté (seulement des nombres) et
 * montré dans Administration → Consommation.
 */
class SpeedTimingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_step_of_an_answer_is_timed_without_any_text(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $chat = $user->projects()->make(['name' => 'Discussion']);
        $chat->forceFill(['kind' => Project::KIND_CHAT])->save();
        $this->model->answers('Bonjour ! Que voulez-vous créer aujourd’hui ?');

        Volt::actingAs($user)->test('projects.show', ['project' => $chat])->set('content', 'Salut')->call('send');

        $run = AgentRun::query()->where('project_id', $chat->id)->sole();
        $server = Timings::secondsFrom((array) $run->timings);
        foreach (['recu', 'tache_recue', 'tache_demarree', 'appel_runner', 'premier_mot', 'fin'] as $step) {
            $this->assertArrayHasKey($step, $server, $step);
        }
        $this->assertSame(0.0, $server['recu']);
        $this->assertGreaterThanOrEqual($server['tache_recue'], $server['fin']);
        // Jamais le texte du client ni de la réponse dans les temps.
        $this->assertStringNotContainsString('Salut', json_encode($run->timings));
    }

    public function test_runner_and_browser_steps_keep_only_known_numbers(): void
    {
        $user = User::factory()->create();
        $project = $user->projects()->create(['name' => 'Site']);
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'x']);
        $run = $project->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);

        Timings::runner($run, ['appel_modele' => 120, 'premier_mot' => 900, 'fin' => 1500, 'cle' => 'sk-secret', 'texte' => 'bonjour']);
        Timings::browser($run, ['affiche' => 40, 'premier_mot' => -5, 'fin' => 'abc']);
        Timings::mark($run, 'recu', 1000);
        Timings::mark($run, 'recu', 9000);

        $timings = $run->fresh()->timings;
        $this->assertSame(['appel_modele' => 120, 'premier_mot' => 900, 'fin' => 1500], $timings['runner']);
        $this->assertSame(['affiche' => 40], $timings['navigateur']);
        $this->assertSame(1000, $timings['serveur']['recu'], 'la première heure notée est gardée');
    }

    public function test_the_administration_shows_the_speed_of_the_answers(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = $admin->projects()->create(['name' => 'Site']);
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'x']);
        $run = $project->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
        Timings::mark($run, 'recu', 10_000);
        Timings::mark($run, 'premier_mot', 11_500);
        Timings::mark($run, 'fin', 13_000);

        $this->actingAs($admin)->get(route('admin.consumption'))->assertOk()
            ->assertSee('Vitesse des réponses')
            ->assertSee('data-speed-row', false)
            ->assertSee('Premier mot 1,5 s')
            ->assertSee('Réponse finie 3,0 s');
    }

    public function test_a_work_is_timed_until_its_first_step(): void
    {
        Queue::fake();
        $user = User::factory()->create(['country' => 'TG']);
        $project = $user->projects()->create(['name' => 'Restaurant']);
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Crée le site']);
        $run = app(RunStarter::class)->startWith($project, $message);

        $this->assertArrayHasKey('travail_cree', $run->fresh()->timings['serveur']);
        unset($this->app[RespondToMessage::class]);
    }
}
