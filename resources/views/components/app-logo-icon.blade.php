{{--
    Le logo de Kekeli (le soleil, provisoire) : UN SEUL fichier, public/logo.svg, inséré ici dans la page. Le favicon
    et l'image des e-mails (public/logo.png, public/favicon.ico) en sont tirés par « node scripts/logo.js ».
    Pour changer de logo : remplacer public/logo.svg (couleur en currentColor), puis lancer node scripts/logo.js.
--}}
@php
    static $kekeliLogo = null;
    $kekeliLogo ??= preg_replace(['/<svg\b([^>]*?)\sstyle="[^"]*"/', '/<svg\b/'], ['<svg$1', '<svg aria-hidden="true" focusable="false"'], trim((string) file_get_contents(public_path('logo.svg'))), 1);
@endphp
{!! preg_replace('/<svg\b/', '<svg '.$attributes->toHtml(), $kekeliLogo, 1) !!}
