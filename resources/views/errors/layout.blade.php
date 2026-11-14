{{--
    Pages d'erreur et de maintenance de Kekeli, sobres comme celles de Claude et de ChatGPT : le logo, un titre, une
    phrase courte, un seul bouton « Retour à l'accueil », et le contact réduit en une seule ligne discrète en bas
    (App\Support\Contact, qui revient aux valeurs de départ sans base de données). Pas de « Erreur 404 » au-dessus du
    titre : ni Claude ni ChatGPT ne l'affichent.
--}}
<!DOCTYPE html>
<html lang="fr">
    <head>
        @include('partials.head', ['title' => $title])
    </head>
    <body class="kekeli-fond flex min-h-svh flex-col antialiased">
        <main class="mx-auto flex w-full max-w-md flex-1 flex-col items-center justify-center gap-5 px-4 py-12 text-center" data-error-page="{{ $code }}">
            <a href="{{ url('/') }}" class="kekeli-apparition" aria-label="{{ __('Back to the home page') }}">
                <x-app-logo-icon class="size-11" />
            </a>
            <div class="kekeli-apparition space-y-2">
                <h1 class="kekeli-titre text-[2.25rem] leading-tight text-zinc-900 dark:text-white">{{ $title }}</h1>
                <p class="text-base text-zinc-600 dark:text-zinc-300">{{ $message }}</p>
            </div>
            <flux:button variant="primary" :href="url('/')" class="kekeli-apparition" data-error-home>{{ __('Back to the home page') }}</flux:button>
        </main>
        <footer class="px-4 pb-6 text-center text-xs text-zinc-500" data-error-contact>
            <x-contact inline short />
        </footer>
    </body>
</html>
