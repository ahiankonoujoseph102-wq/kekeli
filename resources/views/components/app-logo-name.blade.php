{{-- Le nom « kekeli » en tracés (police Lexend 500, licence OFL) : un seul fichier, public/logo-nom.svg. --}}
@php
    static $kekeliNom = null;
    $kekeliNom ??= preg_replace(['/\sdata-ligne-de-base="[^"]*"/', '/<svg\b/'], ['', '<svg role="img" aria-label="Kekeli"'], trim((string) file_get_contents(public_path('logo-nom.svg'))));
@endphp
{!! preg_replace('/<svg\b/', '<svg '.$attributes->toHtml(), $kekeliNom, 1) !!}
