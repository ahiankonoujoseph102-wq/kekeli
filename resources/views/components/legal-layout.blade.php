@props(['title', 'updated'])
{{-- Gabarit des pages juridiques. Ces textes sont écrits directement en français ; la version anglaise sera un
     fichier à part (par exemple legal/en/conditions.blade.php) quand l'anglais sera ajouté. --}}
<!DOCTYPE html>
<html lang="fr">
    <head>
        @include('partials.head', ['title' => $title])
    </head>
    <body class="kekeli-fond min-h-screen antialiased">
        <header class="mx-auto flex w-full max-w-3xl items-center justify-between gap-4 px-4 py-4">
            <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold">
                <x-app-logo />
            </a>
        </header>

        <main class="mx-auto w-full max-w-3xl px-4 pb-16 pt-4">
            <article class="legal space-y-4 text-[15px] leading-relaxed text-zinc-700 dark:text-zinc-300">
                <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ $title }}</h1>
                <p class="text-sm text-zinc-500">Dernière mise à jour : {{ $updated }}.</p>
                {{ $slot }}
            </article>
        </main>

        <footer class="border-t border-zinc-200 px-4 py-6 text-center text-sm text-zinc-500 dark:border-zinc-800">
            <a href="{{ route('legal.terms') }}" class="underline">Conditions d'utilisation</a>
            ·
            <a href="{{ route('legal.privacy') }}" class="underline">Politique de confidentialité</a>
        </footer>
    </body>
</html>
