<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Countries where Kekeli is not yet available during the beta (the free offer of Gemini is forbidden there):
 * the country chosen by the user, and the country of the connection when the host gives it (« CF-IPCountry »).
 */
class Availability
{
    public function __construct(private Request $request) {}

    public function comingSoon(?User $user): bool
    {
        $blocked = (array) config('kekeli.coming_soon_countries');
        $connection = strtoupper((string) $this->request->header('CF-IPCountry'));

        return in_array((string) $user?->country, $blocked, true) || in_array($connection, $blocked, true);
    }
}
