<?php

namespace App\Providers;

use App\Agent\Energy;
use App\Models\User;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Les réglages lus une seule fois par requête ou par tâche de la file d'attente (« scoped »).
        $this->app->scoped(Settings::class);
        // L'énergie lue pendant l'affichage d'une page est gardée le temps de cette page seulement (Energy::remembered).
        $this->app->scoped(Energy::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));

        Gate::define('admin', fn (User $user) => $user->is_admin);

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
