{{-- Gabarit des pages ouvertes à tous, même sans compte (par exemple la galerie de modèles). --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white text-zinc-900 antialiased dark:bg-zinc-900 dark:text-white">
        <header class="mx-auto flex w-full max-w-5xl items-center justify-between gap-4 px-4 py-4">
            <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold">
                <x-app-logo />
            </a>
            <nav class="flex items-center gap-2 text-sm">
                @auth
                    <flux:button :href="route('projects.index')" variant="primary" size="sm">{{ __('My projects') }}</flux:button>
                @else
                    <flux:button :href="route('login')" variant="ghost" size="sm">{{ __('Log in') }}</flux:button>
                    <flux:button :href="route('register')" variant="primary" size="sm">{{ __('Sign up') }}</flux:button>
                @endauth
            </nav>
        </header>

        <main class="mx-auto w-full max-w-5xl px-4 pb-16 pt-2">
            {{ $slot }}
        </main>

        <footer class="border-t border-zinc-200 px-4 py-6 text-center text-sm text-zinc-500 dark:border-zinc-800">
            <a href="{{ route('legal.terms') }}" class="underline">{{ __('Conditions of use') }}</a>
            ·
            <a href="{{ route('legal.privacy') }}" class="underline">{{ __('Privacy policy') }}</a>
        </footer>

        @fluxScripts
    </body>
</html>
