{{--
    Réglages rangés par onglets, comme Claude ou ChatGPT : Profil, Mot de passe, Apparence, Formule, Mémoire, et Connecteurs
    seulement quand au moins un connecteur fonctionne vraiment (app/Support/Connectors.php).
--}}
@php
    $onglets = [
        ['settings.profile', __('Profile')],
        ['settings.appearance', __('Appearance')],
        ['settings.plan', __('Formula')],
        ['settings.memory', __('Memory')],
    ];
    // « Mot de passe » seulement pour les comptes qui en ont un (les autres se connectent par code ou par Google).
    if (filled(auth()->user()?->password)) {
        array_splice($onglets, 1, 0, [['settings.password', __('Password')]]);
    }
    if (app(\App\Support\Connectors::class)->any()) {
        $onglets[] = ['settings.connectors', __('Connectors')];
    }
    // Après une action (par exemple « Modifier »), la page se met à jour sans recharger : l'onglet actif est retrouvé
    // grâce à l'adresse d'origine de la page.
    $actif = fn (string $route) => request()->routeIs($route)
        || (\Livewire\Livewire::isLivewireRequest() && rtrim(strtok((string) \Livewire\Livewire::originalUrl(), '?'), '/') === rtrim(route($route), '/'));
@endphp
<div class="mx-auto flex w-full max-w-3xl flex-col">
    {{-- Une seule ligne d'onglets soulignés, comme Claude et ChatGPT (audit unslop, décision 2A) : sur un téléphone, elle
         défile sur le côté, avec un léger fondu du côté où il reste des onglets. --}}
    <nav class="kekeli-defile -mx-1 mb-6 flex gap-1 overflow-x-auto border-b border-zinc-200 px-1 [scrollbar-width:none] dark:border-zinc-700 [&::-webkit-scrollbar]:hidden"
        x-data="{ debut: false, suite: false, voir() { this.debut = $el.scrollLeft > 4; this.suite = $el.scrollLeft + $el.clientWidth < $el.scrollWidth - 4 } }"
        x-init="$nextTick(() => { $el.querySelector('[aria-current]')?.scrollIntoView({ block: 'nearest', inline: 'center' }); voir() })" x-on:scroll.passive="voir()" x-on:resize.window="voir()" x-bind:data-debut="debut" x-bind:data-suite="suite"
        aria-label="{{ __('Settings') }}" data-settings-tabs>
        @foreach ($onglets as [$route, $label])
            <a href="{{ route($route) }}" wire:navigate data-settings-tab="{{ $route }}"
                @class([
                    '-mb-px shrink-0 whitespace-nowrap border-b-2 px-3 py-2.5 text-sm font-medium transition-colors',
                    'border-zinc-900 text-zinc-900 dark:border-zinc-100 dark:text-white' => $actif($route),
                    'border-transparent text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white' => ! $actif($route),
                ])
                @if ($actif($route)) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    <div class="w-full max-w-2xl">
        @if (filled($heading ?? null))
            <flux:heading size="lg">{{ $heading }}</flux:heading>
        @endif
        @if (filled($subheading ?? null))
            <flux:text class="mt-1">{{ $subheading }}</flux:text>
        @endif

        <div class="kekeli-apparition mt-5 w-full">
            {{ $slot }}
        </div>
    </div>
</div>
