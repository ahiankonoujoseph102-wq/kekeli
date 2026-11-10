@props(['run'])
{{-- « Voir les modifications » : ce qui a changé pendant une demande, fichier par fichier. --}}
<flux:modal name="run-changes" class="w-full max-w-2xl">
    @if ($run)
        @php
            $files = collect($run->changes['fichiers'] ?? []);
            $diffs = \App\Agent\Diff::files($run->diff);
            $labels = ['ajoute' => __('added'), 'supprime' => __('deleted'), 'renomme' => __('renamed'), 'modifie' => __('changed')];
        @endphp
        <div class="space-y-4" data-changes-modal>
            <div>
                <flux:heading size="lg">{{ __('See the changes') }}</flux:heading>
                <flux:subheading>
                    {{ trans_choice(':count file changed by this request|:count files changed by this request', $files->count(), ['count' => $files->count()]) }}
                    ({{ __(':added added, :changed changed, :deleted deleted', ['added' => $files->where('etat', 'ajoute')->count(), 'changed' => $files->whereIn('etat', ['modifie', 'renomme'])->count(), 'deleted' => $files->where('etat', 'supprime')->count()]) }})
                </flux:subheading>
                <p class="mt-1 text-xs text-zinc-500">{{ __('In green, what was added; in red, what was removed.') }}</p>
            </div>
            @foreach ($files as $file)
                <details class="rounded-lg border border-zinc-200 dark:border-zinc-700" @if ($loop->first) open @endif data-changed-file="{{ $file['chemin'] }}">
                    <summary class="flex cursor-pointer items-center justify-between gap-2 px-3 py-2 text-sm">
                        <span class="min-w-0 break-all font-mono text-xs">{{ $file['chemin'] }}</span>
                        <span class="shrink-0 text-xs">
                            <span class="text-zinc-500">{{ $labels[$file['etat']] ?? $file['etat'] }}</span>
                            @unless ($file['binaire'])
                                <span class="text-emerald-600">+{{ $file['ajouts'] }}</span> <span class="text-red-600">−{{ $file['suppressions'] }}</span>
                            @endunless
                        </span>
                    </summary>
                    <div class="max-h-96 overflow-auto border-t border-zinc-200 bg-zinc-50 font-mono text-[11px] leading-5 dark:border-zinc-700 dark:bg-zinc-900">
                        @forelse ($diffs[$file['chemin']] ?? [] as $line)
                            @if ($line['type'] === 'binary')
                                <p class="px-3 py-2 font-sans text-xs text-zinc-500">{{ __('Image or non-text file: nothing to show line by line.') }}</p>
                            @else
                                <div @class(['whitespace-pre px-3', 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200' => $line['type'] === 'add', 'bg-red-50 text-red-800 dark:bg-red-950 dark:text-red-200' => $line['type'] === 'remove', 'text-zinc-400' => $line['type'] === 'gap', 'text-zinc-600 dark:text-zinc-300' => $line['type'] === 'same'])>{{ ['add' => '+ ', 'remove' => '− ', 'same' => '  ', 'gap' => ''][$line['type']] }}{{ $line['text'] }}</div>
                            @endif
                        @empty
                            <p class="px-3 py-2 font-sans text-xs text-zinc-500">{{ __('The details of this file are too long to be shown here.') }}</p>
                        @endforelse
                    </div>
                </details>
            @endforeach
            @if ($run->changes['tronque'] ?? false)
                <p class="text-xs text-zinc-500">{{ __('The changes are very long: only the beginning is shown.') }}</p>
            @endif
        </div>
    @endif
</flux:modal>
