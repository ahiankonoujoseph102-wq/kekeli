<?php

use App\Agent\Energy;
use App\Agent\PreviewToken;
use App\Agent\RunnerClient;
use App\Jobs\PublishProject;
use App\Jobs\SendAgentRun;
use App\Jobs\WakePreview;
use App\Models\AgentPermission;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\ProjectPhoto;
use App\Models\ProjectSite;
use App\Support\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    #[Locked]
    public Project $project;

    public string $content = '';

    public string $newSite = '';

    /** Photos chosen on the phone, before they are saved. */
    public array $uploads = [];

    public string $country = '';

    /**
     * Mount the component with one of the current user's projects. An administrator may look at
     * any project, without changing anything.
     */
    public function mount(Project $project): void
    {
        abort_unless($project->user()->is(Auth::user()) || Auth::user()->can('admin'), 404);

        $this->project = $project;
        $this->country = (string) (Auth::user()->country ?? config('kekeli.default_country'));
    }

    /**
     * Render the page with the project's name as the title.
     */
    public function rendering($view): void
    {
        $view->title($this->project->name);
    }

    /**
     * Whether the current user owns the project (an administrator only looks at it).
     */
    #[Computed]
    public function isOwner(): bool
    {
        return $this->project->user()->is(Auth::user());
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
     * The requests sent to the agent, with their steps and permissions, by user message.
     *
     * @return Collection<int, AgentRun>
     */
    #[Computed]
    public function runs(): Collection
    {
        return $this->project->agentRuns()->with('steps', 'permissions')->get()->keyBy('message_id');
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
     * Private address of the preview (with a temporary token), if the preview is on.
     */
    #[Computed]
    public function previewUrl(): ?string
    {
        return $this->project->hasLivePreview() ? PreviewToken::url($this->project) : null;
    }

    /**
     * Sites added by the user, and the history of the changes.
     *
     * @return Collection<int, ProjectSite>
     */
    #[Computed]
    public function sites(): Collection
    {
        return $this->project->sites()->get();
    }

    /**
     * The owner's energy: formula, energy left and when it recharges (« l'énergie qui se recharge »).
     *
     * @return array{plan: array<string, mixed>, free: bool, free_cap: bool, percent: int, exhausted: bool, recharges_at: string}
     */
    #[Computed]
    public function energy(): array
    {
        $energy = app(Energy::class);
        $owner = $this->project->user;

        $free = $energy->isFree($owner);
        $freeCap = $free && $energy->freeCapReached();

        return [
            'plan' => $energy->plan($owner),
            'free' => $free,
            'free_cap' => $freeCap,
            'percent' => $energy->percentLeft($owner),
            'exhausted' => $freeCap || $energy->isExhausted($owner),
            'recharges_at' => $energy->rechargesAt($owner)->timezone(config('kekeli.display_timezone'))->format('H:i'),
        ];
    }

    /**
     * Minutes without a request before the computer pauses (set in the administration).
     */
    #[Computed]
    public function pauseMinutes(): int
    {
        return app(Settings::class)->int('pause_minutes');
    }

    /**
     * Whether the client still has to say in which country they work (accounts created before the question existed).
     */
    #[Computed]
    public function needsCountry(): bool
    {
        return $this->isOwner && ! Auth::user()->countryDetails();
    }

    /**
     * Save the client's country: Kekeli adapts photos, prices and phone numbers to it.
     */
    public function saveCountry(): void
    {
        abort_unless($this->isOwner, 403);

        $this->validate(['country' => ['required', 'string', Rule::in(array_keys(config('kekeli.countries')))]]);

        Auth::user()->update(['country' => $this->country]);

        unset($this->needsCountry);
    }

    /**
     * The photos sent by the client for this project.
     *
     * @return Collection<int, ProjectPhoto>
     */
    #[Computed]
    public function photos(): Collection
    {
        return $this->project->photos()->get();
    }

    /**
     * Save the photos chosen on the phone. They are copied into the project's computer at the next request.
     */
    public function savePhotos(): void
    {
        abort_unless($this->isOwner, 403);

        $limits = config('kekeli.client_photos');
        $room = $limits['max_per_project'] - $this->project->photos()->count();

        $this->validate([
            'uploads' => ['required', 'array', 'min:1', 'max:'.max(0, $room)],
            'uploads.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.$limits['max_kilobytes']],
        ], [
            'uploads.max' => __('You can keep at most :count photos in a project.', ['count' => $limits['max_per_project']]),
        ], [
            'uploads' => __('photos'),
            'uploads.*' => __('photo'),
        ]);

        foreach ($this->uploads as $upload) {
            $extension = strtolower($upload->getClientOriginalExtension() ?: $upload->extension());
            $extension = $extension === 'jpeg' ? 'jpg' : $extension;
            $base = Str::slug(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'photo';
            $name = Str::limit($base, 60, '').'.'.$extension;

            for ($i = 2; $this->project->photos()->where('name', $name)->exists(); $i++) {
                $name = Str::limit($base, 55, '').'-'.$i.'.'.$extension;
            }

            $path = $upload->storeAs($limits['directory'].'/'.$this->project->id, Str::random(20).'.'.$extension, ProjectPhoto::disk());

            $this->project->photos()->create(['name' => $name, 'path' => $path, 'size' => $upload->getSize()]);
        }

        $this->reset('uploads');
        unset($this->photos);
    }

    public function deletePhoto(int $photoId): void
    {
        abort_unless($this->isOwner, 403);

        $this->project->photos()->findOrFail($photoId)->deleteWithFile();

        unset($this->photos);
    }

    /**
     * Send the user's message to the agent.
     */
    public function send(): void
    {
        abort_unless($this->isOwner, 403);

        if ($this->needsCountry) {
            $this->addError('country', __('Choose your country first: Kekeli adapts your project to it.'));

            return;
        }

        unset($this->energy);
        if ($this->energy['exhausted']) {
            return;
        }

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

            app(Energy::class)->startWindowIfNeeded(Auth::user());

            return $this->project->agentRuns()->create(['message_id' => $message->id]);
        });

        $this->reset('content');

        rescue(function () use ($run) {
            SendAgentRun::dispatch($run);
        }, report: true);

        unset($this->messages, $this->runs, $this->activeRun, $this->energy);
    }

    /**
     * Publish the project online, on a free pages.dev address (the version saved after the last request).
     */
    public function publish(): void
    {
        abort_unless($this->isOwner, 403);

        $this->project->refresh();

        if (! $this->project->backup_at || $this->project->publish_status === Project::PUBLISH_IN_PROGRESS) {
            return;
        }

        if ($this->project->agentRuns()->whereIn('status', AgentRun::ACTIVE_STATUSES)->exists()) {
            $this->addError('publish', __('Kekeli is still working: publish once the request is finished.'));

            return;
        }

        $this->project->forceFill(['publish_status' => Project::PUBLISH_IN_PROGRESS, 'publish_error' => null])->save();

        rescue(function () {
            PublishProject::dispatch($this->project);
        }, report: true);
    }

    /**
     * Resume a request stopped because the energy ran out, exactly where it stopped (after the recharge,
     * or after a formula has been paid).
     */
    public function continueRun(string $runId): void
    {
        abort_unless($this->isOwner, 403);

        $run = $this->project->agentRuns()->whereKey($runId)->firstOrFail();

        unset($this->energy);
        if ($run->status !== AgentRun::STATUS_STOPPED || $this->energy['exhausted']) {
            return;
        }

        if ($this->project->agentRuns()->whereIn('status', AgentRun::ACTIVE_STATUSES)->exists()) {
            return;
        }

        $run->update([
            'status' => AgentRun::STATUS_QUEUED,
            'stop_requested_at' => null,
            'started_at' => null,
            'finished_at' => null,
        ]);

        app(Energy::class)->startWindowIfNeeded(Auth::user());

        rescue(function () use ($run) {
            SendAgentRun::dispatch($run, true);
        }, report: true);

        unset($this->runs, $this->activeRun, $this->energy);
    }

    /**
     * The user allows or refuses a risky action asked by the agent.
     */
    public function answerPermission(int $permissionId, bool $allowed): void
    {
        abort_unless($this->isOwner, 403);

        $permission = AgentPermission::query()
            ->whereKey($permissionId)
            ->whereHas('run', fn ($query) => $query->where('project_id', $this->project->id))
            ->firstOrFail();

        if (! $permission->isPending()) {
            return;
        }

        if (! app(RunnerClient::class)->answerPermission($permission, $allowed)) {
            $this->addError('permission', __('Kekeli could not receive your answer. Please try again.'));

            return;
        }

        $permission->update([
            'status' => $allowed ? AgentPermission::STATUS_ALLOWED : AgentPermission::STATUS_REFUSED,
            'decided_by' => Auth::id(),
            'decided_at' => now(),
        ]);

        unset($this->runs);
    }

    /**
     * Turn the preview back on: the paused computer is woken up, with all the project's files.
     */
    public function wakePreview(): void
    {
        $project = $this->project;

        if ($this->activeRun || $project->hasLivePreview() || $project->previewIsWaking() || blank($project->preview_url)) {
            return;
        }

        $project->timestamps = false;
        $project->forceFill(['preview_waking_at' => now()])->save();
        $project->timestamps = true;

        rescue(function () use ($project) {
            WakePreview::dispatch($project);
        }, report: true);
    }

    /**
     * Add a site to the list of sites the project's computer may reach.
     */
    public function addSite(): void
    {
        abort_unless($this->isOwner, 403);

        $domain = strtolower(trim($this->newSite));
        $domain = preg_replace('#^[a-z]+://#', '', $domain);
        $domain = rtrim(explode('/', $domain)[0], '.');
        $this->newSite = $domain;

        $this->validate([
            'newSite' => ['required', 'string', 'max:253', 'regex:/^(?!-)(?:[a-z0-9-]{1,63}\.)+[a-z]{2,63}$/'],
        ], [], ['newSite' => __('site')]);

        $defaults = array_merge(...array_values(config('kekeli.default_sites')));

        if (in_array($domain, $defaults, true) || in_array($domain, $this->project->allowedDomains(), true)) {
            $this->addError('newSite', __('This site is already allowed.'));

            return;
        }

        if (count($this->project->allowedDomains()) >= 30) {
            $this->addError('newSite', __('You can add up to 30 sites.'));

            return;
        }

        $this->project->sites()->create(['domain' => $domain, 'user_id' => Auth::id()]);
        $this->reset('newSite');
        unset($this->sites);
    }

    /**
     * Remove a site added by the user. The row is kept, so the change stays recorded.
     */
    public function removeSite(int $siteId): void
    {
        abort_unless($this->isOwner, 403);

        $this->project->sites()->whereKey($siteId)->whereNull('removed_at')->update(['removed_at' => now()]);
        unset($this->sites);
    }
}; ?>

<section class="mx-auto flex min-h-[calc(100svh-6rem)] w-full max-w-3xl flex-col gap-4 lg:max-w-7xl" x-data="{ onglet: 'discussion' }" @if ($this->activeRun || $project->previewIsWaking() || $project->publish_status === Project::PUBLISH_IN_PROGRESS) wire:poll.2s @elseif ($project->hasLivePreview()) wire:poll.60s @endif>
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

        @if ($this->isOwner)
            <div class="mt-2 -ml-2 flex flex-wrap gap-1">
                <flux:modal.trigger name="client-photos">
                    <flux:button size="sm" variant="subtle" icon="camera" data-open-photos>
                        {{ __('My photos') }}@if ($this->photos->isNotEmpty()) ({{ $this->photos->count() }})@endif
                    </flux:button>
                </flux:modal.trigger>
                <flux:modal.trigger name="allowed-sites">
                    <flux:button size="sm" variant="subtle" icon="globe-alt" data-open-sites>{{ __('Allowed sites') }}</flux:button>
                </flux:modal.trigger>
            </div>

            <div class="mt-3 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700" data-publication="{{ $project->publish_status ?? 'none' }}">
                @if ($project->publish_status === Project::PUBLISH_IN_PROGRESS)
                    <div class="flex items-center gap-2 text-sm font-medium text-sky-700 dark:text-sky-300">
                        <flux:icon.loading class="size-4" />
                        {{ __('Publishing your site…') }}
                    </div>
                @else
                    @if ($project->published_url)
                        <div class="text-sm">
                            <div class="flex items-center gap-2 font-medium text-emerald-700 dark:text-emerald-300">
                                <flux:icon.check-circle variant="micro" />
                                {{ __('Your site is online:') }}
                            </div>
                            <a href="{{ $project->published_url }}" target="_blank" rel="noopener" class="mt-1 block break-all font-medium text-amber-700 underline dark:text-amber-400" data-published-url>{{ $project->published_url }}</a>
                            <p class="mt-1 text-xs text-zinc-500">{{ __('Published on :date. Later changes are online only after « Publish again ».', ['date' => $project->published_at?->timezone(config('kekeli.display_timezone'))->format('d/m/Y \à H:i')]) }}</p>
                            @if ($project->published_at?->gt(now()->subMinutes(15)))
                                <p class="mt-1 text-xs text-zinc-500" data-publish-delay>{{ __('If the address does not open yet, wait a few minutes: putting a site online can take a little time.') }}</p>
                            @endif
                        </div>
                    @endif
                    @if ($project->publish_status === Project::PUBLISH_FAILED)
                        <p class="text-sm text-red-700 dark:text-red-300" data-publish-failed>{{ __('Your site could not be published this time. Please try again in a moment.') }}</p>
                    @endif
                    @if ($project->backup_at)
                        <flux:button size="sm" :variant="$project->published_url ? 'subtle' : 'primary'" icon="rocket-launch" class="mt-2" wire:click="publish" :disabled="(bool) $this->activeRun" data-publish>
                            {{ $project->published_url ? __('Publish again') : __('Publish my site') }}
                        </flux:button>
                        @unless ($project->published_url)
                            <p class="mt-1 text-xs text-zinc-500">{{ __('Your site goes online for free, at an address ending in pages.dev, in one tap.') }}</p>
                        @endunless
                    @else
                        <p class="text-sm text-zinc-500">{{ __('Once Kekeli has built your site, you can publish it online here in one tap.') }}</p>
                    @endif
                    <flux:error name="publish" />
                @endif
            </div>
        @else
            <flux:callout icon="shield-check" color="sky" class="mt-3" data-admin-view>
                <flux:callout.text>{{ __('You are looking at this project as an administrator (read only). It belongs to :owner.', ['owner' => $project->user->email]) }}</flux:callout.text>
            </flux:callout>
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
                    @elseif ($run->status === AgentRun::STATUS_STOPPED)
                        <div class="rounded-lg border border-amber-300 bg-amber-50 p-3 dark:border-amber-700 dark:bg-amber-950" data-run-stopped>
                            @if ($this->energy['exhausted'])
                                <p class="font-medium text-amber-800 dark:text-amber-200">
                                    {{ __('☀️ Your energy is used up: Kekeli has stopped the work. Your project is saved as it is, with nothing lost. The energy recharges at :time (Lomé time); you will then continue exactly where the work stopped.', ['time' => $this->energy['recharges_at']]) }}
                                </p>
                                @if ($this->isOwner)
                                    <flux:button :href="route('plans')" variant="primary" size="sm" icon="sun" class="mt-3" wire:navigate>{{ __('See the formulas') }}</flux:button>
                                @endif
                            @else
                                <p class="font-medium text-amber-800 dark:text-amber-200">
                                    {{ __('☀️ Kekeli had stopped because the energy was used up. Your energy is recharged: continue exactly where the work stopped.') }}
                                </p>
                                @if ($this->isOwner && ! $this->activeRun)
                                    <flux:button variant="primary" size="sm" icon="play" class="mt-3" wire:click="continueRun('{{ $run->id }}')" data-continue>{{ __('Continue') }}</flux:button>
                                @endif
                            @endif
                        </div>
                    @elseif ($run->status === AgentRun::STATUS_FAILED)
                        <div class="font-medium text-red-700 dark:text-red-300">
                            {{ __('Sorry, Kekeli could not finish this request. Please try again in a moment.') }}
                        </div>
                    @endif

                    @foreach ($run->permissions as $permission)
                        @if ($permission->isPending() && $run->isActive())
                            <div wire:key="permission-{{ $permission->id }}" class="mt-2 rounded-lg border border-amber-300 bg-amber-50 p-3 dark:border-amber-700 dark:bg-amber-950" data-permission="pending">
                                <div class="flex items-center gap-2 font-medium text-amber-800 dark:text-amber-200">
                                    <flux:icon.shield-exclamation variant="micro" />
                                    {{ __('Kekeli asks for your permission') }}
                                </div>
                                <div class="mt-1 text-zinc-700 dark:text-zinc-200">{{ $permission->toolLabel() }}</div>
                                @if ($permission->target)
                                    <code class="mt-1 block break-all rounded bg-white px-2 py-1 text-xs dark:bg-zinc-800">{{ $permission->target }}</code>
                                @endif
                                @if ($this->isOwner)
                                    <div class="mt-3 flex gap-2">
                                        <flux:button size="sm" variant="primary" icon="check" wire:click="answerPermission({{ $permission->id }}, true)" data-allow>{{ __('Allow') }}</flux:button>
                                        <flux:button size="sm" variant="danger" icon="x-mark" wire:click="answerPermission({{ $permission->id }}, false)" data-refuse>{{ __('Refuse') }}</flux:button>
                                    </div>
                                    <flux:error name="permission" />
                                @endif
                            </div>
                        @elseif (! $permission->isPending())
                            <div wire:key="permission-{{ $permission->id }}" class="mt-2 flex items-start gap-2 text-zinc-600 dark:text-zinc-300" data-permission="{{ $permission->status }}">
                                <flux:icon :name="$permission->status === AgentPermission::STATUS_ALLOWED ? 'check-circle' : 'x-circle'" variant="micro" @class(['mt-0.5 shrink-0', 'text-emerald-500' => $permission->status === AgentPermission::STATUS_ALLOWED, 'text-red-500' => $permission->status !== AgentPermission::STATUS_ALLOWED]) />
                                <span class="min-w-0 break-words">
                                    {{ match ($permission->status) { AgentPermission::STATUS_ALLOWED => __('Permission given'), AgentPermission::STATUS_REFUSED => __('Permission refused'), default => __('No answer: action refused') } }} :
                                    {{ $permission->toolLabel() }}
                                    @if ($permission->target)
                                        <code class="ml-1 break-all rounded bg-zinc-200 px-1 py-0.5 text-xs dark:bg-zinc-700">{{ $permission->target }}</code>
                                    @endif
                                </span>
                            </div>
                        @endif
                    @endforeach

                    @if ($run->steps->isNotEmpty())
                        <details @class(['group', 'mt-2' => $run->status !== AgentRun::STATUS_COMPLETED]) @if ($run->isActive()) open @endif>
                            <summary class="cursor-pointer select-none text-zinc-600 dark:text-zinc-300">
                                {{ trans_choice('{1} :count action by Kekeli|[2,*] :count actions by Kekeli', $run->steps->count(), ['count' => $run->steps->count()]) }}
                            </summary>
                            <ol class="mt-2 flex flex-col gap-1.5">
                                @foreach ($run->steps as $step)
                                    <li wire:key="step-{{ $step->id }}" class="flex items-start gap-2">
                                        <flux:icon :name="$step->icon()" variant="micro" @class(['mt-0.5 shrink-0', 'text-red-500' => $step->tone() === 'danger', 'text-emerald-500' => $step->tone() === 'success', 'text-amber-500' => $step->tone() === 'normal']) />
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

    @if ($this->isOwner && $this->energy['exhausted'] && ! $this->activeRun)
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm dark:border-amber-700 dark:bg-amber-950" data-energy-exhausted="{{ $this->energy['free_cap'] ? 'free-cap' : ($this->energy['free'] ? 'free' : 'paid') }}">
            <p class="text-zinc-800 dark:text-zinc-100">
                @if ($this->energy['free_cap'])
                    {{ __('☀️ Kekeli is recharging right now. It will be ready again at :time. Don\'t want to wait? Switch to a paid formula and continue right away.', ['time' => $this->energy['recharges_at']]) }}
                @elseif ($this->energy['free'])
                    {{ __('☀️ Your energy for today is used up. Kekeli is recharging and will be ready again at :time. Don\'t want to wait? Switch to a paid formula and continue right away.', ['time' => $this->energy['recharges_at']]) }}
                @else
                    {{ __('☀️ You have used all the energy of your formula for now. It recharges at :time. Your project is saved: you will pick up exactly where you left off. Need more? Switch to the higher formula.', ['time' => $this->energy['recharges_at']]) }}
                @endif
            </p>
            <flux:button :href="route('plans')" variant="primary" size="sm" icon="sun" class="mt-3" wire:navigate>{{ __('See the formulas') }}</flux:button>
        </div>
    @elseif ($this->isOwner)
    <div class="flex items-center gap-2 text-xs text-zinc-600 dark:text-zinc-300" data-energy="{{ $this->energy['percent'] }}">
        <flux:icon.sun variant="micro" class="text-amber-500" />
        <span>{{ __('Energy') }}</span>
        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700" role="meter" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $this->energy['percent'] }}" aria-label="{{ __('Energy left') }}">
            <div @class(['h-full rounded-full', 'bg-amber-500' => $this->energy['percent'] > 20, 'bg-red-500' => $this->energy['percent'] <= 20]) style="width: {{ $this->energy['percent'] }}%"></div>
        </div>
        <span>{{ $this->energy['percent'] }} %</span>
    </div>
    @if ($this->needsCountry)
        <form wire:submit="saveCountry" class="flex flex-col gap-2 rounded-xl border border-amber-300 bg-amber-50 p-3 dark:border-amber-700 dark:bg-amber-950" data-country-question>
            <flux:heading size="sm">{{ __('In which country is your activity?') }}</flux:heading>
            <flux:text class="text-sm">{{ __('Kekeli adapts photos, prices and phone numbers to your country.') }}</flux:text>
            <div class="flex items-end gap-2">
                <flux:select wire:model="country" class="flex-1" :aria-label="__('Your country')">
                    @foreach (config('kekeli.countries') as $code => $details)
                        <flux:select.option value="{{ $code }}">{{ $details['name'] }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
            <flux:error name="country" />
        </form>
    @endif
    <form wire:submit="send" class="flex flex-col gap-1">
        <div class="flex items-end gap-2">
            <flux:textarea wire:model="content" :placeholder="__('Describe what you want to build…')" rows="2" class="flex-1" :aria-label="__('Message')" :disabled="(bool) $this->activeRun" />
            <flux:button type="submit" variant="primary" icon="paper-airplane" :aria-label="__('Send')" :disabled="(bool) $this->activeRun" />
        </div>
        <flux:error name="content" />
    </form>
    @endif
    </div>

    <div id="panneau-apercu" role="tabpanel" aria-labelledby="onglet-apercu" class="flex flex-1 flex-col" x-bind:class="onglet === 'apercu' ? '' : 'max-lg:hidden'" x-cloak wire:ignore.self data-panel="apercu">
        @php($warning = (int) config('kekeli.preview_pause_warning'))
        @if ($this->previewUrl && ! $project->previewIsPausing())
            @php($remaining = max(0, now()->diffInSeconds($project->preview_expires_at, false)))
            {{-- Une minute et demie avant la pause, l'aperçu annonce la pause au lieu d'afficher une erreur. --}}
            <div wire:key="preview-{{ md5($this->previewUrl) }}" class="flex flex-1 flex-col"
                x-data="{ bientot: false, recharger() { this.$refs.cadre.src = this.$refs.cadre.src } }"
                x-init="setTimeout(() => { bientot = true }, {{ max(0, (int) $remaining - $warning) * 1000 }}); setTimeout(() => $wire.$refresh(), {{ ((int) $remaining + 5) * 1000 }})">
                <div x-show="! bientot" class="flex flex-1 flex-col overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700" data-preview="live">
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
                        <flux:button size="sm" variant="ghost" icon="arrow-top-right-on-square" :href="$this->previewUrl" target="_blank" rel="noopener noreferrer" :aria-label="__('Open the preview in a new tab')" :tooltip="__('Open the preview in a new tab')" />
                    </div>
                    <iframe
                        x-ref="cadre"
                        src="{{ $this->previewUrl }}"
                        title="{{ __('Preview of :name', ['name' => $project->name]) }}"
                        class="h-[calc(100svh-15rem)] min-h-96 w-full flex-1 bg-white lg:h-auto"
                        sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-modals allow-downloads"
                        referrerpolicy="no-referrer"
                        loading="eager"
                    ></iframe>
                </div>
                <div x-show="bientot" x-cloak class="flex min-h-96 flex-1 flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-zinc-300 p-6 text-center dark:border-zinc-600" data-preview-pausing-soon>
                    <flux:icon.moon class="size-10 animate-pulse text-amber-500" />
                    <flux:heading size="lg">{{ __('Kekeli is pausing…') }}</flux:heading>
                    <flux:text class="max-w-sm">{{ __('Your files are kept. In a moment you can turn the preview back on.') }}</flux:text>
                </div>
                <flux:text x-show="! bientot" class="mt-2 text-xs">
                    {{ __('The preview stays on until :time (Lomé time). It pauses :minutes minutes after your last request.', ['time' => $project->preview_expires_at->timezone(config('kekeli.display_timezone'))->format('H:i'), 'minutes' => $this->pauseMinutes]) }}
                </flux:text>
            </div>
        @else
            @php($state = match (true) {
                (bool) $this->activeRun => 'starting',
                $project->previewIsPausing() => 'pausing',
                $project->previewIsWaking() => 'waking',
                filled($project->preview_url) => 'asleep',
                default => 'empty',
            })
            <div class="flex min-h-96 flex-1 flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-zinc-300 p-6 text-center dark:border-zinc-600" data-preview="{{ $state }}">
                @if ($state === 'starting')
                    <flux:icon.loading class="size-8 text-amber-500" />
                    <flux:heading size="lg">{{ __('Kekeli is preparing the preview…') }}</flux:heading>
                    <flux:text class="max-w-sm">{{ __('Your project will appear here in a few seconds, and you will see it take shape live.') }}</flux:text>
                @elseif ($state === 'pausing')
                    <flux:icon.moon class="size-10 animate-pulse text-amber-500" />
                    <flux:heading size="lg">{{ __('Kekeli is pausing…') }}</flux:heading>
                    <flux:text class="max-w-sm">{{ __('Your files are kept. In a moment you can turn the preview back on.') }}</flux:text>
                @elseif ($state === 'waking')
                    <flux:icon.loading class="size-8 text-amber-500" />
                    <flux:heading size="lg">{{ __('Kekeli is turning the preview back on…') }}</flux:heading>
                    <flux:text class="max-w-sm">{{ __('Your project is coming back exactly as you left it. It takes less than a minute.') }}</flux:text>
                @elseif ($state === 'asleep')
                    <flux:icon.moon class="size-10 text-zinc-400" />
                    <flux:heading size="lg">{{ __('The preview is paused') }}</flux:heading>
                    <flux:text class="max-w-sm">{{ __('Kekeli\'s computer pauses :minutes minutes after your last request. Your files are kept: turn the preview back on, or send a new request.', ['minutes' => $this->pauseMinutes]) }}</flux:text>
                    @if ($project->computer_erased_at)
                        <flux:text class="max-w-sm text-xs" data-computer-erased>{{ __('After a long pause, the computer was erased to save resources. Your project is safely saved and will be put back in a new computer, exactly as you left it.') }}</flux:text>
                    @endif
                    <flux:button variant="primary" icon="sun" wire:click="wakePreview" data-wake-preview>{{ __('Turn the preview back on') }}</flux:button>
                @else
                    <x-app-logo-icon class="size-10 text-amber-500" />
                    <flux:heading size="lg">{{ __('The preview will appear here') }}</flux:heading>
                    <flux:text class="max-w-sm">{{ __('As soon as Kekeli starts building, you will see your project take shape here, live.') }}</flux:text>
                @endif
            </div>
        @endif
    </div>
    </div>

    @if ($this->isOwner)
        <flux:modal name="client-photos" class="w-full max-w-lg">
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">{{ __('My photos') }}</flux:heading>
                    <flux:subheading>{{ __('Your own photos (your dishes, your articles, your shop, your team) are always used first. Kekeli places them where they fit.') }}</flux:subheading>
                </div>

                <form wire:submit="savePhotos" class="space-y-3">
                    <label class="flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 border-dashed border-zinc-300 p-5 text-center dark:border-zinc-600">
                        <flux:icon.camera class="size-8 text-amber-500" />
                        <span class="text-sm font-medium">{{ __('Choose photos on your phone') }}</span>
                        <span class="text-xs text-zinc-500">{{ __('JPG, PNG or WebP, up to 8 MB each, :count photos at most.', ['count' => config('kekeli.client_photos.max_per_project')]) }}</span>
                        <input type="file" wire:model="uploads" multiple accept="image/jpeg,image/png,image/webp" class="sr-only" data-photo-input>
                    </label>
                    <div wire:loading wire:target="uploads" class="text-sm text-zinc-500">{{ __('Sending the photos…') }}</div>
                    @if ($uploads)
                        <flux:text class="text-sm">{{ trans_choice(':count photo chosen|:count photos chosen', count($uploads), ['count' => count($uploads)]) }}</flux:text>
                    @endif
                    <flux:error name="uploads" />
                    <flux:error name="uploads.*" />
                    <flux:button type="submit" variant="primary" class="w-full" data-save-photos>{{ __('Add these photos') }}</flux:button>
                </form>

                <div class="grid grid-cols-3 gap-2" data-client-photos>
                    @forelse ($this->photos as $photo)
                        <div wire:key="photo-{{ $photo->id }}" class="relative overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                            <img src="{{ route('projects.photos.show', [$project, $photo]) }}" alt="{{ $photo->name }}" class="aspect-square w-full object-cover" loading="lazy">
                            <flux:button size="xs" variant="filled" icon="x-mark" class="absolute top-1 right-1" wire:click="deletePhoto({{ $photo->id }})" :aria-label="__('Remove :photo', ['photo' => $photo->name])" />
                        </div>
                    @empty
                        <flux:text class="col-span-3 text-sm">{{ __('No photo yet. Without your photos, Kekeli uses free photos adapted to your country, never presented as your own products.') }}</flux:text>
                    @endforelse
                </div>
            </div>
        </flux:modal>

        <flux:modal name="allowed-sites" class="w-full max-w-lg">
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">{{ __('Allowed sites') }}</flux:heading>
                    <flux:subheading>{{ __('Kekeli\'s computer can only reach these sites. Changes apply from the next request.') }}</flux:subheading>
                </div>

                <div class="space-y-2" data-user-sites>
                    <flux:heading size="sm">{{ __('Sites you added') }}</flux:heading>
                    @forelse ($this->sites->whereNull('removed_at') as $site)
                        <div wire:key="site-{{ $site->id }}" class="flex items-center justify-between gap-2 rounded-lg border border-zinc-200 px-3 py-1.5 dark:border-zinc-700">
                            <code class="break-all text-sm">{{ $site->domain }}</code>
                            <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="removeSite({{ $site->id }})" :aria-label="__('Remove :site', ['site' => $site->domain])" />
                        </div>
                    @empty
                        <flux:text class="text-sm">{{ __('No site added yet.') }}</flux:text>
                    @endforelse
                </div>

                <form wire:submit="addSite" class="flex items-start gap-2">
                    <div class="flex-1">
                        <flux:input wire:model="newSite" :placeholder="__('for example api.my-site.com')" :aria-label="__('Site to allow')" />
                        <flux:error name="newSite" />
                    </div>
                    <flux:button type="submit" variant="primary">{{ __('Add') }}</flux:button>
                </form>

                <details class="text-sm">
                    <summary class="cursor-pointer select-none text-zinc-600 dark:text-zinc-300">{{ __('Always allowed: free sources and services Kekeli needs') }}</summary>
                    <div class="mt-2 flex flex-wrap gap-1">
                        @foreach (array_merge(...array_values(config('kekeli.default_sites'))) as $domain)
                            <code class="rounded bg-zinc-100 px-1.5 py-0.5 text-xs dark:bg-zinc-800">{{ $domain }}</code>
                        @endforeach
                    </div>
                </details>

                @if ($this->sites->isNotEmpty())
                    <details class="text-sm" data-sites-history>
                        <summary class="cursor-pointer select-none text-zinc-600 dark:text-zinc-300">{{ __('History of the changes') }}</summary>
                        <ul class="mt-2 space-y-1 text-zinc-600 dark:text-zinc-300">
                            @foreach ($this->sites as $site)
                                <li wire:key="history-add-{{ $site->id }}">{{ __(':date: :site added', ['date' => $site->created_at->timezone(config('kekeli.display_timezone'))->format('d/m/Y H:i'), 'site' => $site->domain]) }}</li>
                                @if ($site->removed_at)
                                    <li wire:key="history-remove-{{ $site->id }}">{{ __(':date: :site removed', ['date' => $site->removed_at->timezone(config('kekeli.display_timezone'))->format('d/m/Y H:i'), 'site' => $site->domain]) }}</li>
                                @endif
                            @endforeach
                        </ul>
                    </details>
                @endif
            </div>
        </flux:modal>
    @endif
</section>
