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

<section class="mx-auto flex min-h-[calc(100svh-6rem)] w-full max-w-3xl flex-col gap-4" @if ($this->activeRun) wire:poll.2s @endif>
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
</section>
