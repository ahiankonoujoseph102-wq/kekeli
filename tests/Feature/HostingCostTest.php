<?php

namespace Tests\Feature;

use App\Models\AdminAlert;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HostingCostTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['kekeli.hosting.token' => 'cle-de-lecture-essai']);
    }

    /**
     * Réponse d'usage de Laravel Cloud : Kekeli et les deux autres sites du porteur du projet.
     *
     * @return array<string, mixed>
     */
    private function usage(int $kekeliCents, int $runnerCents, int $databaseCents): array
    {
        return ['data' => [
            'summary' => ['current_spend_cents' => 4200],
            'application_totals' => ['total_cost_cents' => 3000, 'application_count' => 4, 'applications' => [
                ['name' => 'Talkami', 'total_cost_cents' => 1500],
                ['name' => 'Lomé IA School', 'total_cost_cents' => 900],
                ['name' => 'kekeli', 'total_cost_cents' => $kekeliCents],
                ['name' => 'kekeli-runner', 'total_cost_cents' => $runnerCents],
            ]],
            'resources' => ['total_cost_cents' => 1200, 'databases' => ['items' => [
                ['name' => 'talkami', 'total_cents' => 800],
                ['name' => 'kekeli', 'total_cents' => $databaseCents],
            ]]],
        ]];
    }

    public function test_only_the_cost_of_kekeli_is_read_and_kept(): void
    {
        Http::fake(['cloud.laravel.com/api/usage*' => Http::response($this->usage(150, 80, 220))]);

        $this->artisan('kekeli:verifier-cout-hebergement')->assertSuccessful();

        // 1,50 $ + 0,80 $ + 2,20 $ = 4,50 $ ; Talkami et Lomé IA School ne sont jamais comptés.
        $this->assertSame(4.5, app(Settings::class)->get('hosting_last_cost')['usd']);
        $this->assertSame(0, AdminAlert::count());
        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && str_starts_with($request->url(), 'https://cloud.laravel.com/api/usage')
            && $request->header('Authorization')[0] === 'Bearer cle-de-lecture-essai');
        // Seulement de la lecture : jamais de modification sur Laravel Cloud.
        Http::assertNotSent(fn (Request $request) => $request->method() !== 'GET');

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.settings'))->assertSee('Coût de Kekeli ce mois-ci : 4,50 $');
    }

    public function test_the_administrator_is_alerted_at_80_then_100_percent_once_per_month(): void
    {
        app(Settings::class)->set('hosting_alert_usd', 10);

        Http::fake(['cloud.laravel.com/api/usage*' => Http::sequence()
            ->push($this->usage(400, 200, 250))
            ->push($this->usage(400, 200, 250))
            ->push($this->usage(600, 300, 300))]);

        // 8,50 $ : plus de 80 % de 10 $, une seule alerte même si la lecture se répète.
        $this->artisan('kekeli:verifier-cout-hebergement');
        $this->artisan('kekeli:verifier-cout-hebergement');
        $this->assertSame(['hosting_cost_80'], AdminAlert::pluck('kind')->all());

        // 12 $ : le seuil est atteint.
        $this->artisan('kekeli:verifier-cout-hebergement');
        $this->assertSame(['hosting_cost_80', 'hosting_cost_100'], AdminAlert::pluck('kind')->all());
        $this->assertStringContainsString('Talkami et Lomé IA School ne sont pas concernés', AdminAlert::latest('id')->value('message'));
    }

    public function test_an_unreadable_cost_alerts_without_stopping_anything(): void
    {
        Http::fake(['cloud.laravel.com/api/usage*' => Http::response(['message' => 'Unauthenticated.'], 401)]);
        $this->artisan('kekeli:verifier-cout-hebergement')->assertSuccessful();
        $this->assertSame('hosting_cost_unreadable', AdminAlert::sole()->kind);
    }

    public function test_without_the_reading_key_nothing_is_read(): void
    {
        config(['kekeli.hosting.token' => null]);
        Http::fake();

        $this->artisan('kekeli:verifier-cout-hebergement')->assertSuccessful();
        Http::assertNothingSent();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.settings'))->assertSee("La clé de lecture de Laravel Cloud n'est pas encore placée");
    }
}
