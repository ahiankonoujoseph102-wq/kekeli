@props([
    'title',
    'description' => null,
])

{{-- En-tête des pages de connexion, comme Claude : un grand titre à empattements, une phrase courte dessous. --}}
<div class="flex w-full flex-col gap-2 text-center">
    <h1 class="kekeli-titre text-[2rem] leading-tight text-zinc-900 dark:text-white">{{ $title }}</h1>
    @if ($description)
        <p class="text-center text-sm text-zinc-600 dark:text-zinc-400">{{ $description }}</p>
    @endif
</div>
