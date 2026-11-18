@props(['run', 'last' => false, 'owner' => false, 'busy' => false, 'variants' => []])
{{--
    Le dernier message de Kekeli après une demande, comme Lovable ou Claude Code : quelques phrases simples qui disent
    ce qui a été fait, et une phrase polie seulement si quelque chose n'a pas pu être terminé. Les fichiers modifiés
    sont dans la vue « Code » ; les actions techniques restent repliées au-dessus.
--}}
@php($report = (array) $run->report)
@php($sources = \App\Jobs\RespondToMessage::sources((array) ($report['sources'] ?? [])))
{{-- Base de connaissances : les passages envoyés au modèle, numérotés comme les renvois ; seuls ceux cités sont montrés. --}}
@php($knowledge = array_values(array_filter((array) ($report['knowledge'] ?? []), 'is_array')))
@php($cited = array_values(array_filter($knowledge, fn ($passage) => ! empty($passage['cited']))))
@php($written = ! empty($report['question']) && filled($report['done'] ?? '') && blank($report['tool'] ?? null))
<div class="space-y-2 text-sm text-zinc-800 dark:text-zinc-100" data-run-report>
    @if (! empty($report['question']) && blank($report['done'] ?? ''))
        {{-- Pas de réponse (fournisseurs indisponibles) : seulement la phrase polie ci-dessous. --}}
    @elseif (! empty($report['question']))
        @if (filled(data_get($report, 'thinking.seconds')))
            {{-- La réflexion du modèle, repliée comme chez Claude et ChatGPT : sa durée, et dépliée un court résumé en clair. --}}
            @php($thinking = (array) $report['thinking'])
            <details class="group" data-thinking>
                <summary class="flex w-fit cursor-pointer list-none items-center gap-1.5 rounded-lg py-0.5 text-zinc-500 select-none hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-100 [&::-webkit-details-marker]:hidden">
                    <flux:icon.light-bulb variant="micro" class="shrink-0" />
                    <span data-thinking-label>{{ __('Thinking · :duration', ['duration' => \App\Support\Formats::duration((int) $thinking['seconds'])]) }}</span>
                    @if (filled($thinking['summary'] ?? null))
                        <flux:icon.chevron-down variant="micro" class="shrink-0 transition-transform group-open:rotate-180" />
                    @endif
                </summary>
                @if (filled($thinking['summary'] ?? null))
                    <p class="mt-1.5 border-l border-zinc-200 pl-3 text-xs leading-relaxed text-zinc-600 dark:border-zinc-700 dark:text-zinc-300" data-thinking-summary>{{ \App\Agent\RunReport::display(\Illuminate\Support\Str::limit((string) $thinking['summary'], 400)) }}</p>
                @endif
            </details>
        @endif
        @if (filled(data_get($report, 'questions.items')))
            {{-- Questions avant une création, comme Claude : chaque question avec ses choix à toucher, « Choisis pour moi ». Les réponses partent comme un message normal (zone de saisie). --}}
            @php($items = (array) data_get($report, 'questions.items'))
            @php($active = $last && $owner && ! $busy)
            <div class="space-y-3" data-question-answer data-report-message data-questions="{{ count($items) }}"
                x-data="{
                    choix: {},
                    choisir(i, c) { this.choix[i] = this.choix[i] === c ? null : c },
                    envoyer(texte) { window.kekeliEnvoyerTexte(texte) },
                    reponses() {
                        const q = {{ \Illuminate\Support\Js::from(array_column($items, 'question')) }};
                        const lignes = q.map((question, i) => this.choix[i] ? question + ' ' + this.choix[i] : null).filter(Boolean);
                        if (lignes.length < q.length) lignes.push({{ \Illuminate\Support\Js::from(__('For the rest, choose for me.')) }});
                        return lignes.join('\n');
                    },
                    get aucun() { return ! Object.values(this.choix).some(Boolean) },
                }">
                <p>{{ \App\Agent\RunReport::display(\Illuminate\Support\Str::before((string) ($report['done'] ?? ''), "\n")) }}</p>
                @foreach ($items as $i => $item)
                    <div class="space-y-1.5" data-question>
                        <p class="font-medium">{{ $item['question'] }}</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($item['choices'] as $choice)
                                <button type="button" @disabled(! $active)
                                    x-on:click="choisir({{ $i }}, {{ \Illuminate\Support\Js::from($choice) }})"
                                    x-bind:aria-pressed="choix[{{ $i }}] === {{ \Illuminate\Support\Js::from($choice) }}"
                                    x-bind:class="choix[{{ $i }}] === {{ \Illuminate\Support\Js::from($choice) }} ? 'border-zinc-800 bg-zinc-800 text-white dark:border-zinc-100 dark:bg-zinc-100 dark:text-zinc-900' : 'border-zinc-300 text-zinc-700 hover:border-zinc-500 dark:border-zinc-600 dark:text-zinc-200'"
                                    class="min-h-10 rounded-full border px-3.5 py-1.5 text-sm transition-colors disabled:cursor-default disabled:opacity-70" data-question-choice>{{ $choice }}</button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                @if ($active)
                    <div class="flex flex-wrap items-center gap-2 pt-1">
                        <flux:button size="sm" variant="primary" x-bind:disabled="aucun" x-on:click="envoyer(reponses())" data-send-answers>{{ __('Send my answers') }}</flux:button>
                        <flux:button size="sm" variant="ghost" icon="sparkles" x-on:click="envoyer({{ \Illuminate\Support\Js::from(__('Choose for me')) }})" data-choose-for-me>{{ __('Choose for me') }}</flux:button>
                    </div>
                @endif
            </div>
        @else
            {{-- Réponse du premier répondant, mise en forme comme Claude ou ChatGPT (Markdown sûr : jamais de HTML brut). --}}
            <div class="kekeli-markdown break-words" data-report-message data-question-answer>
                {{ \App\Agent\Markdown::render($report['done'] ?? '', $sources !== [] ? $sources : $knowledge) }}
            </div>
        @endif
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
    @if (! empty(data_get($report, 'launch.lines')))
        {{-- « Lancer maintenant » : le journal lisible, replié sous le résumé. --}}
        <details class="group" data-launch-details>
            <summary class="flex w-fit cursor-pointer list-none items-center gap-1.5 text-xs text-zinc-500 select-none hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-100 [&::-webkit-details-marker]:hidden">
                <span>{{ trans_choice('{1} Log · :count step|[2,*] Log · :count steps', count($report['launch']['lines']), ['count' => count($report['launch']['lines'])]) }}</span>
                <flux:icon.chevron-down variant="micro" class="transition-transform group-open:rotate-180" />
            </summary>
            <div class="mt-2"><x-launch-log :lines="$report['launch']['lines']" /></div>
        </details>
    @endif
    @if ($sources !== [])
        {{-- Les sources d'une réponse avec recherche sur Internet : liens cliquables, numérotés comme les pastilles du texte. --}}
        <div class="pt-1" data-sources>
            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Sources') }}</p>
            <ol class="mt-1 space-y-1">
                @foreach ($sources as $source)
                    {{-- Sur un téléphone, le site et la date passent sous le titre : le titre garde toute la largeur. --}}
                    <li class="grid min-w-0 grid-cols-[auto_minmax(0,1fr)] items-baseline gap-x-1.5 text-sm sm:flex" data-source>
                        <span class="shrink-0 text-xs text-zinc-500">{{ $loop->iteration }}.</span>
                        <a href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer nofollow" class="min-w-0 truncate text-zinc-700 underline decoration-zinc-300 underline-offset-2 hover:text-zinc-900 dark:text-zinc-200 dark:decoration-zinc-600 dark:hover:text-white">{{ $source['title'] }}</a>
                        <span class="col-start-2 min-w-0 truncate text-xs text-zinc-500 sm:shrink-0">{{ preg_replace('/^www\./', '', (string) parse_url($source['url'], PHP_URL_HOST)).($source['date'] !== '' ? ' · '.$source['date'] : '') }}</span>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif
    @if ($cited !== [])
        {{-- Les sources d'une réponse de la base de connaissances : le document, la page et un court extrait, à déplier. --}}
        <div class="pt-1" data-knowledge-sources>
            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Sources') }}</p>
            <ol class="mt-1 space-y-1">
                @foreach ($cited as $passage)
                    <li data-knowledge-source>
                        <details class="group">
                            <summary class="flex min-h-10 cursor-pointer list-none items-baseline gap-1.5 text-sm select-none [&::-webkit-details-marker]:hidden">
                                <span class="shrink-0 text-xs text-zinc-500 tabular-nums">{{ $passage['n'] }}.</span>
                                <span class="min-w-0 truncate text-zinc-700 dark:text-zinc-200">{{ $passage['document'] }}</span>
                                @if (! empty($passage['moment']))
                                    <span class="shrink-0 text-xs text-zinc-500" data-knowledge-moment>{{ __('at :moment', ['moment' => $passage['moment']]) }}</span>
                                @elseif (! empty($passage['page']))
                                    <span class="shrink-0 text-xs text-zinc-500" data-knowledge-page>{{ __('page :page', ['page' => $passage['page']]) }}</span>
                                @endif
                                <flux:icon.chevron-down variant="micro" class="ms-auto shrink-0 text-zinc-400 transition-transform group-open:rotate-180" />
                            </summary>
                            @if (($passage['excerpt'] ?? '') !== '')
                                <blockquote class="mt-1 border-s-2 border-zinc-200 ps-3 text-sm text-zinc-600 dark:border-zinc-700 dark:text-zinc-300">{{ $passage['excerpt'] }}</blockquote>
                            @endif
                            @if (! empty($passage['video']))
                                {{-- Le lien est reconstruit par Kekeli (YouTube seulement) : la vidéo s'ouvre au moment cité. --}}
                                <a href="{{ $passage['video'] }}" target="_blank" rel="noopener noreferrer nofollow" class="kekeli-lien mt-1 inline-flex min-h-10 items-center gap-1 text-sm" data-knowledge-video-link>
                                    <flux:icon.play-circle variant="micro" />{{ __('Watch from :moment', ['moment' => $passage['moment']]) }}
                                </a>
                            @endif
                        </details>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif
    @if (! empty($report['note']))
        <p class="text-zinc-600 dark:text-zinc-300" data-report-note>{{ $report['note'] }}</p>
    @endif
    @if (! empty($report['retry']) && $last && $owner && ! $busy && $run->status === \App\Models\AgentRun::STATUS_FAILED)
        {{-- Comme Claude Code : un échec expliqué, puis « Réessayer » (la même demande repart). --}}
        <div class="pt-1">
            <flux:button size="sm" variant="primary" icon="arrow-path" wire:click="retry('{{ $run->id }}')" wire:loading.attr="disabled" data-retry>{{ __('Try again') }}</flux:button>
        </div>
    @endif
    @if (empty($report['question']) && ! empty($report['next']) && $last && $owner && ! $busy)
        {{-- Les suites proposées, comme Claude Code : 2 ou 3 boutons ; un toucher envoie la demande (ou ouvre « Mettre en ligne »). --}}
        <div class="flex flex-wrap gap-2 pt-1" data-next-steps="{{ count($report['next']) }}">
            @foreach (array_slice((array) $report['next'], 0, 3) as $step)
                @if (preg_match('/mettre en ligne|publier|récupérer le code|put online|publish|get the code/iu', (string) $step) && $run->project?->canBePublished())
                    <button type="button" class="min-h-10 rounded-full border border-zinc-300 px-3.5 py-1.5 text-sm text-zinc-700 transition-colors hover:border-zinc-500 dark:border-zinc-600 dark:text-zinc-200" x-on:click="$flux.modal('publish').show()" data-next-step="publish">{{ $step }}</button>
                @else
                    <button type="button" class="min-h-10 rounded-full border border-zinc-300 px-3.5 py-1.5 text-sm text-zinc-700 transition-colors hover:border-zinc-500 dark:border-zinc-600 dark:text-zinc-200" x-on:click="window.kekeliEnvoyerTexte({{ \Illuminate\Support\Js::from((string) $step) }})" data-next-step>{{ $step }}</button>
                @endif
            @endforeach
        </div>
    @endif
    @if ($written && $owner && $last && ! $busy && in_array($report['offer'] ?? null, [\App\Agent\Tools\CreateApplication::OFFER, \App\Agent\Tools\CreateAgent::OFFER, \App\Agent\Tools\CreateAutomation::OFFER, \App\Agent\Tools\CreateDocument::OFFER, \App\Agent\Tools\CreatePresentation::OFFER, \App\Agent\Tools\CreateVideo::OFFER], true))
        {{-- Le mode plan, comme Claude Code : le plan proposé, puis « Construire ce plan ». Le client peut aussi le corriger en écrivant. --}}
        <div class="flex flex-wrap items-center gap-2 pt-1" data-plan-actions>
            <flux:button size="sm" variant="primary" icon="wrench-screwdriver" wire:click="acceptPlan('{{ $run->id }}')" wire:loading.attr="disabled" data-build-plan>{{ __('Build this plan') }}</flux:button>
            @if (($report['offer'] ?? null) === \App\Agent\Tools\CreateApplication::OFFER && app(\App\Agent\TeamMode::class)->allowed(auth()->user()))
                {{-- Mode équipe (phase 1) : seulement pour un administrateur ; l'agent unique reste le chemin normal. --}}
                <flux:button size="sm" variant="ghost" icon="user-group" wire:click="acceptPlan('{{ $run->id }}', true)" wire:loading.attr="disabled" data-build-team>{{ __('Build in team mode (trial)') }}</flux:button>
            @endif
            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Or write what to change in the plan.') }}</span>
        </div>
    @endif
    @if ($written && $owner && $last && ! $busy && filled($report['suggestion'] ?? null) && blank($report['offer'] ?? null))
        {{-- Kekeli propose en une phrase, à la fin de sa réponse, de créer ce dont il a parlé, comme Claude : un toucher répond « Oui, créer… » comme un message écrit. --}}
        @php($suggestionLabel = \App\Jobs\RespondToMessage::suggestionLabel((string) $report['suggestion']))
        <div class="pt-1">
            <flux:button size="sm" variant="primary" icon="sparkles" x-on:click="window.kekeliEnvoyerTexte({{ \Illuminate\Support\Js::from($suggestionLabel) }})" data-suggestion="{{ $report['suggestion'] }}">{{ $suggestionLabel }}</flux:button>
        </div>
    @endif
    @if ($written && $owner && $last && ! $busy && in_array($report['offer'] ?? null, \App\Jobs\RespondToMessage::CONFIRMED_TOOLS, true))
        {{-- Kekeli propose de créer (ou demande confirmation d'une demande floue) : un bouton sous la question, comme les cartes de Lovable ou de Bolt.new. Le client peut aussi répondre en écrivant. --}}
        <div class="pt-1">
            <flux:button size="sm" variant="primary" icon="sparkles" wire:click="acceptOffer('{{ $run->id }}')" wire:loading.attr="disabled" data-accept-site data-accept-offer="{{ $report['offer'] }}">{{ ($report['offer_reason'] ?? null) === 'energie' ? __('Start anyway') : match ($report['offer']) { 'creer_image' => __('Yes, create the image'), 'creer_document' => __('Yes, create the document'), 'creer_presentation' => __('Yes, create the presentation'), 'creer_video' => __('Yes, create the video'), default => __('Yes, create the site') } }}</flux:button>
            @if (($report['offer_reason'] ?? null) === 'energie')
                <flux:button size="sm" variant="ghost" :href="route('plans')" wire:navigate data-energy-plans>{{ __('See the formulas') }}</flux:button>
            @endif
        </div>
    @endif
    @if (! empty($report['chat_limit']) && $owner && $last && app(\App\Agent\Energy::class)->isFree($run->project->user))
        {{-- Discussions du jour toutes utilisées (formule Gratuite) : un lien discret, comme la ligne « Énergie épuisée ». --}}
        <p class="text-sm text-zinc-500 dark:text-zinc-400" data-chat-limit>
            <a href="{{ route('plans') }}" wire:navigate class="underline decoration-zinc-300 underline-offset-2 hover:text-zinc-800 dark:decoration-zinc-600 dark:hover:text-zinc-100" data-see-plans>{{ __('See the formulas') }}</a>
        </p>
    @endif
    @if ($written && empty($report['chat_limit']))
        {{-- Sous chaque réponse écrite, comme Claude et ChatGPT : « Copier », et « Régénérer » sous la dernière. --}}
        <div class="-ms-2 flex items-center gap-0.5 pt-0.5 text-zinc-500 dark:text-zinc-400" x-data="{ copie: false }" data-answer-actions>
            <button type="button" class="flex size-10 items-center justify-center rounded-lg transition-colors hover:bg-zinc-100 sm:size-8 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
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
            <textarea x-ref="texte" class="hidden" readonly tabindex="-1" aria-hidden="true" data-copy-text>{{ \App\Agent\RunReport::copyText($report['done'], $sources !== [] ? $sources : $cited) }}</textarea>
            @if ($owner && $last && ! $busy)
                <button type="button" class="flex size-10 items-center justify-center rounded-lg transition-colors hover:bg-zinc-100 sm:size-8 hover:text-zinc-700 disabled:opacity-40 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                    x-on:click="$dispatch('kekeli-regenerer', { tache: '{{ $run->id }}' })" x-bind:disabled="$store.saisie?.enCours" aria-label="{{ __('Regenerate') }}" title="{{ __('Regenerate') }}" data-regenerate>
                    <flux:icon.arrow-path variant="micro" />
                </button>
            @endif
            @if ($owner && count($variants) > 1)
                {{-- Comme ChatGPT et Claude : revenir à une réponse précédente, « ‹ 1 / 2 › ». --}}
                @php($position = array_search($run->id, $variants, true))
                <div class="flex items-center text-xs tabular-nums" data-answer-variants="{{ ($position === false ? 0 : $position) + 1 }}/{{ count($variants) }}">
                    <button type="button" class="flex size-10 items-center justify-center rounded-lg transition-colors hover:bg-zinc-100 disabled:opacity-30 sm:size-8 dark:hover:bg-zinc-800" @if ($position > 0 && ! $busy) wire:click="showAnswer('{{ $variants[$position - 1] }}')" @else disabled @endif aria-label="{{ __('Previous answer') }}" title="{{ __('Previous answer') }}" data-previous-answer>
                        <flux:icon.chevron-left variant="micro" />
                    </button>
                    <span>{{ ($position === false ? 0 : $position) + 1 }} / {{ count($variants) }}</span>
                    <button type="button" class="flex size-10 items-center justify-center rounded-lg transition-colors hover:bg-zinc-100 disabled:opacity-30 sm:size-8 dark:hover:bg-zinc-800" @if ($position !== false && $position < count($variants) - 1 && ! $busy) wire:click="showAnswer('{{ $variants[$position + 1] }}')" @else disabled @endif aria-label="{{ __('Next answer') }}" title="{{ __('Next answer') }}" data-next-answer>
                        <flux:icon.chevron-right variant="micro" />
                    </button>
                </div>
            @endif
        </div>
    @endif
</div>
