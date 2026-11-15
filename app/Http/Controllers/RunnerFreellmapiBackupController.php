<?php

namespace App\Http\Controllers;

use App\Agent\FreeLlmApi;
use App\Agent\ProjectBackup;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The backup of FreeLLMAPI's database (quota counters, pauses of the keys), sent by FreeLLMAPI every 2 minutes and
 * fetched when the Runner wakes up (docs/plan.md, partie 26.38). The file is encrypted with a key known only to the
 * Runner (FREELLMAPI_CLE_SAUVEGARDE): the site keeps it without being able to read it. Protected by a token derived
 * from the Runner's secret (FreeLlmApi::backupToken).
 */
class RunnerFreellmapiBackupController extends Controller
{
    /** One file per fingerprint of the Runner's secrets (« ?empreinte= »): a backup made with other secrets is never restored. */
    public static function path(?string $fingerprint): string
    {
        return 'freellmapi/base-'.(preg_match('/^[0-9a-f]{12}$/', (string) $fingerprint) ? $fingerprint : 'defaut').'.chiffree';
    }

    /** Largest backup accepted (the database holds counters only, never the clients' messages). */
    public const MAX_BYTES = 20 * 1024 * 1024;

    public function show(Request $request, ProjectBackup $backups): Response
    {
        $this->authorizeRunner($request);
        $path = self::path($request->query('empreinte'));
        abort_unless($backups->disk()->exists($path), 404);

        return response($backups->disk()->get($path), 200, ['Content-Type' => 'application/octet-stream', 'Cache-Control' => 'no-store']);
    }

    public function store(Request $request, ProjectBackup $backups): Response
    {
        $this->authorizeRunner($request);
        $content = $request->getContent();
        abort_if($content === '' || strlen($content) > self::MAX_BYTES, 413);
        // FreeLLMAPI's backups always start with its mark (« FAPIBK1 ») : anything else is refused.
        abort_unless(str_starts_with($content, "FAPIBK1\0"), 422);
        $backups->disk()->put(self::path($request->query('empreinte')), $content);

        return response()->noContent();
    }

    private function authorizeRunner(Request $request): void
    {
        $expected = FreeLlmApi::backupToken();
        abort_unless($expected !== null && hash_equals($expected, (string) $request->bearerToken()), 401);
    }
}
