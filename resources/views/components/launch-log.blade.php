@props(['lines' => [], 'ok' => null])
{{--
    Journal lisible d'un lancement d'automatisation : une ligne par étape, avec une petite pastille (réussie, à régler,
    erreur). Chaque texte passe par {{ }}, jamais de HTML brut ; rien de secret (masqué par le Runner et par le site).
--}}
<ol class="space-y-1.5 text-sm" data-launch-log>
    @foreach ($lines as $line)
        @php($status = $line['status'] ?? 'info')
        <li class="grid grid-cols-[auto_minmax(0,1fr)] items-start gap-x-2" data-launch-line="{{ $status }}">
            <span @class([
                'mt-1.5 size-2 rounded-full',
                'bg-emerald-500' => $status === 'ok',
                'bg-amber-500' => $status === 'attention',
                'bg-red-500' => $status === 'erreur',
                'bg-zinc-300 dark:bg-zinc-600' => $status === 'info',
            ]) aria-hidden="true"></span>
            <span class="min-w-0 break-words">
                <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ $line['step'] ?? '' }}</span>
                @if (filled($line['message'] ?? ''))
                    <span class="block whitespace-pre-line text-zinc-600 dark:text-zinc-300">{{ $line['message'] }}</span>
                @endif
            </span>
        </li>
    @endforeach
</ol>
