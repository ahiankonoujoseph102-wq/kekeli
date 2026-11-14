<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="kekeli-fond kekeli-aube flex min-h-svh flex-col antialiased">
        <header class="mx-auto flex w-full max-w-5xl items-center justify-between gap-4 px-4 py-4">
            <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold">
                <x-app-logo />
            </a>

            <nav class="flex items-center gap-2 text-sm">
                @auth
                    <flux:button :href="route('projects.index')" variant="primary" size="sm">{{ __('My projects') }}</flux:button>
                @else
                    <flux:button :href="route('login')" variant="ghost" size="sm">{{ __('Log in') }}</flux:button>
                    @if (Route::has('register'))
                        <flux:button :href="route('register')" variant="primary" size="sm">{{ __('Sign up') }}</flux:button>
                    @endif
                @endauth
            </nav>
        </header>

        {{-- Comme Bolt.new et Lovable : la question, la zone de saisie et les idées, rien d'autre. --}}
        <main class="mx-auto flex w-full max-w-5xl flex-1 flex-col justify-center px-4 pb-16 pt-10 sm:pt-16">
            <livewire:site-prompt :main="true" />
        </main>

        <footer class="flex flex-col items-center gap-1.5 px-4 py-5 text-center text-xs text-zinc-500" data-site-footer>
            <p>
                <a href="{{ route('legal.terms') }}" class="hover:underline">{{ __('Conditions of use') }}</a>
                <span class="mx-1.5">·</span>
                <a href="{{ route('legal.privacy') }}" class="hover:underline">{{ __('Privacy policy') }}</a>
            </p>
            <p>{{ __('Contact') }} : <x-contact inline short /></p>
        </footer>

        @fluxScripts
    </body>
</html>
