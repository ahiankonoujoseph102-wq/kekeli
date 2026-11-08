<?php

use App\Jobs\SendAgentRun;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component {
    #[Locked]
    public Project $project;

    public string $content = '';

    /**
     * Mount the component with one of the current user's projects only.
     */
    public function mount(Project $project): void
    {
        abort_unless($project->user()->is(Auth::user()), 404);

        $this->project = $project;
    }

    /**
     * Render the page with the project's name as the title.
     */
    public function rendering($view): void
    {
        $view->title($this->project->name);
    }

    /**
     * The messages of the conversation.
     *
     * @return Collection<int, Message>
     */
    #[Computed]
    public function messages(): Collection
    {
        return $this->project->messages()->get();
    }

    /**
     * The requests sent to the agent, with their steps, by user message.
     *
     * @return Collection<int, AgentRun>
     */
    #[Computed]
    public function runs(): Collection
    {
        return $this->project->agentRuns()->with('steps')->get()->keyBy('message_id');
    }

    /**
     * The request the agent is working on, if any.
     */
    #[Computed]
    public function activeRun(): ?AgentRun
    {
        return $this->runs->first(fn (AgentRun $run) => $run->isActive());
    }

    /**
     * Send the user's message to the agent.
     */
    public function send(): void
    {
        $validated = $this->validate([
            'content' => ['required', 'string', 'max:4000'],
        ]);

        if ($this->project->agentRuns()->whereIn('status', AgentRun::ACTIVE_STATUSES)->exists()) {
            $this->addError('content', __('Kekeli is still working on your previous request. Please wait until it is finished.'));

            return;
        }

        $run = DB::transaction(function () use ($validated) {
            $message = $this->project->messages()->create([
                'role' => Message::ROLE_USER,
                'content' => trim($validated['content']),
            ]);

            $this->project->touch();

            return $this->project->agentRuns()->create(['message_id' => $message->id]);
        });

        $this->reset('content');

        rescue(function () use ($run) {
            SendAgentRun::dispatch($run);
        }, report: true);

        unset($this->messages, $this->runs, $this->activeRun);
    }
}; ?>

<section class="mx-auto flex min-h-[calc(100svh-6rem)] w-full max-w-3xl flex-col gap-4 lg:max-w-7xl" x-data="{ onglet: 'discussion' }" @if ($this->activeRun) wire:poll.2s @endif>
    <div>
        <flux:link :href="route('projects.index')" variant="subtle" class="text-sm" wire:navigate>
            ← {{ __('My projects') }}
        </flux:link>

        <div class="mt-2 flex flex-wrap items-center gap-3">
            <flux:heading size="xl" level="1" class="break-words">{{ $project->name }}</flux:heading>
            @if ($this->activeRun)
                <flux:badge color="sky" size="sm">{{ __('In progress') }}</flux:badge>
            @else
                <flux:badge color="amber" size="sm">{{ __('Draft') }}</flux:badge>
            @endif
        </div>

        @if ($project->description)
            <flux:text class="mt-1">{{ $project->description }}</flux:text>
        @endif
    </div>

    {{-- Sur le téléphone, deux onglets ; sur un grand écran, la discussion et l'aperçu côte à côte. --}}
    <div class="grid grid-cols-2 gap-1 rounded-xl bg-zinc-100 p-1 lg:hidden dark:bg-zinc-800" role="tablist" aria-label="{{ __('Project views') }}" wire:ignore>
        <button type="button" role="tab" id="onglet-discussion" aria-controls="panneau-discussion" data-tab="discussion"
            x-on:click="onglet = 'discussion'" x-bind:aria-selected="onglet === 'discussion'"
            x-bind:class="onglet === 'discussion' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-700 dark:text-white' : 'text-zinc-600 dark:text-zinc-300'"
            class="flex items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm font-medium">
            <flux:icon.chat-bubble-left-right variant="micro" />
            {{ __('Discussion') }}
        </button>
        <button type="button" role="tab" id="onglet-apercu" aria-controls="panneau-apercu" data-tab="apercu"
            x-on:click="onglet = 'apercu'" x-bind:aria-selected="onglet === 'apercu'"
            x-bind:class="onglet === 'apercu' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-700 dark:text-white' : 'text-zinc-600 dark:text-zinc-300'"
            class="flex items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm font-medium">
            <flux:icon.eye variant="micro" />
            {{ __('Preview') }}
        </button>
    </div>

    <div class="flex flex-1 flex-col gap-4 lg:grid lg:grid-cols-2 lg:items-stretch">
    <div id="panneau-discussion" role="tabpanel" aria-labelledby="onglet-discussion" class="flex flex-1 flex-col gap-4" x-bind:class="onglet === 'discussion' ? '' : 'max-lg:hidden'" wire:ignore.self data-panel="discussion">
    <div class="flex flex-1 flex-col gap-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" data-conversation>
        @forelse ($this->messages as $message)
            <div wire:key="message-{{ $message->id }}" @class([
                'max-w-[85%] whitespace-pre-line break-words rounded-2xl px-4 py-2 text-sm',
                'self-end bg-amber-500 text-white' => $message->role === Message::ROLE_USER,
                'self-start bg-zinc-100 text-zinc-900 dark:bg-zinc-700 dark:text-white' => $message->role !== Message::ROLE_USER,
            ])>{{ $message->content }}</div>

            @php($run = $message->role === Message::ROLE_USER ? $this->runs->get($message->id) : null)

            @if ($run)
                <div wire:key="run-{{ $run->id }}" class="self-start w-full max-w-[95%] rounded-xl border border-zinc-200 bg-zinc-50 p-3 text-sm dark:border-zinc-700 dark:bg-zinc-900" data-run-status="{{ $run->status }}">
                    @if ($run->status === AgentRun::STATUS_QUEUED || $run->status === AgentRun::STATUS_RUNNING)
                        <div class="flex items-center gap-2 font-medium text-sky-700 dark:text-sky-300">
                            <flux:icon.loading class="size-4" />
                            {{ $run->status === AgentRun::STATUS_QUEUED ? __('Kekeli is getting ready…') : __('Kekeli is working…') }}
                        </div>
                    @elseif ($run->status === AgentRun::STATUS_WAITING)
                        <div class="font-medium text-amber-700 dark:text-amber-300">
                            {{ __('☀️ Kekeli is in high demand right now. Your request is waiting and will resume automatically around :time.', ['time' => $run->resume_at?->timezone(config('kekeli.display_timezone'))->format('H:i')]) }}
                        </div>
                    @elseif ($run->status === AgentRun::STATUS_FAILED)
                        <div class="font-medium text-red-700 dark:text-red-300">
                            {{ __('Sorry, Kekeli could not finish this request. Please try again in a moment.') }}
                        </div>
                    @endif

                    @if ($run->steps->isNotEmpty())
                        <details @class(['group', 'mt-2' => $run->status !== AgentRun::STATUS_COMPLETED]) @if ($run->isActive()) open @endif>
                            <summary class="cursor-pointer select-none text-zinc-600 dark:text-zinc-300">
                                {{ trans_choice('{1} :count action by Kekeli|[2,*] :count actions by Kekeli', $run->steps->count(), ['count' => $run->steps->count()]) }}
                            </summary>
                            <ol class="mt-2 flex flex-col gap-1.5">
                                @foreach ($run->steps as $step)
                                    <li wire:key="step-{{ $step->id }}" class="flex items-start gap-2">
                                        <flux:icon :name="$step->icon()" variant="micro" @class(['mt-0.5 shrink-0', 'text-red-500' => $step->kind === 'echec', 'text-amber-500' => $step->kind !== 'echec']) />
                                        <span class="min-w-0 break-words">
                                            <span class="text-zinc-700 dark:text-zinc-200">{{ $step->label() }}</span>
                                            @if ($step->target)
                                                <code class="ml-1 break-all rounded bg-zinc-200 px-1 py-0.5 text-xs dark:bg-zinc-700">{{ $step->target }}</code>
                                            @endif
                                        </span>
                                    </li>
                                @endforeach
                            </ol>
                        </details>
                    @elseif ($run->status === AgentRun::STATUS_COMPLETED)
                        <span class="text-zinc-600 dark:text-zinc-300">{{ __('Finished.') }}</span>
                    @endif

                    @if ($run->status === AgentRun::STATUS_COMPLETED && $project->hasLivePreview())
                        <flux:button size="sm" variant="filled" icon="eye" class="mt-2 lg:hidden" x-on:click="onglet = 'apercu'; window.scrollTo({ top: 0 })" data-show-preview>
                            {{ __('See the preview') }}
                        </flux:button>
                    @endif
                </div>
            @endif
        @empty
            <div class="m-auto flex max-w-sm flex-col items-center gap-3 py-10 text-center">
                <x-app-logo-icon class="size-10 text-amber-500" />
                <flux:heading size="lg">{{ __('The conversation will start here') }}</flux:heading>
                <flux:text>{{ __('Describe what you want to build. Kekeli works in its own computer and shows you each step live.') }}</flux:text>
            </div>
        @endforelse
    </div>

    <form wire:submit="send" class="flex flex-col gap-1">
        <div class="flex items-end gap-2">
            <flux:textarea wire:model="content" :placeholder="__('Describe what you want to build…')" rows="2" class="flex-1" :aria-label="__('Message')" :disabled="(bool) $this->activeRun" />
            <flux:button type="submit" variant="primary" icon="paper-airplane" :aria-label="__('Send')" :disabled="(bool) $this->activeRun" />
        </div>
        <flux:error name="content" />
    </form>
    </div>

    <div id="panneau-apercu" role="tabpanel" aria-labelledby="onglet-apercu" class="flex flex-1 flex-col" x-bind:class="onglet === 'apercu' ? '' : 'max-lg:hidden'" x-cloak wire:ignore.self data-panel="apercu">
        @if ($project->hasLivePreview())
            <div class="flex flex-1 flex-col overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700" x-data="{ recharger() { this.$refs.cadre.src = this.$refs.cadre.src } }" data-preview="live">
                <div class="flex items-center gap-2 border-b border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-900">
                    <span class="relative flex size-2.5" aria-hidden="true">
                        @if ($this->activeRun)
                            <span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                        @endif
                        <span class="relative inline-flex size-2.5 rounded-full bg-emerald-500"></span>
                    </span>
                    <span class="min-w-0 flex-1 truncate text-sm font-medium text-zinc-700 dark:text-zinc-200">
                        {{ $this->activeRun ? __('Live preview, under construction') : __('Live preview') }}
                    </span>
                    <flux:button size="sm" variant="ghost" icon="arrow-path" x-on:click="recharger()" :aria-label="__('Reload the preview')" :tooltip="__('Reload the preview')" />
                    <flux:button size="sm" variant="ghost" icon="arrow-top-right-on-square" :href="$project->preview_url" target="_blank" rel="noopener noreferrer" :aria-label="__('Open the preview in a new tab')" :tooltip="__('Open the preview in a new tab')" />
                </div>
                <iframe
                    wire:key="preview-{{ md5($project->preview_url) }}"
                    x-ref="cadre"
                    src="{{ $project->preview_url }}"
                    title="{{ __('Preview of :name', ['name' => $project->name]) }}"
                    class="h-[calc(100svh-15rem)] min-h-96 w-full flex-1 bg-white lg:h-auto"
                    sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-modals allow-downloads"
                    referrerpolicy="no-referrer"
                    loading="eager"
                ></iframe>
            </div>
            <flux:text class="mt-2 text-xs">
                {{ __('The preview stays on until :time (Lomé time). It turns off 30 minutes after your last request.', ['time' => $project->preview_expires_at->timezone(config('kekeli.display_timezone'))->format('H:i')]) }}
            </flux:text>
        @else
            <div class="flex min-h-96 flex-1 flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-zinc-300 p-6 text-center dark:border-zinc-600" data-preview="{{ $this->activeRun ? 'starting' : ($project->preview_url ? 'asleep' : 'empty') }}">
                @if ($this->activeRun)
                    <flux:icon.loading class="size-8 text-amber-500" />
                    <flux:heading size="lg">{{ __('Kekeli is preparing the preview…') }}</flux:heading>
                    <flux:text class="max-w-sm">{{ __('Your project will appear here in a few seconds, and you will see it take shape live.') }}</flux:text>
                @elseif ($project->preview_url)
                    <flux:icon.moon class="size-10 text-zinc-400" />
                    <flux:heading size="lg">{{ __('The preview is paused') }}</flux:heading>
                    <flux:text class="max-w-sm">{{ __('Kekeli\'s computer turns off 30 minutes after your last request. Send a new request to start it again.') }}</flux:text>
                @else
                    <x-app-logo-icon class="size-10 text-amber-500" />
                    <flux:heading size="lg">{{ __('The preview will appear here') }}</flux:heading>
                    <flux:text class="max-w-sm">{{ __('As soon as Kekeli starts building, you will see your project take shape here, live.') }}</flux:text>
                @endif
            </div>
        @endif
    </div>
    </div>
</section>
