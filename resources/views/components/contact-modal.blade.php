{{--
    « Nous contacter » (menu du compte), comme « Aide » chez Claude et ChatGPT : une petite fenêtre avec l'e-mail, les
    numéros (un toucher lance l'appel) et un bouton WhatsApp vers le numéro principal. App\Support\Contact.
--}}
@php
    $contactEmail = \App\Support\Contact::email();
    $contactPhones = \App\Support\Contact::phones();
    $whatsapp = \App\Support\Contact::whatsappLink();
@endphp
<flux:modal name="contact" class="w-full max-w-sm" data-contact-modal>
    <div class="space-y-5">
        <div>
            <flux:heading size="lg">{{ __('Contact us') }}</flux:heading>
            <flux:text class="mt-1">{{ __('A person from Kekeli answers you, in French.') }}</flux:text>
        </div>

        <ul class="kekeli-carte divide-y divide-zinc-200 dark:divide-zinc-700">
            @if ($contactEmail !== '')
                <li>
                    <a href="mailto:{{ $contactEmail }}" class="flex items-center gap-3 px-4 py-3 text-sm transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800" data-contact-email>
                        <flux:icon.envelope variant="mini" class="shrink-0 text-zinc-500" />
                        <span class="min-w-0 truncate font-medium text-zinc-900 dark:text-white">{{ $contactEmail }}</span>
                    </a>
                </li>
            @endif
            @foreach ($contactPhones as $phone)
                <li>
                    <a href="{{ \App\Support\Contact::telLink($phone) }}" class="flex items-center gap-3 px-4 py-3 text-sm transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800" data-contact-phone>
                        <flux:icon.phone variant="mini" class="shrink-0 text-zinc-500" />
                        <span class="whitespace-nowrap font-medium text-zinc-900 dark:text-white">{{ \App\Support\Phones::unbreakable($phone) }}</span>
                        <span class="ms-auto text-xs text-zinc-500">{{ __('Call') }}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        @if ($whatsapp !== '')
            <flux:button :href="$whatsapp" target="_blank" rel="noopener" variant="primary" icon="chat-bubble-oval-left-ellipsis" class="w-full" data-contact-whatsapp>{{ __('Write on WhatsApp') }}</flux:button>
        @endif
    </div>
</flux:modal>
