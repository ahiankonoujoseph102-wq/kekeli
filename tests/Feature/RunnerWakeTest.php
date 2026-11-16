<?php

namespace Tests\Feature;

use App\Agent\RunnerClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RunnerWakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_runner_is_woken_up_while_the_client_writes(): void
    {
        config(['kekeli.runner.url' => 'https://runner.test']);
        $user = User::factory()->create();

        // La zone du message porte l'adresse du réveil ; sans compte, rien.
        $this->post(route('runner.wake'))->assertRedirect();
        $this->actingAs($user)->get(route('projects.index'))->assertSee('data-reveil="'.route('runner.wake').'"', false);

        // Au plus une fois par minute pour tous ; jamais de donnée envoyée, seulement « /sante ».
        $this->actingAs($user)->postJson(route('runner.wake'))->assertOk();
        $this->assertTrue(Cache::has('kekeli.reveil-runner'));
        Http::fake(['runner.test/*' => Http::response(['ok' => true])]);
        app(RunnerClient::class)->wake();
        Http::assertSent(fn (Request $r) => $r->url() === 'https://runner.test/sante' && $r->method() === 'GET' && $r->body() === '');

        // Runner injoignable : aucune erreur pour le client.
        Http::fake(['runner.test/*' => Http::response('', 503)]);
        app(RunnerClient::class)->wake();
        $this->actingAs($user)->postJson(route('runner.wake'))->assertOk();
    }
}
