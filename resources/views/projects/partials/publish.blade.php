{{-- Fenêtre « Mettre en ligne » : les explications s'affichent seulement après avoir touché le bouton (comme « Publish » chez Lovable). --}}
<flux:modal name="publish" class="w-full max-w-lg">
    <div class="space-y-5" data-publish-sheet>
        @if ($project->isRepository())
            <flux:heading size="lg">GitHub</flux:heading>
            @php($state = (array) $project->repository_state)
            <div class="space-y-3 text-sm" data-repository-card>
                <div class="flex flex-wrap items-center gap-2">
                    <flux:icon.code-bracket-square class="size-5 text-zinc-500" />
                    <a href="https://github.com/{{ $project->repository }}" target="_blank" rel="noopener" class="font-medium underline">{{ $project->repository }}</a>
                    @if (! $project->canWriteRepository())
                        <flux:badge size="sm" color="zinc" data-read-only>{{ __('read only') }}</flux:badge>
                    @endif
                </div>
                <p class="text-zinc-600 dark:text-zinc-300">
                    {{ __('Kekeli works on the branch :work, made from :base.', ['work' => $project->work_branch, 'base' => $project->repository_base_branch]) }}
                    @if (! empty($state['detection']['nom']))
                        {{ __('Project recognized: :kind.', ['kind' => $state['detection']['nom']]) }}
                    @endif
                </p>

                @if (! empty($state['fichiers']))
                    <details class="rounded-lg bg-zinc-50 p-2 dark:bg-zinc-800" data-changed-files>
                        <summary class="cursor-pointer font-medium">{{ trans_choice(':count file changed by Kekeli|:count files changed by Kekeli', $state['nombre_fichiers'] ?? count($state['fichiers']), ['count' => $state['nombre_fichiers'] ?? count($state['fichiers'])]) }}</summary>
                        <ul class="mt-2 space-y-0.5 font-mono text-xs">
                            @foreach (array_slice($state['fichiers'], 0, 40) as $file)
                                <li wire:key="fichier-{{ $loop->index }}" class="break-all">{{ ['ajoute' => '+', 'supprime' => '−', 'renomme' => '→'][$file['etat']] ?? '~' }} {{ $file['chemin'] }}</li>
                            @endforeach
                        </ul>
                    </details>
                @endif

                @if ($project->pr_url)
                    <div class="flex flex-wrap items-center gap-2" data-pull-request="{{ $project->pr_status }}">
                        <a href="{{ $project->pr_url }}" target="_blank" rel="noopener" class="font-medium text-amber-700 underline dark:text-amber-400">{{ __('Pull request number :number', ['number' => $project->pr_number]) }}</a>
                        @if ($project->pr_status === 'merged')
                            <flux:badge size="sm" color="purple">{{ __('Merged') }}</flux:badge>
                        @else
                            @switch($project->ci_status)
                                @case(\App\Models\Project::CI_PENDING)
                                    <flux:badge size="sm" color="sky" icon="clock" data-ci="pending">{{ __('Automatic checks in progress') }}</flux:badge>
                                    @break
                                @case(\App\Models\Project::CI_SUCCESS)
                                    <flux:badge size="sm" color="green" icon="check" data-ci="success">{{ __('Automatic checks green') }}</flux:badge>
                                    @break
                                @case(\App\Models\Project::CI_FAILURE)
                                    <flux:badge size="sm" color="orange" icon="x-mark" data-ci="failure">{{ __('Automatic checks failed') }}</flux:badge>
                                    @break
                                @case(\App\Models\Project::CI_NONE)
                                    <flux:badge size="sm" color="zinc" data-ci="none">{{ __('No automatic check') }}</flux:badge>
                                    @break
                            @endswitch
                        @endif
                    </div>
                    @if ($project->ci_status === \App\Models\Project::CI_FAILURE && $project->ci_summary)
                        <details class="text-xs" data-ci-summary>
                            <summary class="cursor-pointer select-none text-zinc-600 dark:text-zinc-300">{{ __('Details of the automatic checks') }}</summary>
                            <p class="mt-1 whitespace-pre-line text-zinc-500">{{ \Illuminate\Support\Str::limit($project->ci_summary, 400) }}</p>
                        </details>
                    @endif
                @endif

                @if ($project->push_status === \App\Models\Project::PUSH_IN_PROGRESS)
                    <div class="flex items-center gap-2 font-medium text-sky-700 dark:text-sky-300" wire:poll.4s>
                        <flux:icon.loading class="size-4" /> {{ __('Sending to GitHub…') }}
                    </div>
                @else
                    @if ($project->push_status === \App\Models\Project::PUSH_FAILED)
                        <p class="kekeli-erreur" data-push-failed>{{ __('The work could not be sent to GitHub this time. Please try again in a moment.') }}</p>
                    @elseif ($project->push_status === \App\Models\Project::PUSH_NOTHING)
                        <p class="text-zinc-600 dark:text-zinc-300">{{ __('Nothing new to send: GitHub already has all of Kekeli\'s work.') }}</p>
                    @endif
                    @if ($project->canWriteRepository() && ! empty($state['fichiers']) && $project->pr_status !== 'merged')
                        <flux:button size="sm" :variant="$project->pr_url ? 'subtle' : 'primary'" icon="arrow-up-tray" wire:click="pushRepository" :disabled="(bool) $this->activeRun" data-push-repository>
                            {{ $project->pr_url ? __('Send the new changes') : __('Send to GitHub and open a pull request') }}
                        </flux:button>
                    @endif
                    @if ($project->pr_url && $project->pr_status === 'open' && $project->canWriteRepository())
                        <flux:button size="sm" variant="ghost" icon="arrows-pointing-in" wire:click="mergePullRequest" wire:confirm="{{ __('Merge this pull request into :branch? This changes the main branch of your repository.', ['branch' => $project->repository_base_branch]) }}" :disabled="$project->ci_status === \App\Models\Project::CI_PENDING || $project->ci_status === \App\Models\Project::CI_FAILURE || (bool) $this->activeRun" data-merge>
                            {{ __('Merge the pull request') }}
                        </flux:button>
                    @endif
                    @if (! $project->canWriteRepository())
                        <p class="text-zinc-500" data-read-only-note>{{ __('Read only: Kekeli can work on this repository in its computer, but cannot send anything to GitHub.') }}</p>
                    @endif
                @endif
                <flux:error name="repository" />
            </div>
        @elseif ($project->isWebApp())
            {{--
                Site avec base de données : Kekeli ne l'héberge pas pour l'instant. Aucune promesse de publication : une
                phrase honnête, puis le code à emporter (comme « Export » chez Bolt.new et Lovable).
            --}}
            @if ($project->isAgent())
                <flux:heading size="lg">{{ __('The code of your agent') }}</flux:heading>
                <p class="text-sm text-zinc-600 dark:text-zinc-300" data-agent-hosting>{{ __('Your agent belongs to you: host it where you want, with the key of your own model. The README explains everything, step by step.') }}</p>
                {{-- La bulle à copier sur un site, comme Dify ou Intercom : une seule ligne avant </body>. --}}
                <div class="space-y-1.5" data-agent-bubble x-data="{ copie: false }">
                    <p class="text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ __('The bubble for your website') }}</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Once your agent is online, paste this line just before </body> in the pages of your site, with the address of your agent.') }}</p>
                    <div class="flex items-start gap-2 rounded-lg bg-zinc-100 p-2 dark:bg-zinc-800">
                        <code class="min-w-0 flex-1 break-all text-xs text-zinc-800 dark:text-zinc-100" x-ref="ligne">&lt;script src="https://{{ __('address-of-your-agent') }}/bulle.js" defer&gt;&lt;/script&gt;</code>
                        <button type="button" class="shrink-0 rounded-md p-1 text-zinc-500 hover:bg-zinc-200 hover:text-zinc-800 dark:hover:bg-zinc-700 dark:hover:text-zinc-100" x-on:click="navigator.clipboard?.writeText($refs.ligne.textContent).then(() => { copie = true; setTimeout(() => copie = false, 2000) })" :aria-label="__('Copy')" data-copy-bubble>
                            <flux:icon.square-2-stack variant="micro" x-show="! copie" />
                            <flux:icon.check variant="micro" class="text-emerald-500" x-show="copie" x-cloak />
                        </button>
                    </div>
                </div>
            @else
            <flux:heading size="lg">{{ __('The code of your site') }}</flux:heading>
            <p class="text-sm text-zinc-600 dark:text-zinc-300" data-webapp-hosting>{{ __('This site needs a host with a database. Its code belongs to you: you can entrust it to the host of your choice.') }}</p>
            @endif
            @if ($project->backup_at)
                <div class="flex flex-col gap-2" data-take-code>
                    <flux:button variant="primary" icon="arrow-down-tray" class="w-full" :href="route('projects.code.zip', $project)" data-zip-download>{{ __('Download the code') }}</flux:button>
                    @include('projects.partials.github-link')
                </div>
            @else
                <p class="text-sm text-zinc-500">{{ __('The code will be ready as soon as Kekeli has built your site.') }}</p>
            @endif
        @else
            <flux:heading size="lg">{{ __('Put it online') }}</flux:heading>
            <div class="space-y-2" data-publication="{{ $project->publish_status ?? 'none' }}">
                @if ($project->publish_status === \App\Models\Project::PUBLISH_IN_PROGRESS)
                    <div class="flex items-center gap-2 text-sm font-medium text-sky-700 dark:text-sky-300">
                        <flux:icon.loading class="size-4" />
                        {{ __('Publishing your site…') }}
                    </div>
                @else
                    @if ($project->published_url)
                        <div class="rounded-lg bg-zinc-50 px-3 py-2 text-sm dark:bg-zinc-800">
                            <a href="{{ $project->published_url }}" target="_blank" rel="noopener" class="flex items-center gap-2 break-all font-medium text-zinc-900 hover:underline dark:text-white" data-published-url>
                                <span class="size-2 shrink-0 rounded-full bg-emerald-500"></span>{{ \Illuminate\Support\Str::after($project->published_url, '://') }}
                            </a>
                            <p class="mt-0.5 pl-4 text-xs text-zinc-500">{{ __('Online since :date', ['date' => $project->published_at?->timezone(config('kekeli.display_timezone'))->format('d/m/Y')]) }}</p>
                        </div>
                    @endif
                    @if ($project->publish_status === \App\Models\Project::PUBLISH_FAILED)
                        <p class="kekeli-erreur text-sm" data-publish-failed>{{ __('Your site could not be published this time. Please try again in a moment.') }}</p>
                    @endif
                    @if ($project->backup_at)
                        <flux:button variant="primary" icon="rocket-launch" class="w-full" wire:click="publish" :disabled="(bool) $this->activeRun" data-publish>
                            {{ $project->published_url ? __('Update') : __('Publish my site') }}
                        </flux:button>
                    @else
                        <p class="text-sm text-zinc-500">{{ __('Your site can be put online as soon as Kekeli has built it.') }}</p>
                    @endif
                    <flux:error name="publish" />
                @endif
            </div>

            <div class="space-y-2 border-t border-zinc-200 pt-4 dark:border-zinc-700" data-domain="{{ $project->custom_domain_status ?? 'none' }}">
                <div class="flex items-center justify-between gap-2">
                    <flux:heading size="sm">{{ __('My own domain name') }}</flux:heading>
                    @if (! $this->paidPlan)
                        <a href="{{ route('plans') }}" wire:navigate class="text-sm font-medium text-amber-700 hover:underline dark:text-amber-400" data-domain-upgrade>{{ __('Paid formulas') }}</a>
                    @endif
                </div>
                @if (! $this->paidPlan)
                @elseif (! $project->published_url)
                    <flux:text class="text-sm">{{ __('Publish your site first, then connect your own address here.') }}</flux:text>
                @elseif (! $project->custom_domain)
                    <flux:text class="text-sm">{{ __('Buy your domain name from the seller of your choice, then write the address with « www » here.') }}</flux:text>
                    <form wire:submit="addDomain" class="flex items-start gap-2">
                        <div class="flex-1">
                            <flux:input wire:model="newDomain" :placeholder="__('for example www.my-restaurant.com')" :aria-label="__('My domain name')" autocapitalize="none" data-domain-input />
                            <flux:error name="newDomain" />
                        </div>
                        <flux:button type="submit" variant="primary">{{ __('Connect') }}</flux:button>
                    </form>
                @else
                    <p class="text-sm font-medium break-all" data-custom-domain>{{ $project->custom_domain }}</p>
                    @if ($project->custom_domain_status === 'active')
                        <p class="flex items-center gap-2 text-sm font-medium text-emerald-700 dark:text-emerald-300" data-domain-active>
                            <flux:icon.check-circle variant="micro" /> {{ __('Your site is online at your address.') }}
                        </p>
                    @else
                        <flux:text class="text-sm">{{ __('Last step: at the seller of your domain name, in the DNS settings, add this record:') }}</flux:text>
                        <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 rounded-lg bg-zinc-50 p-3 font-mono text-sm dark:bg-zinc-800" data-domain-record>
                            <dt class="text-zinc-500">{{ __('Type') }}</dt><dd>CNAME</dd>
                            <dt class="text-zinc-500">{{ __('Name') }}</dt><dd class="break-all">{{ \Illuminate\Support\Str::before($project->custom_domain, '.') }}</dd>
                            <dt class="text-zinc-500">{{ __('Value') }}</dt><dd class="break-all">{{ $project->pagesName() }}.pages.dev</dd>
                        </dl>
                        <flux:text class="text-xs">{{ __('It can take from a few minutes to a few hours. For the address without « www », ask the seller to redirect it to the address with « www ».') }}</flux:text>
                        @if ($project->custom_domain_message)
                            <flux:text class="text-xs" data-domain-message>{{ __('Not ready yet: the record is not found yet.') }}</flux:text>
                        @endif
                        <flux:button size="sm" variant="primary" icon="arrow-path" wire:click="checkDomain" data-domain-check>{{ __('Check now') }}</flux:button>
                    @endif
                    <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="removeDomain" wire:confirm="{{ __('Remove this address from your site?') }}">{{ __('Remove this address') }}</flux:button>
                    <flux:error name="domain" />
                @endif
            </div>

            {{-- Comme Lovable et Bolt.new : le code peut aussi être emporté, pour l'héberger ailleurs. --}}
            @if ($project->backup_at)
                <div class="flex flex-wrap items-center gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700" data-take-code>
                    <flux:button size="sm" variant="filled" icon="arrow-down-tray" :href="route('projects.code.zip', $project)" data-zip-download>{{ __('Download the code') }}</flux:button>
                    @include('projects.partials.github-link')
                </div>
            @endif
        @endif
    </div>
</flux:modal>
