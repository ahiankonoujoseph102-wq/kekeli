{{--
    La barre de navigation de Kekeli, la même sur toutes les pages connectées, comme Claude, ChatGPT, Lovable ou Manus
    (étude : docs/design-etude.md, partie B) : à gauche sur un ordinateur, dans le menu ☰ sur un téléphone.
    « Nouvelle discussion », « Rechercher », « Projets », « Bibliothèque », puis « Récents » (les discussions) ;
    en bas, le compte (nom, formule, énergie) avec « Réglages » et « Nous contacter ». Rien qui ne fonctionne pas encore.
--}}
<flux:sidebar sticky stashable class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900" data-navigation>
    <div class="flex items-center justify-between">
        <a href="{{ route('projects.index') }}" class="flex items-center gap-2" wire:navigate>
            <x-app-logo />
        </a>
        <flux:sidebar.toggle class="lg:hidden" icon="x-mark" :aria-label="__('Close the menu')" />
    </div>

    <flux:navlist variant="outline">
        <flux:navlist.item icon="pencil-square" :href="route('projects.index')" :current="request()->routeIs('projects.index')" wire:navigate data-nav-new>{{ __('New discussion') }}</flux:navlist.item>
        <flux:modal.trigger name="search">
            {{-- Sur un téléphone, une seule couche à la fois : ouvrir « Rechercher » referme d'abord le menu, comme ChatGPT. --}}
            <flux:navlist.item icon="magnifying-glass" as="button" data-nav-search data-close-menu-first
                x-data
                x-on:click="const menu = $el.closest('[data-flux-sidebar]'); if (menu && menu.hasAttribute('data-flux-sidebar-on-mobile') && ! menu.hasAttribute('data-flux-sidebar-collapsed-mobile')) $dispatch('flux-sidebar-toggle')"
            >{{ __('Search') }}</flux:navlist.item>
        </flux:modal.trigger>
        <flux:navlist.item icon="window" :href="route('projects.all')" :current="request()->routeIs('projects.all')" wire:navigate data-nav-projects>{{ __('Projects') }}</flux:navlist.item>
        <flux:navlist.item icon="photo" :href="route('library')" :current="request()->routeIs('library')" wire:navigate data-nav-library>{{ __('Library') }}</flux:navlist.item>
    </flux:navlist>

    @can('admin')
        <flux:navlist variant="outline">
            <flux:navlist.group :heading="__('Administration')" expandable :expanded="request()->routeIs('admin.*')">
                <flux:navlist.item icon="chart-bar" :href="route('admin.providers')" :current="request()->routeIs('admin.providers')" wire:navigate>{{ __('AI providers') }}</flux:navlist.item>
                <flux:navlist.item icon="rectangle-stack" :href="route('admin.projects')" :current="request()->routeIs('admin.projects')" wire:navigate>{{ __('Projects') }}</flux:navlist.item>
                <flux:navlist.item icon="credit-card" :href="route('admin.payments')" :current="request()->routeIs('admin.payments')" wire:navigate>{{ __('Payments') }}</flux:navlist.item>
                <flux:navlist.item icon="envelope" :href="route('admin.emails')" :current="request()->routeIs('admin.emails')" wire:navigate>{{ __('E-mails') }}</flux:navlist.item>
                <flux:navlist.item icon="banknotes" :href="route('admin.consumption')" :current="request()->routeIs('admin.consumption')" wire:navigate>{{ __('Consumption') }}</flux:navlist.item>
                <flux:navlist.item icon="adjustments-horizontal" :href="route('admin.settings')" :current="request()->routeIs('admin.settings')" wire:navigate>{{ __('Settings (administration)') }}</flux:navlist.item>
            </flux:navlist.group>
        </flux:navlist>
    @endcan

    <livewire:navigation.recents :current="request()->route('project')?->id ?? (is_string(request()->route('project')) ? request()->route('project') : null)" />

    <flux:dropdown position="top" align="start" class="mt-auto">
        {{-- Comme Claude : le nom et la formule ; ici aussi l'énergie qui reste, en une fine barre ambrée. --}}
        @php($energie = app(\App\Agent\Energy::class))
        @php($reste = $energie->percentLeft(auth()->user()))
        <button type="button" class="flex w-full items-center gap-2.5 rounded-lg p-1.5 text-start transition hover:bg-zinc-200/60 dark:hover:bg-zinc-800" data-nav-account>
            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-zinc-200 text-xs font-semibold text-zinc-800 dark:bg-zinc-700 dark:text-white">{{ auth()->user()->initials() }}</span>
            <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-medium text-zinc-900 dark:text-white">{{ auth()->user()->name }}</span>
                <span class="flex items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400" data-nav-plan>
                    <span class="truncate">{{ __('Formula :plan', ['plan' => $energie->plan(auth()->user())['name']]) }}</span>
                    <span class="h-1 w-8 shrink-0 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700" role="img" aria-label="{{ __('Energy left: :percent %', ['percent' => $reste]) }}" data-nav-energy="{{ $reste }}">
                        <span class="block h-full rounded-full bg-amber-500" style="width: {{ $reste }}%"></span>
                    </span>
                </span>
            </span>
            <flux:icon.chevron-up-down variant="micro" class="shrink-0 text-zinc-400" />
        </button>
        <x-user-menu />
    </flux:dropdown>
</flux:sidebar>

<livewire:navigation.search />

<x-contact-modal />
