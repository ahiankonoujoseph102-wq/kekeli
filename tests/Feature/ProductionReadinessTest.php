<?php

namespace Tests\Feature;

use App\Support\ProductionCheck;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use Tests\TestCase;

/**
 * Prêt pour la mise en ligne sur Laravel Cloud : HTTPS derrière le proxy, file d'attente, tâches planifiées, fichiers
 * dans le stockage objet, et la commande kekeli:verifier-production qui ne montre jamais de valeur secrète.
 */
class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_queue_waits_longer_than_the_longest_job_before_trying_again(): void
    {
        $longest = 0;
        foreach (glob(app_path('Jobs/*.php')) as $file) {
            $class = 'App\\Jobs\\'.basename($file, '.php');
            $defaults = (new ReflectionClass($class))->getDefaultProperties();
            $longest = max($longest, (int) ($defaults['timeout'] ?? 0));
        }

        $this->assertSame(ProductionCheck::LONGEST_JOB_SECONDS, $longest, 'le plus long travail a changé : mettre à jour ProductionCheck et retry_after');
        $this->assertGreaterThan($longest, (int) config('queue.connections.database.retry_after'));
        $this->assertSame('database', config('queue.connections.database.driver'));
    }

    public function test_the_site_behind_the_proxy_of_laravel_cloud_knows_the_visit_is_in_https(): void
    {
        Route::get('/essai-proxy', fn () => response()->json(['https' => request()->isSecure(), 'ip' => request()->ip()]));

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '41.207.1.2'])
            ->get('/essai-proxy')
            ->assertJson(['https' => true, 'ip' => '41.207.1.2']);
    }

    public function test_the_scheduled_tasks_and_the_health_page_are_there(): void
    {
        $commands = collect(app(Schedule::class)->events())->map(fn ($event) => $event->command)->implode("\n");
        foreach (['kekeli:effacer-ordinateurs-en-pause', 'kekeli:expirer-formules', 'kekeli:verifier-cout-hebergement'] as $command) {
            $this->assertStringContainsString($command, $commands);
        }

        $this->get('/up')->assertOk();
    }

    public function test_the_files_follow_the_default_disk_of_laravel_cloud_and_temporary_uploads_stay_local(): void
    {
        $this->assertSame('local', config('livewire.temporary_file_upload.disk'));
        $config = file_get_contents(config_path('kekeli.php'));
        $this->assertStringContainsString("env('KEKELI_DISQUE_SAUVEGARDES', env('FILESYSTEM_DISK', 'local'))", $config);
        $this->assertStringContainsString("env('SESSION_SECURE_COOKIE', env('APP_ENV') === 'production')", file_get_contents(config_path('session.php')));
    }

    public function test_the_check_lists_what_must_be_corrected_without_ever_showing_a_secret(): void
    {
        config(['kekeli.runner.secret' => 'un-secret-tres-long-invente-pour-le-test-0123456789', 'mail.default' => 'log']);

        $this->artisan('kekeli:verifier-production')
            ->expectsOutputToContain('[à corriger] APP_ENV')
            ->expectsOutputToContain('[à corriger] Fichiers')
            ->expectsOutputToContain('[bon] KEKELI_RUNNER_SECRET : présente')
            ->doesntExpectOutputToContain('un-secret-tres-long-invente')
            ->expectsOutputToContain('Certains réglages doivent être corrigés')
            ->assertFailed();
    }

    public function test_a_real_production_passes_the_check(): void
    {
        // La base des tests (SQLite en local, PostgreSQL sur GitHub) est remise telle quelle après la lecture des réglages.
        $testDatabase = config('database.default');
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.url' => 'https://kekeli.laravel.cloud',
            'database.default' => 'pgsql',
            'queue.default' => 'database',
            'kekeli.backups.disk' => 's3',
            'filesystems.disks.s3.bucket' => 'seau-invente',
            'session.secure' => true,
            'mail.default' => 'smtp',
            'mail.from.address' => 'expediteur@exemple.tg',
            'kekeli.runner.url' => 'https://kekeli-runner.laravel.cloud',
            'kekeli.runner.secret' => str_repeat('x', 40),
            'services.google.client_id' => 'identifiant-invente',
            'services.google.redirect' => 'https://kekeli.laravel.cloud/auth/google/retour',
        ]);

        $check = app(ProductionCheck::class);
        $this->assertTrue($check->passes(), json_encode(array_filter($check->run(), fn ($line) => $line['level'] === 'erreur')));

        // Une adresse de retour de Google qui ne correspond pas au site est signalée.
        config(['services.google.redirect' => 'http://127.0.0.1:8123/auth/google/retour']);
        $this->assertFalse($check->passes());
        config(['database.default' => $testDatabase]);

        $this->artisan('kekeli:verifier-production')->expectsOutputToContain('GOOGLE_REDIRECT_URI doit valoir https://kekeli.laravel.cloud/auth/google/retour.')->assertFailed();
    }

    public function test_a_new_secret_for_the_runner_is_long_and_different_each_time(): void
    {
        $first = $this->secret();
        $second = $this->secret();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $first);
        $this->assertNotSame($first, $second);
    }

    private function secret(): string
    {
        Artisan::call('kekeli:nouveau-secret');

        return trim(strtok(Artisan::output(), "\n"));
    }
}
