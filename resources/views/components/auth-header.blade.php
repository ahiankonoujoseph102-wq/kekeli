@props([
    'title',
    'description' => null,
])

<div class="flex w-full flex-col gap-2 text-center">
    <h1 class="text-xl font-medium dark:text-zinc-200">{{ $title }}</h1>
    @if ($description)
        <p class="text-center text-sm text-zinc-600 dark:text-zinc-400">{{ $description }}</p>
    @endif
</div>
