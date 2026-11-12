<?php

namespace App\Console\Commands;

use App\Github\GithubClient;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Sleep;

/**
 * Relie un compte de Kekeli à GitHub avec le flux « appareil » officiel de GitHub : un code court s'affiche, le client
 * le tape sur github.com/login/device depuis son téléphone. Sert à l'essai réel tant que le site n'est pas en ligne
 * (GitHub ne peut alors pas revenir sur Kekeli). Les jetons sont gardés chiffrés et ne sont jamais affichés.
 */
class GithubDeviceLogin extends Command
{
    protected $signature = 'kekeli:github-appareil {email : adresse du compte Kekeli à relier}';

    protected $description = 'Relie un compte à GitHub avec un code court à taper sur github.com/login/device';

    public function handle(GithubClient $github): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user || ! $github->configured()) {
            $this->error($user ? 'La GitHub App n\'est pas réglée (GITHUB_APP_SLUG, GITHUB_CLIENT_ID, GITHUB_CLIENT_SECRET).' : 'Compte introuvable.');

            return self::FAILURE;
        }

        $device = $github->deviceCode();
        $this->info('Code à taper sur '.$device['verification_uri'].' : '.$device['user_code']);

        $interval = $device['interval'];
        $until = now()->addSeconds($device['expires_in']);
        while (now()->isBefore($until)) {
            Sleep::for($interval)->seconds();
            $result = $github->pollDevice($device['device_code']);
            if ($result['etat'] === 'attendre') {
                continue;
            }
            if ($result['etat'] === 'ralentir') {
                $interval = max($interval + 5, (int) ($result['interval'] ?? 0));

                continue;
            }
            if ($result['etat'] === 'erreur') {
                $this->error('GitHub a refusé : '.$result['erreur']);

                return self::FAILURE;
            }

            $user->forceFill([
                'github_token' => $result['token'],
                'github_refresh_token' => $result['refresh'],
                'github_token_expires_at' => $result['expires_at'],
                'github_login' => $result['login'],
                'github_installation_id' => $result['installation'],
            ])->save();
            $this->info('Compte relié à GitHub : '.$result['login'].($result['installation'] ? ' (installation trouvée)' : ' (aucune installation : installez d\'abord la GitHub App)'));

            return self::SUCCESS;
        }

        $this->error('Le code a expiré.');

        return self::FAILURE;
    }
}
