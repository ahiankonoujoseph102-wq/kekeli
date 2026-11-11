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
        {{-- Paragraphes, retours à la ligne et listes gardés ; chaque texte passe par {{ }}, jamais de HTML brut. --}}
        <div class="space-y-2 break-words leading-relaxed" data-report-message>
            @foreach (\App\Agent\RunReport::blocks($report['done'] ?? '') as $block)
                @if ($block['type'] === 'list')
                    <ul class="list-disc space-y-1 ps-5 marker:text-zinc-400 dark:marker:text-zinc-500" data-report-list>
                        @foreach ($block['items'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                @else
                    <p data-report-paragraph>
                        @foreach ($block['lines'] as $line)
                            {{ $line }}@if (! $loop->last)<br>@endif
                        @endforeach
                    </p>
                @endif
            @endforeach
        </div>
    @endif
    @if (! empty($report['note']))
        <p class="text-zinc-600 dark:text-zinc-300" data-report-note>{{ $report['note'] }}</p>
    @endif
</div>
