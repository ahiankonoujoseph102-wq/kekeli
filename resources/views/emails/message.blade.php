{{--
    Gabarit de tous les e-mails de Kekeli : sobre, lisible sur un téléphone, mode sombre des messageries qui le
    permettent ; styles écrits dans chaque balise (les messageries ignorent souvent les feuilles de style). Couleurs :
    App\Support\Design (les mêmes que le site) ; logo : public/logo.png, tiré du seul fichier public/logo.svg.
--}}
@php
    $c = \App\Support\Design::LIGHT;
    $d = \App\Support\Design::DARK;
    $font = \App\Support\Design::EMAIL_FONT;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>{{ $title }}</title>
    <style>
        @media (prefers-color-scheme: dark) {
            .fond { background: {{ $d['fond'] }} !important; }
            .carte { background: {{ $d['surface'] }} !important; border-color: {{ $d['bord'] }} !important; }
            .encre { color: {{ $d['texte'] }} !important; }
            .doux { color: {{ $d['texte-doux'] }} !important; }
            .bouton { background: {{ $d['bouton'] }} !important; color: {{ $d['bouton-texte'] }} !important; }
            .code { background: {{ $d['surface-douce'] }} !important; color: {{ $d['texte'] }} !important; border-color: {{ $d['bord'] }} !important; }
            .ligne td { border-color: {{ $d['bord'] }} !important; }
            .filet { border-color: {{ $d['bord'] }} !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background:{{ $c['fond'] }};-webkit-text-size-adjust:100%;" class="fond">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{{ $c['fond'] }};" class="fond">
        <tr>
            <td align="center" style="padding:32px 12px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;">
                    <tr>
                        <td style="padding:0 4px 20px;font-family:{!! $font !!};font-size:19px;font-weight:600;letter-spacing:-0.01em;color:{{ $c['texte'] }};" class="encre">
                            <img src="{{ asset('logo.png') }}" width="22" height="22" alt="" style="display:inline-block;width:22px;height:22px;border:0;vertical-align:-5px;margin-right:8px;">kekeli
                        </td>
                    </tr>
                    <tr>
                        <td style="background:{{ $c['surface'] }};border:1px solid {{ $c['bord'] }};border-radius:18px;padding:28px 24px;font-family:{!! $font !!};color:{{ $c['texte'] }};" class="carte encre">
                            <h1 style="margin:0 0 14px;font-size:21px;line-height:1.3;font-weight:600;letter-spacing:-0.01em;color:{{ $c['texte'] }};" class="encre">{{ $title }}</h1>
                            @foreach ($paragraphs as $paragraph)
                                <p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:{{ $c['texte'] }};" class="encre">{{ $paragraph }}</p>
                            @endforeach
                            @if ($code)
                                <p style="margin:20px 0;text-align:center;">
                                    <span style="display:inline-block;padding:14px 22px;border-radius:14px;border:1px solid {{ $c['bord'] }};background:{{ $c['surface-douce'] }};font-size:30px;font-weight:600;letter-spacing:8px;color:{{ $c['texte'] }};font-family:'SFMono-Regular',Menlo,Consolas,monospace;" class="code">{{ $code }}</span>
                                </p>
                            @endif
                            @if ($details)
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:6px 0 14px;font-size:14px;">
                                    @foreach ($details as $label => $value)
                                        <tr class="ligne">
                                            <td style="padding:9px 0;border-top:1px solid {{ $c['bord'] }};color:{{ $c['texte-doux'] }};width:42%;vertical-align:top;" class="doux">{{ $label }}</td>
                                            <td style="padding:9px 0;border-top:1px solid {{ $c['bord'] }};color:{{ $c['texte'] }};text-align:right;font-weight:600;vertical-align:top;" class="encre">{{ $value }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            @endif
                            @if ($button)
                                <p style="margin:22px 0 6px;">
                                    <a href="{{ $button['url'] }}" style="display:inline-block;padding:12px 22px;border-radius:999px;background:{{ $c['bouton'] }};color:{{ $c['bouton-texte'] }};font-size:15px;font-weight:600;text-decoration:none;" class="bouton">{{ $button['label'] }}</a>
                                </p>
                            @endif
                            @if ($note)
                                <p style="margin:18px 0 0;padding-top:14px;border-top:1px solid {{ $c['bord'] }};font-size:13px;line-height:1.55;color:{{ $c['texte-doux'] }};" class="doux filet">{{ $note }}</p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 4px 0;font-family:{!! $font !!};font-size:12px;line-height:1.7;color:{{ $c['texte-doux'] }};" class="doux">
                            Kekeli, un produit de {{ $footer['name'] }}@if ($footer['address']) · {{ $footer['address'] }}@endif<br>
                            @if ($footer['phones'])Téléphones : @foreach ($footer['phones'] as $phone)<span style="white-space:nowrap;">{{ $phone }}</span>@if (! $loop->last) · @endif @endforeach<br>@endif
                            @if ($footer['email'])E-mail : <a href="mailto:{{ $footer['email'] }}" style="color:{{ $c['texte-doux'] }};" class="doux">{{ $footer['email'] }}</a>@endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
