<?php

namespace App\Agent;

use App\Models\ProviderUsage;
use App\Support\Settings;
use Carbon\CarbonImmutable;

/**
 * La réserve de Cloudflare Workers AI pour les images des clients (chantier « le meilleur du gratuit », 15 novembre 2026).
 *
 * Les 10 000 « neurones » gratuits par jour de Cloudflare (remis à zéro à minuit UTC) sont partagés entre les images
 * (FLUX.1 schnell, environ 58 neurones par image) et les réponses écrites par les modèles de texte de Workers AI. Le
 * 15 novembre 2026, des essais d'images avaient vidé toute l'offre du jour : plus aucune image ni aucune réponse par
 * Cloudflare jusqu'à minuit UTC. Désormais, les images passent d'abord : le texte n'a droit qu'à ce qui reste une fois
 * réservées toutes les images permises du jour (réglage « images_max_per_day ») et une marge. Au-delà, Workers AI est
 * retiré des listes de texte (les autres fournisseurs gratuits prennent le relais).
 */
class WorkersAiReserve
{
    public function __construct(private Settings $settings) {}

    /** Les neurones de texte permis aujourd'hui : l'offre du jour moins la réserve des images et la marge. */
    public function textBudget(): int
    {
        $reserve = (array) config('kekeli.workersai_reserve', []);
        $daily = (int) ($reserve['daily_neurons'] ?? 10000);
        $perImage = (float) ($reserve['neurons_per_image'] ?? 58);
        $margin = (int) ($reserve['margin_neurons'] ?? 500);

        return max(0, (int) floor($daily - $this->settings->int('images_max_per_day') * $perImage - $margin));
    }

    /** Les neurones déjà utilisés aujourd'hui (jour UTC) par les modèles de texte de Workers AI, d'après les tokens. */
    public function textUsedToday(): float
    {
        $prices = (array) config('kekeli.workersai_reserve.neurons_per_million', []);
        $default = $prices['*'] ?? [24545, 77273];

        return (float) ProviderUsage::query()
            ->where('provider', 'workersai')
            ->whereDate('day', CarbonImmutable::now('UTC')->toDateString())
            ->get(['model', 'input_tokens', 'output_tokens'])
            ->sum(function (ProviderUsage $usage) use ($prices, $default) {
                [$in, $out] = $prices[$usage->model] ?? $default;

                return ($usage->input_tokens * $in + $usage->output_tokens * $out) / 1_000_000;
            });
    }

    /** Workers AI peut encore écrire du texte aujourd'hui sans toucher à la réserve des images. */
    public function textAllowed(): bool
    {
        return $this->textUsedToday() < $this->textBudget();
    }
}
