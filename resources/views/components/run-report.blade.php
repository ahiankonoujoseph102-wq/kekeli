@props(['run'])
{{-- Compte rendu d'une demande, toujours dans le même ordre, avec « Voir les modifications », « Copier » et « WhatsApp ». --}}
@php
    $report = (array) $run->report;
    $text = \App\Agent\RunReport::text($run);
    $states = ['ajoute' => ['+', 'text-emerald-600', __('added')], 'supprime' => ['−', 'text-red-600', __('deleted')], 'renomme' => ['→', 'text-sky-600', __('renamed')], 'modifie' => ['~', 'text-amber-600', __('changed')]];
@endphp
<div class="space-y-3 text-sm" data-run-report x-data="{ copie: false, texte: @js($text) }">
    <p class="font-semibold text-zinc-900 dark:text-white">{{ __('Report of Kekeli') }}</p>

    <section>
        <h3 class="font-medium text-zinc-900 dark:text-white">{{ __('What was done') }}</h3>
        <p class="mt-1 whitespace-pre-line text-zinc-700 dark:text-zinc-200">{{ $report['done'] ?? '' }}</p>
    </section>

    <section data-report-files>
        <h3 class="font-medium text-zinc-900 dark:text-white">{{ trans_choice('Changed files (:count)|Changed files (:count)', $report['files_count'] ?? count($report['files'] ?? []), ['count' => $report['files_count'] ?? count($report['files'] ?? [])]) }}</h3>
        @if (empty($report['files']))
            <p class="mt-1 text-zinc-600 dark:text-zinc-300">{{ __('No file changed.') }}</p>
        @else
            <ul class="mt-1 space-y-0.5 font-mono text-xs">
                @foreach (array_slice($report['files'], 0, 12) as $file)
                    @php([$mark, $color, $label] = $states[$file['etat']] ?? $states['modifie'])
                    <li class="break-all"><span class="{{ $color }} font-bold" title="{{ $label }}">{{ $mark }}</span> {{ $file['chemin'] }}</li>
                @endforeach
                @if (count($report['files']) > 12)
                    <li class="font-sans text-zinc-500">{{ __('and :count more', ['count' => count($report['files']) - 12]) }}</li>
                @endif
            </ul>
        @endif
    </section>

    <section data-report-tests>
        <h3 class="font-medium text-zinc-900 dark:text-white">{{ __('Tests and checks') }}</h3>
        @if (empty($report['tests']) && empty($report['tests_text']))
            <p class="mt-1 text-zinc-600 dark:text-zinc-300">{{ __('No test run for this request.') }}</p>
        @else
            <ul class="mt-1 space-y-1">
                @foreach ($report['tests'] ?? [] as $test)
                    <li class="flex items-start gap-1.5">
                        <flux:icon :name="$test['ok'] === false ? 'x-circle' : ($test['ok'] === true ? 'check-circle' : 'play-circle')" variant="micro" @class(['mt-0.5 shrink-0', 'text-orange-700 dark:text-orange-300' => $test['ok'] === false, 'text-emerald-500' => $test['ok'] === true, 'text-zinc-400' => $test['ok'] === null]) />
                        <span class="min-w-0 break-words text-zinc-700 dark:text-zinc-200">{{ $test['text'] }}</span>
                    </li>
                @endforeach
            </ul>
            @if (! empty($report['tests_text']))
                {{-- Ce que l'agent dit de ses tests, une ligne par point. --}}
                <ul class="mt-1 list-disc space-y-0.5 ps-5 text-zinc-700 dark:text-zinc-200" data-report-tests-agent>
                    @foreach (preg_split('/\R/u', $report['tests_text']) as $line)
                        <li>{{ ltrim($line, '-*• ') }}</li>
                    @endforeach
                </ul>
            @endif
        @endif
    </section>

    <section data-report-problems>
        <h3 class="font-medium text-zinc-900 dark:text-white">{{ __('What did not work or remains to do') }}</h3>
        @if (empty($report['problems']))
            <p class="mt-1 text-zinc-600 dark:text-zinc-300">{{ __('Nothing.') }}</p>
        @else
            <ul class="kekeli-erreur mt-1 list-disc space-y-0.5 ps-5">
                @foreach ($report['problems'] as $problem)
                    <li>{{ $problem }}</li>
                @endforeach
            </ul>
        @endif
    </section>

    <section data-report-check>
        <h3 class="font-medium text-zinc-900 dark:text-white">{{ __('To check on your side') }}</h3>
        <ul class="mt-1 list-disc space-y-0.5 ps-5 text-zinc-700 dark:text-zinc-200">
            @foreach ($report['check'] ?? [] as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
    </section>

    <div class="flex flex-wrap gap-2 pt-1">
        @if (! empty($run->changes['fichiers']))
            <flux:button size="sm" variant="primary" icon="document-magnifying-glass" wire:click="showChanges('{{ $run->id }}')" data-show-changes>{{ __('See the changes') }}</flux:button>
        @endif
        <flux:button size="sm" variant="filled" icon="clipboard" x-on:click="navigator.clipboard?.writeText(texte); copie = true; setTimeout(() => copie = false, 2500)" data-copy-report>
            <span x-show="! copie">{{ __('Copy the report') }}</span><span x-show="copie" x-cloak>{{ __('Copied') }}</span>
        </flux:button>
        <flux:button size="sm" variant="ghost" icon="share" :href="'https://wa.me/?text='.rawurlencode(\Illuminate\Support\Str::limit($text, 3000))" target="_blank" data-share-whatsapp>{{ __('Share on WhatsApp') }}</flux:button>
    </div>
</div>
