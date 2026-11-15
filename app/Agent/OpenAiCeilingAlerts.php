<?php

namespace App\Agent;

use App\Mail\Emails;
use App\Models\AdminAlert;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Alerts of the OpenAI spending of the day (decision of the project owner, 15 November 2026, with the ceiling raised
 * from 1 $ to 10 $): when the spending reaches 80 % then 100 % of « openai_daily_cap_usd », the administrator receives
 * an e-mail and an alert in the administration, once per threshold and per day (UTC day, like the ceiling).
 */
class OpenAiCeilingAlerts
{
    public const THRESHOLDS = [80, 100];

    public function __construct(private Settings $settings, private AnswerModels $models, private Emails $emails) {}

    public function check(): void
    {
        $ceiling = $this->settings->float('openai_daily_cap_usd');
        if ($ceiling <= 0) {
            return;
        }
        $spent = $this->models->openAiSpentToday();
        $day = CarbonImmutable::now('UTC')->toDateString();
        foreach (self::THRESHOLDS as $percent) {
            // Cache::add n'écrit que si la clé n'existe pas encore : une seule alerte par seuil et par jour.
            if ($spent < $ceiling * $percent / 100 || ! Cache::add("kekeli:alerte-openai:{$day}:{$percent}", true, now()->addDays(2))) {
                continue;
            }
            AdminAlert::create([
                'kind' => 'openai_ceiling',
                'message' => $percent >= 100
                    ? __('The OpenAI ceiling of the day is reached (:spent $ of :ceiling $). Kekeli goes on with the other providers.', ['spent' => number_format($spent, 2, ',', ' '), 'ceiling' => number_format($ceiling, 2, ',', ' ')])
                    : __(':percent % of the OpenAI ceiling of the day is used (:spent $ of :ceiling $).', ['percent' => $percent, 'spent' => number_format($spent, 2, ',', ' '), 'ceiling' => number_format($ceiling, 2, ',', ' ')]),
            ]);
            $this->emails->openAiCeiling($percent, $spent, $ceiling);
        }
    }
}
