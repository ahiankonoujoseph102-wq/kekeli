<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class NewRunnerSecret extends Command
{
    protected $signature = 'kekeli:nouveau-secret';

    protected $description = 'Crée un secret neuf pour KEKELI_RUNNER_SECRET (le même dans le site et le Runner), sans l\'enregistrer nulle part';

    public function handle(): int
    {
        // 32 octets tirés au hasard par le système, écrits en 64 caractères : rien n'est gardé ni envoyé ailleurs.
        $this->line(bin2hex(random_bytes(32)));
        $this->newLine();
        $this->info('Copiez cette ligne dans KEKELI_RUNNER_SECRET des deux applications (« kekeli » et « kekeli-runner »). Ne l\'envoyez jamais dans une discussion.');

        return self::SUCCESS;
    }
}
