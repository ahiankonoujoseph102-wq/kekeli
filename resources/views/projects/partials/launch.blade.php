{{--
    Automatisation : pas d'aperçu. « Lancer maintenant » la fait tourner une fois dans l'ordinateur du projet, avec les
    Variables secrètes du client, et montre son journal lisible. Ensuite, elle tourne seule sur le dépôt GitHub du client.
--}}
@php($launch = $this->lastLaunch)
@php($launchLines = (array) data_get($launch?->report, 'launch.lines', []))
@php($trigger = (array) ($project->automation_trigger ?? []))
@php($trial = $trigger['trial'] ?? null)
<div class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" data-launch-panel>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="min-w-0">
            <p class="font-medium text-zinc-900 dark:text-white">{{ __('Try your automation') }}</p>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('It runs once now, with your Secret variables. Then it runs by itself on your GitHub repository.') }}</p>
            @if (filled($trigger['kind'] ?? null))
                <p class="mt-1 text-sm text-zinc-700 dark:text-zinc-200" data-trigger="{{ $trigger['kind'] }}"><span class="font-medium">{{ __('Trigger:') }}</span> {{ \App\Automations\AutomationTrigger::describe($trigger) }}</p>
            @endif
        </div>
        @if ($this->isOwner)
            <flux:button size="sm" variant="primary" icon="play" wire:click="launchNow" wire:loading.attr="disabled" :disabled="(bool) $this->activeRun || ! $project->backup_at" data-launch-now>{{ __('Run now') }}</flux:button>
        @endif
    </div>

    @if (is_array($trial))
        {{-- « Lancer maintenant » simule l'événement avec ces données inventées. --}}
        <details class="rounded-lg bg-zinc-50 px-3 py-2 text-sm dark:bg-zinc-800/60" data-trial-event>
            <summary class="cursor-pointer text-zinc-700 dark:text-zinc-200">{{ __('« Run now » simulates the event with test data') }}</summary>
            <dl class="mt-2 grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-1">
                @foreach ((array) ($trial['donnees'] ?? []) as $field => $value)
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ $field }}</dt>
                    <dd class="min-w-0 break-words text-zinc-800 dark:text-zinc-100">{{ $value }}</dd>
                @endforeach
            </dl>
        </details>
    @endif

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

    @if (($trigger['kind'] ?? null) === \App\Automations\AutomationTrigger::EVENT)
        {{-- Brancher l'événement : le formulaire d'un site fait avec Kekeli, avec l'accord du client. --}}
        <div class="space-y-2 border-t border-zinc-200 pt-3 dark:border-zinc-700" data-wire-section>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="font-medium text-zinc-900 dark:text-white">{{ __('Connect the event') }}</p>
                @if ($this->isOwner && $this->wirableSites->isNotEmpty())
                    <flux:modal.trigger name="wire-form">
                        <flux:button size="sm" icon="link" data-wire-open>{{ __('Connect a form') }}</flux:button>
                    </flux:modal.trigger>
                @endif
            </div>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Your entry point receives the event on your Cloudflare account, then starts the automation on your GitHub. The steps are in the README.') }}</p>
            @foreach ((array) ($trigger['branched'] ?? []) as $branched)
                <p class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-200" data-wired-site><flux:icon.check class="size-4 text-emerald-600" /> {{ __('Form of « :name » connected', ['name' => $branched['name'] ?? '']) }}</p>
            @endforeach
        </div>
    @endif

    @if ($this->triggerLog !== [])
        <div class="space-y-2 border-t border-zinc-200 pt-3 dark:border-zinc-700" data-trigger-log>
            <p class="font-medium text-zinc-900 dark:text-white">{{ __('Triggers') }}</p>
            <ol class="space-y-1.5 text-sm">
                @foreach ($this->triggerLog as $entry)
                    <li class="grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-x-2" data-trigger-entry="{{ $entry['origin'] }}">
                        <span @class(['size-2 rounded-full', 'bg-emerald-500' => $entry['ok'] === true, 'bg-red-500' => $entry['ok'] === false, 'bg-amber-400' => $entry['ok'] === null]) aria-hidden="true"></span>
                        <span class="min-w-0 truncate text-zinc-800 dark:text-zinc-100">
                            {{ match ($entry['origin']) { 'evenement' => __('Event received'), 'heure' => __('Planned time'), 'main' => __('Started by hand'), 'kekeli' => __('Trial in Kekeli'), default => __('Other launch') } }}
                            <span class="text-zinc-500 dark:text-zinc-400">· {{ $entry['at']->timezone(config('kekeli.display_timezone'))->translatedFormat('j F à H \h i') }}</span>
                        </span>
                        @if ($entry['url'])
                            <a href="{{ $entry['url'] }}" target="_blank" rel="noopener" class="text-zinc-500 underline underline-offset-2 dark:text-zinc-400">{{ $entry['ok'] === null ? __('In progress') : ($entry['ok'] ? __('Succeeded') : __('Failed')) }}</a>
                        @else
                            <span class="text-zinc-500 dark:text-zinc-400">{{ $entry['ok'] === null ? __('In progress') : ($entry['ok'] ? __('Succeeded') : __('Failed')) }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </div>
    @endif
</div>

@if (($trigger['kind'] ?? null) === \App\Automations\AutomationTrigger::EVENT && $this->isOwner)
    <flux:modal name="wire-form" class="w-full max-w-lg">
        <form wire:submit="wireForm" class="space-y-4" data-wire-form>
            <div>
                <flux:heading size="lg">{{ __('Connect a form') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Kekeli changes the form of your site so that each message also starts this automation. Nothing else changes.') }}</flux:text>
            </div>
            <flux:select wire:model="wireSite" :label="__('Site')" :placeholder="__('Choose a site')">
                @foreach ($this->wirableSites as $site)
                    <flux:select.option value="{{ $site->id }}">{{ $site->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="entryUrl" :label="__('Address of your entry point')" placeholder="https://…workers.dev" inputmode="url" autocomplete="off" />
            <flux:text class="text-sm">{{ __('You find it at the end of the « Point d’entrée » launch, in the Actions tab of your GitHub repository.') }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" data-wire-submit>{{ __('Plug in') }}</flux:button>
            </div>
        </form>
    </flux:modal>
@endif
