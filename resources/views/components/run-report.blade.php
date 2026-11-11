@props(['run'])
{{--
    Le dernier message de Kekeli après une demande, comme Lovable ou Claude Code : quelques phrases simples qui disent
    ce qui a été fait, et une phrase polie seulement si quelque chose n'a pas pu être terminé. Les fichiers modifiés
    sont dans la vue « Code » ; les actions techniques restent repliées au-dessus.
--}}
@php($report = (array) $run->report)
<div class="space-y-2 text-sm text-zinc-800 dark:text-zinc-100" data-run-report>
    @if (! empty($report['question']))
        {{-- Réponse à une question : gardée telle quelle (listes comprises), sans émoji ni mise en forme. --}}
        <p class="whitespace-pre-line break-words" data-report-message data-question-answer>{{ \App\Agent\RunReport::clean($report['done'] ?? '') }}</p>
    @else
        <p class="whitespace-pre-line break-words" data-report-message>{{ \App\Agent\RunReport::plain($report['done'] ?? '') }}</p>
    @endif
    @if (! empty($report['note']))
        <p class="text-zinc-600 dark:text-zinc-300" data-report-note>{{ $report['note'] }}</p>
    @endif
</div>
