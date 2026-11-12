@props(['run', 'last' => false, 'owner' => false, 'busy' => false])
{{--
    Le dernier message de Kekeli après une demande, comme Lovable ou Claude Code : quelques phrases simples qui disent
    ce qui a été fait, et une phrase polie seulement si quelque chose n'a pas pu être terminé. Les fichiers modifiés
    sont dans la vue « Code » ; les actions techniques restent repliées au-dessus.
--}}
@php($report = (array) $run->report)
@php($sources = \App\Jobs\RespondToMessage::sources((array) ($report['sources'] ?? [])))
@php($written = ! empty($report['question']) && filled($report['done'] ?? '') && blank($report['tool'] ?? null))
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
                            <li>{{ \App\Agent\RunReport::display($item, $sources) }}</li>
                        @endforeach
                    </ul>
                @else
                    <p data-report-paragraph>
                        @foreach ($block['lines'] as $line)
                            {{ \App\Agent\RunReport::display($line, $sources) }}@if (! $loop->last)<br>@endif
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
    @if ($sources !== [])
        {{-- Les sources d'une réponse avec recherche sur Internet : liens cliquables, numérotés comme les pastilles du texte. --}}
        <div class="pt-1" data-sources>
            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Sources') }}</p>
            <ol class="mt-1 space-y-1">
                @foreach ($sources as $source)
                    <li class="flex min-w-0 items-baseline gap-1.5 text-sm" data-source>
                        <span class="shrink-0 text-xs text-zinc-400">{{ $loop->iteration }}.</span>
                        <a href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer nofollow" class="min-w-0 truncate text-zinc-700 underline decoration-zinc-300 underline-offset-2 hover:text-zinc-900 dark:text-zinc-200 dark:decoration-zinc-600 dark:hover:text-white">{{ $source['title'] }}</a>
                        <span class="shrink-0 text-xs text-zinc-400">{{ preg_replace('/^www\./', '', (string) parse_url($source['url'], PHP_URL_HOST)).($source['date'] !== '' ? ' · '.$source['date'] : '') }}</span>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif
    @if (! empty($report['note']))
        <p class="text-zinc-600 dark:text-zinc-300" data-report-note>{{ $report['note'] }}</p>
    @endif
    @if ($written && $owner && $last && ! $busy && ($report['offer'] ?? null) === 'creer_site')
        {{-- Kekeli propose de créer le site : un bouton sous la question, comme les cartes de Lovable ou de Bolt.new. Le client peut aussi répondre en écrivant. --}}
        <div class="pt-1">
            <flux:button size="sm" variant="primary" icon="sparkles" wire:click="acceptSiteOffer('{{ $run->id }}')" wire:loading.attr="disabled" data-accept-site>{{ __('Yes, create the site') }}</flux:button>
        </div>
    @endif
    @if ($written)
        {{-- Sous chaque réponse écrite, comme Claude et ChatGPT : « Copier », et « Régénérer » sous la dernière. --}}
        <div class="flex items-center gap-0.5 pt-0.5 text-zinc-400 dark:text-zinc-500" x-data="{ copie: false }" data-answer-actions>
            <button type="button" class="flex size-8 items-center justify-center rounded-lg transition hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                x-on:click="
                    const texte = $refs.texte.value;
                    const fini = () => { copie = true; setTimeout(() => copie = false, 2000) };
                    if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(texte).then(fini) }
                    else { $refs.texte.classList.remove('hidden'); $refs.texte.select(); document.execCommand('copy'); $refs.texte.classList.add('hidden'); fini() }
                "
                x-bind:aria-label="copie ? @js(__('Copied')) : @js(__('Copy'))" x-bind:title="copie ? @js(__('Copied')) : @js(__('Copy'))" aria-label="{{ __('Copy') }}" title="{{ __('Copy') }}" data-copy-answer>
                <flux:icon.square-2-stack variant="micro" x-show="! copie" />
                <flux:icon.check variant="micro" class="text-emerald-500" x-show="copie" x-cloak />
            </button>
            <textarea x-ref="texte" class="hidden" readonly tabindex="-1" aria-hidden="true" data-copy-text>{{ \App\Agent\RunReport::copyText($report['done'], $sources) }}</textarea>
            @if ($owner && $last && ! $busy)
                <button type="button" class="flex size-8 items-center justify-center rounded-lg transition hover:bg-zinc-100 hover:text-zinc-700 disabled:opacity-40 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                    wire:click="regenerate('{{ $run->id }}')" wire:loading.attr="disabled" aria-label="{{ __('Regenerate') }}" title="{{ __('Regenerate') }}" data-regenerate>
                    <flux:icon.arrow-path variant="micro" />
                </button>
            @endif
        </div>
    @endif
</div>
