{{--
    Réglages rangés par onglets, comme Claude ou ChatGPT : Profil, Mot de passe, Apparence, Formule, Mémoire, et Connecteurs
    seulement quand au moins un connecteur fonctionne vraiment (app/Support/Connectors.php).
--}}
@php
    $onglets = [
        ['settings.profile', __('Profile'), 'user'],
        ['settings.appearance', __('Appearance'), 'swatch'],
        ['settings.plan', __('Formula'), 'sun'],
        ['settings.memory', __('Memory'), 'bookmark'],
    ];
    // « Mot de passe » seulement pour les comptes qui en ont un (les autres se connectent par code ou par Google).
    if (filled(auth()->user()?->password)) {
        array_splice($onglets, 1, 0, [['settings.password', __('Password'), 'key']]);
    }
    if (app(\App\Support\Connectors::class)->any()) {
        $onglets[] = ['settings.connectors', __('Connectors'), 'puzzle-piece'];
    }
    // Après une action (par exemple « Modifier »), la page se met à jour sans recharger : l'onglet actif est retrouvé
    // grâce à l'adresse d'origine de la page.
    $actif = fn (string $route) => request()->routeIs($route)
        || (\Livewire\Livewire::isLivewireRequest() && rtrim(strtok((string) \Livewire\Livewire::originalUrl(), '?'), '/') === rtrim(route($route), '/'));
@endphp
<div class="flex w-full flex-col">
    {{-- Sur un téléphone, deux colonnes de pastilles : tous les onglets restent visibles, rien n'est coupé au bord. --}}
    <nav class="mb-6 grid grid-cols-2 gap-1.5 sm:flex sm:flex-wrap" aria-label="{{ __('Settings') }}" data-settings-tabs>
        @foreach ($onglets as [$route, $label, $icon])
            <a href="{{ route($route) }}" wire:navigate data-settings-tab="{{ $route }}"
                @class([
                    'flex min-w-0 items-center justify-center gap-1.5 rounded-full border px-3 py-2 text-sm font-medium transition sm:justify-start',
                    'border-zinc-900 bg-zinc-900 text-white dark:border-zinc-100 dark:bg-zinc-100 dark:text-zinc-900' => $actif($route),
                    'border-zinc-200 text-zinc-600 hover:border-zinc-300 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:text-white' => ! $actif($route),
                ])
                @if ($actif($route)) aria-current="page" @endif>
                <flux:icon :name="$icon" variant="micro" class="shrink-0" />
                <span class="truncate">{{ $label }}</span>
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
