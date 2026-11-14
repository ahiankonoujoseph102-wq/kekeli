<?php

namespace App\Console\Commands;

use App\Support\ProductionCheck;
use Illuminate\Console\Command;

class CheckProduction extends Command
{
    protected $signature = 'kekeli:verifier-production';

    protected $description = 'Vérifie les réglages de production du site (aucune valeur secrète n\'est affichée)';

    public function handle(ProductionCheck $check): int
    {
        foreach ($check->run() as $line) {
            $mark = match ($line['level']) {
                'ok' => 'bon',
                'erreur' => 'à corriger',
                default => 'conseil',
            };
            $text = "[{$mark}] {$line['label']} : {$line['detail']}";
            match ($line['level']) {
                'ok' => $this->line($text),
                'erreur' => $this->error($text),
                default => $this->warn($text),
            };
        }

        if (! $check->passes()) {
            $this->newLine();
            $this->error(__('Some settings must be corrected before opening Kekeli to clients.'));

            return self::FAILURE;
        }

        $this->newLine();
        $this->info(__('The production settings are correct.'));

        return self::SUCCESS;
    }
}
