<?php

use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component {
    #[Locked]
    public Project $project;

    /**
     * Mount the component with one of the current user's projects only.
     */
    public function mount(Project $project): void
    {
        abort_unless($project->user()->is(Auth::user()), 404);

        $this->project = $project->load('messages');
    }

    /**
     * Render the page with the project's name as the title.
     */
    public function rendering($view): void
    {
        $view->title($this->project->name);
    }
}; ?>

<section class="mx-auto flex min-h-[calc(100svh-6rem)] w-full max-w-3xl flex-col gap-4">
    <div>
        <flux:link :href="route('projects.index')" variant="subtle" class="text-sm" wire:navigate>
            ← {{ __('My projects') }}
        </flux:link>

        <div class="mt-2 flex flex-wrap items-center gap-3">
            <flux:heading size="xl" level="1" class="break-words">{{ $project->name }}</flux:heading>
            <flux:badge color="amber" size="sm">{{ __('Draft') }}</flux:badge>
        </div>

        @if ($project->description)
            <flux:text class="mt-1">{{ $project->description }}</flux:text>
        @endif
    </div>

    <div class="flex flex-1 flex-col gap-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
        @forelse ($project->messages as $message)
            <div wire:key="message-{{ $message->id }}" @class([
                'max-w-[85%] whitespace-pre-line rounded-2xl px-4 py-2 text-sm',
                'self-end bg-amber-500 text-white' => $message->role === \App\Models\Message::ROLE_USER,
                'self-start bg-zinc-100 text-zinc-900 dark:bg-zinc-700 dark:text-white' => $message->role !== \App\Models\Message::ROLE_USER,
            ])>{{ $message->content }}</div>
        @empty
            <div class="m-auto flex max-w-sm flex-col items-center gap-3 py-10 text-center">
                <x-app-logo-icon class="size-10 text-amber-500" />
                <flux:heading size="lg">{{ __('The conversation will start here') }}</flux:heading>
                <flux:text>{{ __('The Kekeli agent is not connected yet. It will arrive in the next step of development: you will then describe your project here and watch it being built.') }}</flux:text>
            </div>
        @endforelse
    </div>

    <form class="flex items-end gap-2" onsubmit="return false">
        <flux:textarea :placeholder="__('Describe what you want to build…')" rows="2" disabled class="flex-1" :aria-label="__('Message')" />
        <flux:button variant="primary" icon="paper-airplane" disabled :aria-label="__('Send')" />
    </form>
</section>
