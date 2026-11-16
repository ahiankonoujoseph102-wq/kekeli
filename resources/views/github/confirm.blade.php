{{--
    Retour de GitHub que Kekeli n'a pas pu vérifier tout seul (session perdue sur le téléphone, retour d'installation
    sans « state ») : le client confirme d'un geste, dans un formulaire protégé, avant que Kekeli relie le compte.
--}}
<x-layouts.app :title="__('Connect GitHub')">
    <div class="mx-auto flex max-w-3xl flex-col items-start gap-4 py-10" data-github-confirm>
        <flux:icon.github class="size-8" />
        <h1 class="text-xl font-medium">{{ __('Finish connecting GitHub') }}</h1>
        <p class="text-zinc-600 dark:text-zinc-300">{{ __('You have just authorised Kekeli on GitHub. Confirm to link this GitHub account to your Kekeli account.') }}</p>
        <form method="POST" action="{{ route('github.confirm') }}">
            @csrf
            <flux:button type="submit" variant="primary" icon="link" data-github-confirm-button>{{ __('Link my GitHub account') }}</flux:button>
        </form>
        <a href="{{ route('projects.index') }}" class="kekeli-lien text-sm">{{ __('Cancel') }}</a>
    </div>
</x-layouts.app>
