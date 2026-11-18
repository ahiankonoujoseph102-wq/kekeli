{{--
    Kekeli Motion : l'animation elle-même, vivante, dans un cadre isolé (aucun accès au site de Kekeli, aucune requête
    vers Internet), sur fond neutre ; ce que l'on peut toucher ; « Mettre dans un site » quand le client a un site ou
    une application. Comme le reste des fichiers : tout se modifie en écrivant à Kekeli.
--}}
@php($format = data_get($version->structure, 'format', 'paysage'))
@php($sites = $this->isOwner ? $this->animationSites : collect())
<div class="min-h-0 flex-1 overflow-y-auto rounded-xl bg-zinc-100 p-3 sm:p-5 dark:bg-zinc-900" data-animation-panel>
    <div @class(['mx-auto flex flex-col gap-3', 'max-w-[22rem]' => $format === 'vertical', 'max-w-md' => $format === 'carre', 'max-w-4xl' => $format === 'paysage'])>
        <iframe wire:key="animation-{{ $version->id }}" src="{{ route('projects.versions.animation', ['project' => $project, 'number' => $version->number, 'file' => 'page']) }}"
            sandbox="allow-scripts" referrerpolicy="no-referrer" loading="lazy" title="{{ $version->title }}"
            @class(['block w-full rounded-lg border-0', 'aspect-[960/564]' => $format === 'paysage', 'aspect-[720/744]' => $format === 'carre', 'aspect-[540/984]' => $format === 'vertical'])
            data-animation-frame></iframe>
        <p class="text-xs text-zinc-500 dark:text-zinc-400" data-animation-hint>{{ __('Touch the characters, the sun or the moon, the trees and the windows: the animation reacts. Moving your finger makes the wind.') }}</p>

        @if ($this->isOwner)
            <div class="kekeli-carte flex flex-col gap-2 text-sm" data-animation-integrate>
                <p class="font-medium text-zinc-900 dark:text-white">{{ __('Put it in a site') }}</p>
                @if ($sites->isNotEmpty())
                    <p class="text-zinc-500 dark:text-zinc-400">{{ __('Kekeli places the animation in your site and shows it where it fits best. It stays light and keeps working on phones.') }}</p>
                    <div>
                        <flux:modal.trigger name="integrer-animation">
                            <flux:button size="sm" icon="window" data-animation-integrate-open>{{ __('Choose a site') }}</flux:button>
                        </flux:modal.trigger>
                    </div>
                @else
                    <p class="text-zinc-500 dark:text-zinc-400" data-animation-no-site>{{ __('Create a site with Kekeli first, then come back here to put this animation in it. You can also download the code and add it yourself.') }}</p>
                @endif
            </div>
        @endif
    </div>
</div>

@if ($this->isOwner && $sites->isNotEmpty())
    <flux:modal name="integrer-animation" class="w-full max-w-lg">
        <form wire:submit="integrateAnimation({{ $version->number }})" class="space-y-4" data-animation-integrate-form>
            <div>
                <flux:heading size="lg">{{ __('Put it in a site') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Kekeli adds this animation to the site you choose, like any change you ask for: it uses energy and creates a new version of the site.') }}</flux:text>
            </div>
            <flux:select wire:model="animationSite" :label="__('Site')" :placeholder="__('Choose a site')" data-animation-site>
                @foreach ($sites as $site)
                    <flux:select.option value="{{ $site->id }}">{{ $site->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" data-animation-integrate-submit>{{ __('Put it in the site') }}</flux:button>
            </div>
        </form>
    </flux:modal>
@endif
