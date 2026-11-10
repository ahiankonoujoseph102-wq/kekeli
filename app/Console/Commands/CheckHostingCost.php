<?php

namespace App\Console\Commands;

use App\Hosting\HostingCost;
use Illuminate\Console\Command;

/**
 * Daily reading of the cost of Kekeli on Laravel Cloud, with an alert above the threshold set by the administrator.
 */
class CheckHostingCost extends Command
{
    protected $signature = 'kekeli:verifier-cout-hebergement';

    protected $description = 'Lit le coût de Kekeli sur Laravel Cloud et alerte l\'administrateur au-delà du seuil';

    public function handle(HostingCost $hosting): int
    {
        if (! $hosting->configured()) {
            $this->info('Clé de lecture de Laravel Cloud absente : rien à vérifier.');

            return self::SUCCESS;
        }

        $cost = $hosting->check();
        $this->info($cost === null ? 'Coût illisible : alerte envoyée.' : 'Coût de Kekeli ce mois-ci : '.$cost.' $.');

        return self::SUCCESS;
    }
}
