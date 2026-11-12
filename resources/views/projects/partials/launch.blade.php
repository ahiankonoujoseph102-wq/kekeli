{{--
    Automatisation : pas d'aperçu. « Lancer maintenant » la fait tourner une fois dans l'ordinateur du projet, avec les
    Variables secrètes du client, et montre son journal lisible. Ensuite, elle tourne seule sur le dépôt GitHub du client.
--}}
@php($launch = $this->lastLaunch)
@php($launchLines = (array) data_get($launch?->report, 'launch.lines', []))
<div class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" data-launch-panel>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="min-w-0">
            <p class="font-medium text-zinc-900 dark:text-white">{{ __('Try your automation') }}</p>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('It runs once now, with your Secret variables. Then it runs by itself on your GitHub repository.') }}</p>
        </div>
        @if ($this->isOwner)
            <flux:button size="sm" variant="primary" icon="play" wire:click="launchNow" wire:loading.attr="disabled" :disabled="(bool) $this->activeRun || ! $project->backup_at" data-launch-now>{{ __('Run now') }}</flux:button>
        @endif
    </div>

    @if ($launch)
        <div class="space-y-2 border-t border-zinc-200 pt-3 dark:border-zinc-700" data-last-launch="{{ $launch->isActive() ? 'running' : (data_get($launch->report, 'launch.ok') ? 'ok' : 'error') }}">
            <p class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                @if ($launch->isActive())
                    <flux:icon.loading class="size-4" />
                    {{ __('Kekeli is running your automation…') }}
                @else
                    {{ __('Last run: :date', ['date' => $launch->created_at->timezone(config('kekeli.display_timezone'))->translatedFormat('j F à H \h i')]) }}
                @endif
            </p>
            @if ($launchLines !== [])
                <x-launch-log :lines="$launchLines" />
            @endif
            @if (! $launch->isActive() && filled(data_get($launch->report, 'done')))
                <p @class(['text-sm', 'text-emerald-700 dark:text-emerald-400' => data_get($launch->report, 'launch.ok'), 'kekeli-erreur' => ! data_get($launch->report, 'launch.ok')]) data-launch-summary>{{ data_get($launch->report, 'done') }}</p>
            @endif
        </div>
    @elseif (! $project->backup_at)
        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('The button will be ready as soon as Kekeli has written your automation.') }}</p>
    @endif
</div>
