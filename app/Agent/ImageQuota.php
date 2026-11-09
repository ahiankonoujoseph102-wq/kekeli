<?php

namespace App\Agent;

use App\Models\AgentRun;
use App\Support\Settings;
use Carbon\CarbonImmutable;

/**
 * Images created by Kekeli for all users together, limited per day so that Kekeli never goes past the free
 * offer of the image service (Cloudflare Workers AI resets it every day at midnight UTC).
 */
class ImageQuota
{
    public function __construct(private Settings $settings) {}

    public function maxPerDay(): int
    {
        return max(0, $this->settings->int('images_max_per_day'));
    }

    public function createdToday(): int
    {
        return (int) AgentRun::query()
            ->where('updated_at', '>=', CarbonImmutable::now('UTC')->startOfDay())
            ->sum('images_created');
    }

    public function remainingToday(): int
    {
        return max(0, $this->maxPerDay() - $this->createdToday());
    }
}
