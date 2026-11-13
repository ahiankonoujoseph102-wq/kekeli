{{--
    La demande d'accord avant une action qui modifie dans un connecteur, comme Claude : un résumé clair de l'action,
    « Autoriser » et « Refuser », et « Toujours autoriser pour cet outil » (réglable ensuite dans Réglages → Connecteurs).
--}}
@if ($action)
    <div class="rounded-2xl border border-zinc-200 bg-white p-4 text-sm dark:border-zinc-700 dark:bg-zinc-800/60" data-connector-approval="{{ $action->status }}">
        <p class="flex items-center gap-2 font-medium">
            <flux:icon.puzzle-piece variant="micro" class="shrink-0 text-amber-600 dark:text-amber-400" />
            <span>{{ __('Kekeli wants to use :name', ['name' => $action->connector_name]) }}</span>
        </p>
        <p class="mt-2 text-base font-medium text-zinc-900 dark:text-white" data-approval-action>{{ $action->title() }}</p>
        @if (filled(data_get($action->summary, 'description')))
            <p class="mt-0.5 text-zinc-500 dark:text-zinc-400">{{ data_get($action->summary, 'description') }}</p>
        @endif
        @if ($action->details() !== [])
            <dl class="mt-3 space-y-1.5 rounded-xl bg-zinc-50 px-3 py-2.5 dark:bg-zinc-900/60" data-approval-details>
                @foreach ($action->details() as $detail)
                    <div class="flex flex-wrap gap-x-2">
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ $detail['name'] }}</dt>
                        <dd class="min-w-0 break-words text-zinc-800 dark:text-zinc-100">{{ $detail['value'] }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif

        @if ($action->status === \App\Models\ConnectorAction::PENDING && $this->isOwner)
            <label class="mt-3 flex items-center gap-2 text-zinc-600 dark:text-zinc-300">
                <input type="checkbox" wire:model="alwaysAllow" class="size-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-400 dark:border-zinc-600 dark:bg-zinc-800" data-approval-always>
                {{ __('Always allow for this tool') }}
            </label>
            <div class="mt-3 flex flex-wrap gap-2">
                <flux:button size="sm" variant="primary" wire:click="approveConnector({{ $action->id }})" :disabled="(bool) $this->activeRun" data-approval-allow>{{ __('Allow') }}</flux:button>
                <flux:button size="sm" variant="ghost" wire:click="refuseConnector({{ $action->id }})" data-approval-refuse>{{ __('Refuse') }}</flux:button>
            </div>
        @else
            <p class="mt-3 flex items-center gap-1.5 text-zinc-500 dark:text-zinc-400" data-approval-answer>
                <flux:icon :name="$action->status === \App\Models\ConnectorAction::REFUSED ? 'x-circle' : 'check-circle'" variant="micro" />
                {{ $action->status === \App\Models\ConnectorAction::REFUSED ? __('Refused') : ($action->status === \App\Models\ConnectorAction::PENDING ? __('Waiting for your answer') : __('Allowed')) }}
            </p>
        @endif
    </div>
@endif
