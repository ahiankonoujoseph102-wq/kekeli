<?php

namespace App\Console\Commands;

use App\Billing\Subscriptions;
use Illuminate\Console\Command;

/**
 * Back to the free formula when the 30 days of a paid formula are over (with an e-mail), and an e-mail the day before.
 */
class ExpirePlans extends Command
{
    protected $signature = 'kekeli:expirer-formules';

    protected $description = 'Remet sur la formule gratuite les utilisateurs dont la formule payée est finie';

    public function handle(Subscriptions $subscriptions): int
    {
        // La veille de la fin : un e-mail de rappel ; à la fin : retour à la Gratuite et un e-mail.
        $reminded = $subscriptions->remindEnding();
        $count = $subscriptions->expireDue();
        $this->info($reminded.' rappel(s) envoyé(s), '.$count.' formule(s) terminée(s).');

        return self::SUCCESS;
    }
}
