<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="kekeli-fond min-h-screen antialiased">
        <flux:sidebar sticky stashable class="border-r border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('projects.index') }}" class="mr-5 flex items-center space-x-2" wire:navigate>
                <x-app-logo />
            </a>

            <flux:navlist variant="outline">
                <flux:navlist.item icon="folder" :href="route('projects.index')" :current="request()->routeIs('projects.*')" wire:navigate>{{ __('My projects') }}</flux:navlist.item>
                <flux:navlist.item icon="sun" :href="route('plans')" :current="request()->routeIs('plans')" wire:navigate>{{ __('Formulas') }}</flux:navlist.item>
                @can('admin')
                    <flux:navlist.item icon="chart-bar" :href="route('admin.providers')" :current="request()->routeIs('admin.providers')" wire:navigate>{{ __('AI providers') }}</flux:navlist.item>
                    <flux:navlist.item icon="rectangle-stack" :href="route('admin.projects')" :current="request()->routeIs('admin.projects')" wire:navigate>{{ __('Projects') }}</flux:navlist.item>
                    <flux:navlist.item icon="credit-card" :href="route('admin.payments')" :current="request()->routeIs('admin.payments')" wire:navigate>{{ __('Payments') }}</flux:navlist.item>
                    <flux:navlist.item icon="banknotes" :href="route('admin.consumption')" :current="request()->routeIs('admin.consumption')" wire:navigate>{{ __('Consumption') }}</flux:navlist.item>
                    <flux:navlist.item icon="adjustments-horizontal" :href="route('admin.settings')" :current="request()->routeIs('admin.settings')" wire:navigate>{{ __('Settings (administration)') }}</flux:navlist.item>
                @endcan
            </flux:navlist>

            <flux:spacer />

            <!-- Desktop User Menu -->
            <flux:dropdown position="bottom" align="start">
                <flux:profile
                    :name="auth()->user()->name"
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevrons-up-down"
                />

                <x-user-menu />
            </flux:dropdown>
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <a href="{{ route('projects.index') }}" class="ml-2 flex items-center space-x-2" wire:navigate>
                <x-app-logo />
            </a>

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <x-user-menu />
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @fluxScripts
    </body>
</html>
