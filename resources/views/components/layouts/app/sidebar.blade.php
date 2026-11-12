<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="kekeli-fond min-h-screen antialiased">
        <x-navigation />

        {{-- Sur un téléphone : le menu ☰, le logo et l'initiale du compte, sur toutes les pages connectées. --}}
        <flux:header class="border-b border-zinc-200 lg:hidden dark:border-zinc-700" data-mobile-bar>
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" :aria-label="__('Open the menu')" data-nav-toggle />

            <a href="{{ route('projects.index') }}" class="ms-2 flex items-center gap-2" wire:navigate>
                <x-app-logo />
            </a>

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" data-mobile-account />
                <x-user-menu />
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @fluxScripts
    </body>
</html>
