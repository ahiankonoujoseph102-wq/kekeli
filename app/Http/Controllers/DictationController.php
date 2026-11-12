<?php

namespace App\Http\Controllers;

use App\Agent\AnswerModels;
use App\Agent\Energy;
use App\Agent\RunnerClient;
use App\Models\Dictation;
use App\Models\ProviderUsage;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Voice dictation when the browser of the client cannot write the voice itself (for example Firefox): the short
 * recording goes to the Runner, which has it written by OpenAI. The text comes back into the input zone, where the
 * client can correct it before sending, like ChatGPT. The cost is counted in the client's energy and in the daily
 * ceiling of OpenAI. The recording is never kept.
 */
class DictationController extends Controller
{
    /** Longest dictation accepted, in seconds (the page stops the recording by itself before). */
    public const MAX_SECONDS = 120;

    public function store(Request $request, RunnerClient $runner, Energy $energy, AnswerModels $answers, Settings $settings): JsonResponse
    {
        $request->validate([
            'audio' => ['required', 'file', 'max:2048', 'mimetypes:audio/webm,audio/ogg,audio/mp4,audio/mpeg,audio/wav,audio/x-wav,audio/aac,video/webm'],
            'duree' => ['nullable', 'numeric', 'min:0', 'max:'.self::MAX_SECONDS + 5],
        ]);
        $user = $request->user();

        if ($energy->isExhausted($user)) {
            return response()->json(['erreur' => __('Your energy is used up: the dictation will come back with the recharge. You can still write your message.')], 402);
        }
        if (! $answers->openAiAvailable()) {
            return response()->json(['erreur' => __('Dictation is not available right now. You can write your message.')], 503);
        }

        $file = $request->file('audio');
        $type = str_starts_with((string) $file->getMimeType(), 'video/webm') ? 'audio/webm' : (string) $file->getMimeType();
        $model = (string) $settings->get('transcription_model');

        try {
            $result = $runner->transcribe(base64_encode((string) file_get_contents($file->getRealPath())), $type, substr(app()->getLocale(), 0, 2), $model);
        } catch (\Throwable $exception) {
            report($exception);
            $result = ['ok' => false];
        }

        if (! ($result['ok'] ?? false)) {
            return response()->json(['erreur' => __('Kekeli could not understand the recording. Try again, or write your message.')], 502);
        }

        $seconds = (int) min(self::MAX_SECONDS, max(1, (int) ($result['secondes'] ?? 0) ?: (int) ceil((float) $request->input('duree', 1))));
        $cost = $seconds / 60 * $settings->float('transcription_usd_per_minute');

        DB::transaction(function () use ($user, $model, $seconds, $cost) {
            Dictation::create(['user_id' => $user->id, 'model' => $model, 'seconds' => $seconds, 'cost_usd' => $cost]);
            $usage = ProviderUsage::query()->where(['day' => CarbonImmutable::now('UTC')->toDateString(), 'provider' => 'openai', 'model' => $model])->lockForUpdate()->first()
                ?? ProviderUsage::create(['day' => CarbonImmutable::now('UTC')->toDateString(), 'provider' => 'openai', 'model' => $model]);
            $usage->requests++;
            $usage->cost_usd = (float) $usage->cost_usd + $cost;
            $usage->save();
        });

        return response()->json(['texte' => (string) ($result['texte'] ?? '')]);
    }
}
