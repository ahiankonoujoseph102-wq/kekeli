{{--
    Pages d'erreur et de maintenance de Kekeli, au même design que le reste : le soleil, une phrase simple, un seul
    bouton, et comment nous joindre (App\Support\Contact, qui revient aux valeurs de départ sans base de données).
--}}
<!DOCTYPE html>
<html lang="fr">
    <head>
        @include('partials.head', ['title' => $title])
    </head>
    <body class="kekeli-fond min-h-svh antialiased">
        <main class="mx-auto flex min-h-svh w-full max-w-md flex-col items-center justify-center gap-6 px-4 py-12 text-center" data-error-page="{{ $code }}">
            <a href="{{ url('/') }}" class="kekeli-apparition" aria-label="{{ __('Back to the home page') }}">
                <x-app-logo-icon class="size-12 text-amber-500" />
            </a>
            <div class="kekeli-apparition space-y-3">
                <p class="kekeli-surtitre">{{ __('Error :code', ['code' => $code]) }}</p>
                <h1 class="kekeli-titre text-4xl leading-tight text-zinc-900 dark:text-white">{{ $title }}</h1>
                <p class="text-base text-zinc-600 dark:text-zinc-300">{{ $message }}</p>
            </div>
            <div class="kekeli-apparition flex flex-wrap items-center justify-center gap-2">
                @if (($action ?? 'home') === 'reload')
                    <flux:button variant="primary" onclick="window.location.reload()">{{ __('Try again') }}</flux:button>
                @else
                    <flux:button variant="primary" :href="url('/')">{{ __('Back to the home page') }}</flux:button>
                @endif
            </div>
            <x-contact class="kekeli-apparition mt-4 flex w-full flex-col items-center border-t border-zinc-200 pt-6 dark:border-zinc-700 [&_ul]:justify-center" :title="__('Need help? Contact us')" />
        </main>
    </body>
</html>
