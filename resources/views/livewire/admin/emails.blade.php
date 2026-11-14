<?php

use App\Jobs\SendEmail;
use App\Mail\Emails;
use App\Models\EmailLog;
use App\Support\Settings;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Component;
use Livewire\WithPagination;

/*
 * Administration → E-mails : le journal des envois (date, type, destinataire masqué, réussite ou échec), et les
 * e-mails envoyés aujourd'hui comparés au plafond de Kekeli. Jamais le contenu d'un e-mail.
 */
new class extends Component {
    use WithPagination;

    public function mount(): void
    {
        Gate::authorize('admin');
    }

    public function rendering($view): void
    {
        $view->title(__('E-mails (administration)'));
    }

    public function with(): array
    {
        return [
            'logs' => EmailLog::query()->latest('id')->paginate(50),
            'today' => SendEmail::sentToday(),
            'cap' => app(Settings::class)->int('email_daily_cap'),
            'deferred' => EmailLog::query()->where('status', EmailLog::DEFERRED)->count(),
        ];
    }
}; ?>

<section class="mx-auto flex w-full max-w-3xl flex-col gap-6" data-admin-emails>
    <div>
        <flux:heading size="xl" level="1">{{ __('E-mails (administration)') }}</flux:heading>
        <flux:subheading>{{ __('Every e-mail sent by Kekeli: never its content, and the address is masked.') }}</flux:subheading>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" data-emails-today>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Sent today (Lomé time)') }}</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $today }} <span class="text-base font-normal text-zinc-500">/ {{ $cap }}</span></p>
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Waiting for tomorrow') }}</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $deferred }}</p>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full min-w-[34rem] text-left text-sm" data-email-journal>
            <thead class="bg-zinc-50 text-xs text-zinc-500 dark:bg-zinc-800/60 dark:text-zinc-400">
                <tr>
                    <th class="px-3 py-2 font-medium">{{ __('Date') }}</th>
                    <th class="px-3 py-2 font-medium">{{ __('Type') }}</th>
                    <th class="px-3 py-2 font-medium">{{ __('Recipient') }}</th>
                    <th class="px-3 py-2 font-medium">{{ __('Result') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($logs as $log)
                    <tr wire:key="email-{{ $log->id }}" data-email-log="{{ $log->status }}">
                        <td class="whitespace-nowrap px-3 py-2 tabular-nums text-zinc-600 dark:text-zinc-300">{{ $log->created_at->timezone(config('kekeli.display_timezone'))->format('d/m/Y H:i') }}</td>
                        <td class="px-3 py-2">{{ __(Emails::TYPES[$log->type] ?? $log->type) }}</td>
                        <td class="px-3 py-2 text-zinc-600 dark:text-zinc-300">{{ $log->recipient_masked }}</td>
                        <td class="px-3 py-2">
                            @switch($log->status)
                                @case(EmailLog::SENT)
                                    <span class="text-green-700 dark:text-green-400">{{ __('Sent') }}</span>
                                    @break
                                @case(EmailLog::FAILED)
                                    <span class="text-red-700 dark:text-red-400" title="{{ $log->error }}">{{ __('Failed') }}</span>
                                    @break
                                @case(EmailLog::DEFERRED)
                                    <span class="text-amber-700 dark:text-amber-400">{{ __('Tomorrow') }}</span>
                                    @break
                                @default
                                    <span class="text-zinc-500">{{ $log->error ? __('New try soon') : __('In the queue') }}</span>
                            @endswitch
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-3 py-6 text-center text-zinc-500">{{ __('No e-mail sent yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $logs->links() }}
</section>
