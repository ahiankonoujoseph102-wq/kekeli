{{--
    Le symbole de Kekeli (un K : barre, jambe et flamme) : UN SEUL fichier, public/logo.svg, inséré ici dans la page.
    Ses couleurs suivent le mode clair ou sombre (--k-logo-forme et --k-logo-flamme dans resources/css/app.css).
    Toutes les déclinaisons (favicon, icônes du site installable, e-mails, docs/logo/) en sont tirées par
    « node scripts/logo.js ». La classe « kekeli-logo-actif » fait vaciller la flamme pendant que Kekeli travaille.
--}}
@php
    static $kekeliLogo = null;
    $kekeliLogo ??= preg_replace(['/\sid="[^"]*"/', '/<svg\b/'], ['', '<svg aria-hidden="true" focusable="false"'], trim((string) file_get_contents(public_path('logo.svg'))));
@endphp
{!! preg_replace('/<svg\b/', '<svg '.$attributes->toHtml(), $kekeliLogo, 1) !!}
