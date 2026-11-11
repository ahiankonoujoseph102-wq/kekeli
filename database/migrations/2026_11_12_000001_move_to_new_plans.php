<?php

use App\Billing\Subscriptions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * New formulas of the 12th of November 2026 (docs/plan.md, part 25): Gratuite, Pro, Expert, Agence. The settings saved
 * in the administration for the older formulas are replaced by the new defaults, the subscribers of Découverte and
 * Entreprise move to the closest formula without losing anything they paid, the daily ceiling of the free formula
 * goes from 1 500 to 1 000 FCFA (unless the administrator had changed it), and the price of the cache of Claude
 * Sonnet 5.5 is corrected (0,10 $ instead of 0,20 $).
 */
return new class extends Migration
{
    public function up(): void
    {
        $stored = fn (string $key) => ($value = DB::table('settings')->where('key', $key)->value('value')) === null ? null : json_decode($value, true);

        // Prix des anciennes formules, tels qu'ils étaient réglés (sinon les valeurs par défaut d'avant).
        $oldPlans = (array) ($stored('plans') ?? []);
        $oldPrices = [
            'decouverte' => (int) ($oldPlans['decouverte']['price_fcfa'] ?? 2500),
            'entreprise' => (int) ($oldPlans['entreprise']['price_fcfa'] ?? 25000),
        ];

        DB::table('settings')->where('key', 'plans')->delete();
        if ((float) $stored('free_daily_cap_fcfa') === 1500.0) {
            DB::table('settings')->where('key', 'free_daily_cap_fcfa')->delete();
        }
        $prices = $stored('model_prices');
        if (is_array($prices) && (float) ($prices['anthropic/claude-sonnet-5-5']['cache'] ?? 0) === 0.2) {
            $prices['anthropic/claude-sonnet-5-5']['cache'] = 0.10;
            DB::table('settings')->where('key', 'model_prices')->update(['value' => json_encode($prices)]);
        }
        Cache::forget('kekeli.settings');

        app(Subscriptions::class)->moveToNewPlans($oldPrices);
    }

    public function down(): void
    {
        // Rien à défaire : les abonnés gardent la formule et les jours obtenus.
    }
};
