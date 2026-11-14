{{--
    La barre de navigation de Kekeli, la même sur toutes les pages connectées, comme Claude, ChatGPT, Lovable ou Manus :
    à gauche sur un ordinateur, dans le menu ☰ sur un téléphone. En haut « Nouveau », « Rechercher » et « Formules » ;
    puis « Récents » (sites et discussions) ; en bas, le menu du compte. Rien qui ne fonctionne pas encore.
--}}
<flux:sidebar sticky stashable class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900" data-navigation>
    <div class="flex items-center justify-between">
        <a href="{{ route('projects.index') }}" class="flex items-center gap-2" wire:navigate>
            <x-app-logo />
        </a>
        <flux:sidebar.toggle class="lg:hidden" icon="x-mark" :aria-label="__('Close the menu')" />
    </div>

    <flux:navlist variant="outline">
        <flux:navlist.item icon="pencil-square" :href="route('projects.index')" :current="request()->routeIs('projects.index')" wire:navigate data-nav-new>{{ __('New') }}</flux:navlist.item>
        <flux:modal.trigger name="search">
            {{-- Sur un téléphone, une seule couche à la fois : ouvrir « Rechercher » referme d'abord le menu, comme ChatGPT. --}}
            <flux:navlist.item icon="magnifying-glass" as="button" data-nav-search data-close-menu-first
                x-data
                x-on:click="const menu = $el.closest('[data-flux-sidebar]'); if (menu && menu.hasAttribute('data-flux-sidebar-on-mobile') && ! menu.hasAttribute('data-flux-sidebar-collapsed-mobile')) $dispatch('flux-sidebar-toggle')"
            >{{ __('Search') }}</flux:navlist.item>
        </flux:modal.trigger>
        <flux:navlist.item icon="fire" :href="route('plans')" :current="request()->routeIs('plans', 'payments.*')" wire:navigate data-nav-plans>{{ __('Formulas') }}</flux:navlist.item>
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
        <flux:profile :name="auth()->user()->name" :initials="auth()->user()->initials()" icon-trailing="chevrons-up-down" data-nav-account />
        <x-user-menu />
    </flux:dropdown>
</flux:sidebar>

<livewire:navigation.search />

<x-contact-modal />
