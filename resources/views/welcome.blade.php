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

        <main class="mx-auto flex w-full max-w-5xl flex-col gap-16 px-4 pb-16 pt-8">
            <section class="flex flex-col items-center gap-6 text-center">
                <x-app-logo-icon class="size-16 text-amber-500" />

                <h1 class="text-balance text-3xl font-semibold leading-tight sm:text-5xl">
                    {{ __('Kekeli, the AI agent that codes for you') }}
                </h1>

                <p class="max-w-2xl text-balance text-lg text-zinc-600 dark:text-zinc-300">
                    {{ __('Describe your project in simple words. Kekeli builds it, tests it and shows you the result live.') }}
                </p>

                <p class="text-sm text-zinc-500">
                    {{ __('« Kekeli » means « the light » in Ewe.') }}
                </p>

                <div class="flex flex-wrap justify-center gap-3">
                    @auth
                        <flux:button :href="route('projects.index')" variant="primary">{{ __('Open my projects') }}</flux:button>
                    @else
                        <flux:button :href="route('register')" variant="primary">{{ __('Create my free account') }}</flux:button>
                        <flux:button :href="route('login')">{{ __('I already have an account') }}</flux:button>
                    @endauth
                </div>

                <flux:badge color="amber">{{ __('Test version under construction') }}</flux:badge>
            </section>

            <section class="grid gap-4 sm:grid-cols-3">
                @foreach ([
                    ['icon' => 'chat-bubble-left-right', 'title' => __('1. Describe'), 'text' => __('Explain what you want: a website for your business, your school, your association.')],
                    ['icon' => 'wrench-screwdriver', 'title' => __('2. Kekeli builds'), 'text' => __('The agent writes the code, tests it and corrects its own mistakes.')],
                    ['icon' => 'eye', 'title' => __('3. You see it live'), 'text' => __('Follow the construction on your phone and publish in one click.')],
                ] as $step)
                    <div class="flex flex-col gap-2 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                        <flux:icon :name="$step['icon']" class="size-6 text-amber-500" />
                        <h2 class="font-semibold">{{ $step['title'] }}</h2>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $step['text'] }}</p>
                    </div>
                @endforeach
            </section>
        </main>

        <footer class="border-t border-zinc-200 px-4 py-6 text-center text-sm text-zinc-500 dark:border-zinc-800">
            {{ __('Kekeli is a project of Lomé IA School, Lomé (Togo).') }}
        </footer>

        @fluxScripts
    </body>
</html>
