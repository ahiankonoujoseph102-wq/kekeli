<?php

namespace App\Http\Middleware;

use App\Agent\RunnerSignature;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only the Runner, which knows the shared secret, may send the agent's events.
 */
class VerifyRunnerSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $valid = RunnerSignature::verify(
            config('kekeli.runner.secret'),
            $request->header('X-Kekeli-Horodatage'),
            $request->header('X-Kekeli-Signature'),
            $request->getContent(),
        );

        abort_unless($valid, 401);

        return $next($request);
    }
}
