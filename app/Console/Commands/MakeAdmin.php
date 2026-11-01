<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeAdmin extends Command
{
    protected $signature = 'kekeli:admin {email : Adresse e-mail du compte}';

    protected $description = "Donne l'accès à l'administration de Kekeli à un compte existant";

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('Aucun compte avec cette adresse e-mail.');

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => true])->save();
        $this->info("Le compte {$user->email} a maintenant accès à l'administration.");

        return self::SUCCESS;
    }
}
