<?php

namespace App\Console\Commands;

use App\Billing\Subscriptions;
use Illuminate\Console\Command;

/**
 * Back to the free formula when the 30 days of a paid formula are over.
 */
class ExpirePlans extends Command
{
    protected $signature = 'kekeli:expirer-formules';

    protected $description = 'Remet sur la formule gratuite les utilisateurs dont la formule payée est finie';

    public function handle(Subscriptions $subscriptions): int
    {
        $count = $subscriptions->expireDue();
        $this->info($count.' formule(s) terminée(s).');

        return self::SUCCESS;
    }
}
