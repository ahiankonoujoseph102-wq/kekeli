{{--
    Comment nous joindre : l'e-mail et les téléphones de Kekeli (App\Support\Contact, Administration → Réglages).
    Chaque numéro reste sur une seule ligne. « inline » pour une phrase, sinon un petit bloc.
--}}
@props(['inline' => false, 'title' => null])
@php
    $contactEmail = \App\Support\Contact::email();
    $contactPhones = \App\Support\Contact::phones();
@endphp
@if ($contactEmail !== '' || $contactPhones)
    @if ($inline)
        <span {{ $attributes->merge(['data-contact' => true]) }}>@if ($contactEmail !== '')<a href="mailto:{{ $contactEmail }}" class="kekeli-lien">{{ $contactEmail }}</a>@endif @foreach ($contactPhones as $phone)@if ($contactEmail !== '' || ! $loop->first) · @endif<a href="{{ \App\Support\Contact::telLink($phone) }}" class="kekeli-lien whitespace-nowrap">{{ \App\Support\Phones::unbreakable($phone) }}</a>@endforeach</span>
    @else
        <div {{ $attributes->merge(['class' => 'kekeli-contact', 'data-contact' => true]) }}>
            @if ($title)<p class="kekeli-contact-titre">{{ $title }}</p>@endif
            <ul>
                @if ($contactEmail !== '')
                    <li><flux:icon.envelope variant="micro" class="kekeli-icone" aria-hidden="true" /><a href="mailto:{{ $contactEmail }}" class="kekeli-lien">{{ $contactEmail }}</a></li>
                @endif
                @foreach ($contactPhones as $phone)
                    <li><flux:icon.phone variant="micro" class="kekeli-icone" aria-hidden="true" /><a href="{{ \App\Support\Contact::telLink($phone) }}" class="kekeli-lien whitespace-nowrap">{{ \App\Support\Phones::unbreakable($phone) }}</a></li>
                @endforeach
            </ul>
        </div>
    @endif
@endif
