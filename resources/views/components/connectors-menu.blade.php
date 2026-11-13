{{--
    « + » → « Connecteurs », comme Claude : le client active ou désactive, pour cette discussion, les connecteurs qu'il a
    reliés dans Réglages → Connecteurs. Un connecteur actif est coché. Formule Gratuite : 2 actifs à la fois (réglable).
--}}
@props(['connectors', 'active' => [], 'toggle' => 'toggleConnector', 'max' => null])
@if (app(\App\Support\Connectors::class)->enabled())
    <flux:menu.submenu :heading="__('Connectors')" icon="puzzle-piece" data-prompt-connectors>
        @forelse ($connectors as $connector)
            @php($on = in_array($connector->id, $active, true))
            <flux:menu.item wire:click="{{ $toggle }}({{ $connector->id }})" :icon="$on ? 'check' : 'minus'" data-connector-toggle="{{ $connector->id }}" data-active="{{ $on ? 'oui' : 'non' }}" aria-pressed="{{ $on ? 'true' : 'false' }}">{{ $connector->name }}</flux:menu.item>
        @empty
            <flux:menu.item disabled>{{ __('No connector yet') }}</flux:menu.item>
        @endforelse
        @if ($max !== null && $connectors->isNotEmpty())
            <flux:menu.item disabled data-connectors-max>{{ trans_choice(':count active at most (Free formula)|:count active at most (Free formula)', $max, ['count' => $max]) }}</flux:menu.item>
        @endif
        <flux:menu.separator />
        <flux:menu.item icon="cog-6-tooth" :href="route('settings.connectors')" data-manage-connectors>{{ __('Manage the connectors') }}</flux:menu.item>
    </flux:menu.submenu>
@endif
