<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="kekeli-fond min-h-screen antialiased">
        {{-- Pages de connexion, comme Claude et ChatGPT : le logo, une seule colonne étroite, beaucoup de vide. --}}
        <div class="flex min-h-svh flex-col items-center justify-center gap-6 px-4 py-10 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-4">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium" wire:navigate>
                    <x-app-logo-icon class="size-10" />
                    <span class="sr-only">{{ config('app.name') }}</span>
                </a>
                <div class="flex flex-col gap-6">
                    {{ $slot }}
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
