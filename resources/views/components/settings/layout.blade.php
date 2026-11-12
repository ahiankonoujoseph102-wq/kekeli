{{--
    Réglages rangés par onglets, comme Claude ou ChatGPT : Profil, Mot de passe, Apparence, Formule, et Connecteurs
    seulement quand au moins un connecteur fonctionne vraiment (app/Support/Connectors.php).
--}}
@php
    $onglets = [
        ['settings.profile', __('Profile'), 'user'],
        ['settings.password', __('Password'), 'key'],
        ['settings.appearance', __('Appearance'), 'swatch'],
        ['settings.plan', __('Formula'), 'sun'],
    ];
    if (app(\App\Support\Connectors::class)->any()) {
        $onglets[] = ['settings.connectors', __('Connectors'), 'puzzle-piece'];
    }
@endphp
<div class="flex w-full flex-col">
    <nav class="-mx-1 mb-6 flex gap-1 overflow-x-auto border-b border-zinc-200 px-1 dark:border-zinc-700" aria-label="{{ __('Settings') }}" data-settings-tabs>
        @foreach ($onglets as [$route, $label, $icon])
            <a href="{{ route($route) }}" wire:navigate data-settings-tab="{{ $route }}"
                @class([
                    '-mb-px flex shrink-0 items-center gap-1.5 border-b-2 px-3 py-2.5 text-sm font-medium transition',
                    'border-zinc-900 text-zinc-900 dark:border-zinc-100 dark:text-zinc-100' => request()->routeIs($route),
                    'border-transparent text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200' => ! request()->routeIs($route),
                ])
                @if (request()->routeIs($route)) aria-current="page" @endif>
                <flux:icon :name="$icon" variant="micro" />
                {{ $label }}
            </a>
        @endforeach
    </nav>

    <div class="w-full max-w-lg">
        @if (filled($heading ?? null))
            <flux:heading size="lg">{{ $heading }}</flux:heading>
        @endif
        @if (filled($subheading ?? null))
            <flux:text class="mt-1">{{ $subheading }}</flux:text>
        @endif

        <div class="mt-5 w-full">
            {{ $slot }}
        </div>
    </div>
</div>
