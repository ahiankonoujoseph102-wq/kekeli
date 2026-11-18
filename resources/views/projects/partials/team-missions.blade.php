{{--
    Mode équipe (phase 1, administrateur seulement) : les missions de l'équipe, cochées une à une, avec leurs mesures
    (durée, requêtes) pour comparer avec l'agent unique. Dépliées pendant le travail, repliées ensuite.
--}}
@php($verified = $missions->where('status', 'verifiee')->count())
<details @if ($run->isActive()) open @endif class="group rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700" data-team-missions="{{ $missions->count() }}">
    <summary class="flex cursor-pointer list-none items-center justify-between gap-2 text-zinc-600 select-none dark:text-zinc-300 [&::-webkit-details-marker]:hidden">
        <span class="flex items-center gap-2">
            <flux:icon.user-group variant="micro" />
            <span>{{ __('Team mode · :verified of :count missions verified', ['verified' => $verified, 'count' => $missions->count()]) }}</span>
        </span>
        <flux:icon.chevron-down variant="micro" class="transition-transform group-open:rotate-180" />
    </summary>
    <ol class="mt-2 space-y-1.5">
        @foreach ($missions as $mission)
            <li class="flex items-start gap-2" data-team-mission="{{ $mission->code }}" data-team-mission-status="{{ $mission->status }}">
                @switch($mission->status)
                    @case('verifiee')
                        <flux:icon.check-circle variant="mini" class="mt-0.5 size-4 shrink-0 text-emerald-600" />
                        @break
                    @case('en_cours')
                        <flux:icon.loading class="mt-0.5 size-4 shrink-0 text-zinc-400" />
                        @break
                    @case('echouee')
                    @case('delai')
                        <flux:icon.exclamation-circle variant="mini" class="mt-0.5 size-4 shrink-0 text-red-600" />
                        @break
                    @default
                        <flux:icon.minus-circle variant="mini" class="mt-0.5 size-4 shrink-0 text-zinc-400" />
                @endswitch
                <span class="min-w-0 flex-1">
                    <span class="text-zinc-800 dark:text-zinc-100">{{ $mission->title }}</span>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400"> · {{ $mission->roleLabel() }}@if ($mission->seconds) · {{ \App\Support\Formats::duration($mission->seconds) }}@endif @if ($mission->requests) · {{ trans_choice('{1} :count request|[2,*] :count requests', $mission->requests, ['count' => $mission->requests]) }}@endif</span>
                    @if ($mission->problem)
                        <span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ $mission->problem }}</span>
                    @endif
                </span>
            </li>
        @endforeach
    </ol>
</details>
