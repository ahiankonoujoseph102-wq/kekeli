@props(['run'])
{{--
    Le dernier message de Kekeli après une demande, comme Lovable ou Claude Code : quelques phrases simples qui disent
    ce qui a été fait, et une phrase polie seulement si quelque chose n'a pas pu être terminé. Les fichiers modifiés
    sont dans la vue « Code » ; les actions techniques restent repliées au-dessus.
--}}
@php($report = (array) $run->report)
<div class="space-y-2 text-sm text-zinc-800 dark:text-zinc-100" data-run-report>
    @if (! empty($report['question']) && blank($report['done'] ?? ''))
        {{-- Pas de réponse (fournisseurs indisponibles) : seulement la phrase polie ci-dessous. --}}
    @elseif (! empty($report['question']))
        {{-- Réponse du premier répondant : tout le texte, avec ses paragraphes et ses listes, sans émoji ni HTML. --}}
        <div class="space-y-2 break-words leading-relaxed" data-report-message data-question-answer>
            @foreach (\App\Agent\RunReport::blocks($report['done'] ?? '', filter: false) as $block)
                @if ($block['type'] === 'list')
                    <ul class="list-disc space-y-1 ps-5 marker:text-zinc-400 dark:marker:text-zinc-500" data-report-list>
                        @foreach ($block['items'] as $item)
                            <li>{{ \App\Agent\RunReport::display($item) }}</li>
                        @endforeach
                    </ul>
                @else
                    <p data-report-paragraph>
                        @foreach ($block['lines'] as $line)
                            {{ \App\Agent\RunReport::display($line) }}@if (! $loop->last)<br>@endif
                        @endforeach
                    </p>
                @endif
            @endforeach
        </div>
    @else
        {{-- Paragraphes, retours à la ligne et listes gardés ; chaque texte passe par {{ }}, jamais de HTML brut. --}}
        <div class="space-y-2 break-words leading-relaxed" data-report-message>
            @foreach (\App\Agent\RunReport::blocks($report['done'] ?? '') as $block)
                @if ($block['type'] === 'list')
                    <ul class="list-disc space-y-1 ps-5 marker:text-zinc-400 dark:marker:text-zinc-500" data-report-list>
                        @foreach ($block['items'] as $item)
                            <li>{{ \App\Agent\RunReport::display($item) }}</li>
                        @endforeach
                    </ul>
                @else
                    <p data-report-paragraph>
                        @foreach ($block['lines'] as $line)
                            {{ \App\Agent\RunReport::display($line) }}@if (! $loop->last)<br>@endif
                        @endforeach
                    </p>
                @endif
            @endforeach
        </div>
    @endif
    @if (! empty($report['sources']))
        {{-- Les sources d'une réponse avec recherche sur Internet : liens cliquables, numérotés comme dans le texte. --}}
        <div class="pt-1" data-sources>
            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Sources') }}</p>
            <ol class="mt-1 space-y-1">
                @foreach (\App\Jobs\RespondToMessage::sources((array) $report['sources']) as $source)
                    <li class="flex min-w-0 items-baseline gap-1.5 text-sm" data-source>
                        <span class="shrink-0 text-xs text-zinc-400">{{ $loop->iteration }}.</span>
                        <a href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer nofollow" class="min-w-0 truncate text-zinc-700 underline decoration-zinc-300 underline-offset-2 hover:text-zinc-900 dark:text-zinc-200 dark:decoration-zinc-600 dark:hover:text-white">{{ $source['title'] }}</a>
                        <span class="shrink-0 text-xs text-zinc-400">{{ preg_replace('/^www\./', '', (string) parse_url($source['url'], PHP_URL_HOST)) }}</span>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif
    @if (! empty($report['note']))
        <p class="text-zinc-600 dark:text-zinc-300" data-report-note>{{ $report['note'] }}</p>
    @endif
</div>
