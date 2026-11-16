<?php

namespace App\Support;

/**
 * Checks that the settings of the site are those of a real production, before and after the first deployment on
 * Laravel Cloud (php artisan kekeli:verifier-production). It reads the configuration only: no secret value is ever
 * shown, only whether it is present. « erreur » blocks the use by clients, « conseil » can wait.
 */
class ProductionCheck
{
    /** Longest job of Kekeli, in seconds (PushRepository): the queue must wait longer before trying again. */
    public const LONGEST_JOB_SECONDS = 600;

    /**
     * @return list<array{level: string, label: string, detail: string}>
     */
    public function run(): array
    {
        $url = (string) config('app.url');
        $disk = (string) config('kekeli.backups.disk');
        $mailer = (string) config('mail.default');
        $runnerUrl = (string) config('kekeli.runner.url');
        $secret = (string) config('kekeli.runner.secret');
        $google = filled(config('services.google.client_id'));
        $githubApp = filled(config('services.github.app_slug')) && filled(config('services.github.client_id'));

        return [
            $this->check(config('app.env') === 'production', 'APP_ENV', 'production', __('APP_ENV must be « production ».')),
            $this->check(config('app.debug') === false, 'APP_DEBUG', 'false', __('APP_DEBUG must be « false »: otherwise an error would show the code and the settings to a visitor.')),
            $this->check(filled(config('app.key')), 'APP_KEY', __('present'), __('APP_KEY is missing.')),
            $this->check(str_starts_with($url, 'https://'), 'APP_URL', __('starts with https://'), __('APP_URL must be the address of the site in https, for example https://kekeli.laravel.cloud.')),
            $this->check(config('database.default') === 'pgsql', __('Database'), 'PostgreSQL', __('The database must be the PostgreSQL database attached to the application.')),
            $this->check(config('queue.default') !== 'sync', __('Queue'), (string) config('queue.default'), __('QUEUE_CONNECTION must be « database »: with « sync », a long work would block the page.')),
            $this->check((int) config('queue.connections.database.retry_after') > self::LONGEST_JOB_SECONDS, __('Queue delay'), (string) config('queue.connections.database.retry_after').' s', __('DB_QUEUE_RETRY_AFTER must be more than :seconds seconds.', ['seconds' => self::LONGEST_JOB_SECONDS])),
            $this->check($disk !== '' && config('filesystems.disks.'.$disk.'.driver') === 's3' && filled(config('filesystems.disks.'.$disk.'.bucket')), __('Files'), $disk, __('The files (photos, backups, documents, receipts) must go to the bucket of Laravel Cloud: the local disk is erased at each deployment.')),
            $this->check(config('session.secure') === true, __('Session cookie'), __('secure'), __('The session cookie must only travel in https (SESSION_SECURE_COOKIE).')),
            $this->check(! in_array($mailer, ['log', 'array', ''], true) && filled(config('mail.from.address')), __('E-mails'), $mailer, __('MAIL_MAILER must be « smtp » with the settings of Brevo, and MAIL_FROM_ADDRESS a sender validated in Brevo.')),
            $this->check(str_starts_with($runnerUrl, 'https://'), 'KEKELI_RUNNER_URL', __('starts with https://'), __('KEKELI_RUNNER_URL must be the address of the Runner in https.')),
            $this->check(strlen($secret) >= 32, 'KEKELI_RUNNER_SECRET', __('present'), __('KEKELI_RUNNER_SECRET is missing or shorter than 32 characters (the same on the site and on the Runner).')),
            $google
                ? $this->check(config('services.google.redirect') === rtrim($url, '/').'/auth/google/retour', 'GOOGLE_REDIRECT_URI', __('matches APP_URL'), __('GOOGLE_REDIRECT_URI must be :address.', ['address' => rtrim($url, '/').'/auth/google/retour']))
                : $this->advice('GOOGLE_CLIENT_ID', __('« Continue with Google » stays hidden until GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET are set.')),
            $githubApp
                ? $this->check(filled(config('services.github.client_secret')), 'GitHub App', __('present'), __('GITHUB_CLIENT_SECRET is missing.'))
                : $this->advice('GitHub App', __('« Link to GitHub » stays hidden until GITHUB_APP_SLUG, GITHUB_CLIENT_ID and GITHUB_CLIENT_SECRET are set.')),
            // GitHub (16 novembre 2026) : le nom court en minuscules, le « Client ID » de la GitHub App (jamais son « App ID »
            // ni un identifiant d'application OAuth), et l'adresse de retour à recopier sur github.com.
            ...($githubApp ? [
                $this->check((bool) preg_match('/^[a-z0-9][a-z0-9-]*$/', (string) config('services.github.app_slug')), 'GITHUB_APP_SLUG', __('short name in lower case'), __('GITHUB_APP_SLUG must be the short name of the address github.com/apps/<name>, in lower case (for example kekeli-lome), not the displayed name.')),
                $this->check(str_starts_with((string) config('services.github.client_id'), 'Iv'), 'GITHUB_CLIENT_ID', __('Client ID of a GitHub App'), __('GITHUB_CLIENT_ID must be the « Client ID » of the GitHub App (it starts with Iv), not its « App ID » (a number).')),
                $this->advice(__('GitHub · Callback URL'), __('On github.com, in the settings of the GitHub App, Callback URL: :address', ['address' => rtrim($url, '/').'/github/retour'])),
            ] : []),
            filled(config('kekeli.hosting.token'))
                ? ['level' => 'ok', 'label' => 'LARAVEL_CLOUD_API_TOKEN', 'detail' => __('present')]
                : $this->advice('LARAVEL_CLOUD_API_TOKEN', __('The daily reading of the hosting cost stays off until this read-only key is set.')),
        ];
    }

    public function passes(): bool
    {
        return collect($this->run())->doesntContain(fn ($line) => $line['level'] === 'erreur');
    }

    /**
     * @return array{level: string, label: string, detail: string}
     */
    private function check(bool $ok, string $label, string $expected, string $problem): array
    {
        return ['level' => $ok ? 'ok' : 'erreur', 'label' => $label, 'detail' => $ok ? $expected : $problem];
    }

    /**
     * @return array{level: string, label: string, detail: string}
     */
    private function advice(string $label, string $detail): array
    {
        return ['level' => 'conseil', 'label' => $label, 'detail' => $detail];
    }
}
