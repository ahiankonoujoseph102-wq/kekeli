{{-- Texte écrit sur l'accueil avant d'avoir un compte : il est gardé, et Kekeli commence dès l'inscription ou la connexion. --}}
@if (filled($pending = session(\App\WebApps\NewSite::PENDING)))
    <div class="kekeli-avis p-3 text-sm" data-pending-site>
        <p class="font-medium text-zinc-900 dark:text-white">{{ __('Your idea is kept') }}</p>
        <p class="mt-1 line-clamp-3 text-zinc-700 dark:text-zinc-300">« {{ $pending }} »</p>
        <p class="mt-1 text-zinc-600 dark:text-zinc-400">{{ __('Kekeli starts building as soon as you are connected.') }}</p>
    </div>
@endif
