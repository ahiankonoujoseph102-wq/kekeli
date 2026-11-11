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
                    @if (Route::has('register'))
                        <flux:button :href="route('register')" variant="primary" size="sm">{{ __('Sign up') }}</flux:button>
                    @endif
                @endauth
            </nav>
        </header>

        <main class="mx-auto flex w-full max-w-5xl flex-col gap-10 px-4 pb-16 pt-10 sm:pt-20">
            <section class="flex flex-col items-center gap-3">
                <livewire:site-prompt :main="true" />
                <p class="max-w-xl text-balance text-center text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Kekeli builds your website, tests it and shows it to you live, on your phone.') }}
                </p>
            </section>

            {{-- Comment ça marche, en une ligne discrète (Bolt.new et Lovable ne mettent presque rien sous la zone). --}}
            <ol class="mx-auto flex max-w-3xl flex-col gap-3 text-sm text-zinc-600 sm:flex-row sm:justify-center sm:gap-8 dark:text-zinc-400" data-how-it-works>
                @foreach ([
                    ['chat-bubble-left-right', __('You share your idea')],
                    ['wrench-screwdriver', __('Kekeli builds and tests it')],
                    ['globe-alt', __('Online in one tap')],
                ] as [$icon, $text])
                    <li class="flex items-center gap-2">
                        <flux:icon :name="$icon" variant="micro" class="text-amber-500" />
                        {{ $text }}
                    </li>
                @endforeach
            </ol>

            <p class="text-center text-sm text-zinc-500">{{ __('« Kekeli » means « the light » in Ewe.') }}</p>
        </main>

        <footer class="border-t border-zinc-200 px-4 py-6 text-center text-sm text-zinc-500 dark:border-zinc-800">
            {{ __('Kekeli is a project of Lomé IA School, Lomé (Togo).') }}
            <div class="mt-2">
                <a href="{{ route('legal.terms') }}" class="underline">{{ __('Conditions of use') }}</a>
                ·
                <a href="{{ route('legal.privacy') }}" class="underline">{{ __('Privacy policy') }}</a>
            </div>
        </footer>

        @fluxScripts
    </body>
</html>
