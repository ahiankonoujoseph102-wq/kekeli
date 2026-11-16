{{--
    Message après le retour de GitHub, comme Claude Code : « GitHub est connecté : N dépôts accessibles », ou la raison
    d'un échec. Il flotte en haut de la page (discussion, projet, réglages) et se ferme d'un geste.
--}}
@if ($status = session('github'))
    <div x-data="{ ouvert: true }" x-show="ouvert" class="kekeli-apparition fixed inset-x-4 top-4 z-50 mx-auto max-w-md rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm shadow-lg dark:border-zinc-700 dark:bg-zinc-900" role="status" data-github-notice="{{ $status }}">
        <div class="flex items-start gap-3">
            <flux:icon.github class="mt-0.5 size-5 shrink-0" />
            <p class="flex-1">
                @if ($status === 'connected')
                    @php($count = session('github_repositories'))
                    @if ($count === null)
                        {{ __('GitHub is connected (account :login).', ['login' => session('github_login')]) }}
                    @else
                        {{ trans_choice('GitHub is connected: :count repository accessible (account :login).|GitHub is connected: :count repositories accessible (account :login).', $count, ['count' => $count, 'login' => session('github_login')]) }}
                    @endif
                    @if ($count === 0)
                        {{ __('Choose on GitHub the repositories Kekeli may read, in Settings → Connectors.') }}
                    @endif
                @elseif ($status === 'failed')
                    {{ __('GitHub did not finish the connection. Please try again in a moment.') }}
                @else
                    {{ __('The connection to GitHub was not completed. Start it again from Kekeli.') }}
                @endif
            </p>
            <button type="button" class="-m-2 rounded-full p-2 text-zinc-500 transition-colors hover:text-zinc-800 dark:hover:text-zinc-200" x-on:click="ouvert = false" aria-label="{{ __('Close') }}">
                <flux:icon.x-mark class="size-4" />
            </button>
        </div>
    </div>
@endif
