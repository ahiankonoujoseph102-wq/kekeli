{{--
    Gabarit de tous les e-mails de Kekeli : sobre, lisible sur un téléphone, mode sombre des messageries qui le
    permettent ; styles écrits dans chaque balise (les messageries ignorent souvent les feuilles de style).
--}}
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
            .fond { background: #171513 !important; }
            .carte { background: #211e1b !important; border-color: #3f3b35 !important; }
            .encre { color: #f3f0ea !important; }
            .doux { color: #a8a196 !important; }
            .bouton { background: #f3f0ea !important; color: #171513 !important; }
            .code { background: #2b2723 !important; color: #f3f0ea !important; }
            .ligne td { border-color: #3f3b35 !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background:#FAF8F4;" class="fond">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FAF8F4;" class="fond">
        <tr>
            <td align="center" style="padding:28px 12px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;">
                    <tr>
                        <td style="padding:0 6px 18px;font-family:Inter,'Segoe UI',Helvetica,Arial,sans-serif;font-size:20px;font-weight:700;color:#1C1A17;" class="encre">
                            <span style="display:inline-block;width:14px;height:14px;border-radius:50%;background:#E3A008;vertical-align:-1px;margin-right:8px;"></span>kekeli
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#FFFFFF;border:1px solid #E7E2D9;border-radius:16px;padding:26px 24px;font-family:Inter,'Segoe UI',Helvetica,Arial,sans-serif;color:#1C1A17;" class="carte encre">
                            <h1 style="margin:0 0 14px;font-size:21px;line-height:1.3;font-weight:700;color:#1C1A17;" class="encre">{{ $title }}</h1>
                            @foreach ($paragraphs as $paragraph)
                                <p style="margin:0 0 12px;font-size:15px;line-height:1.55;color:#1C1A17;" class="encre">{{ $paragraph }}</p>
                            @endforeach
                            @if ($code)
                                <p style="margin:18px 0;text-align:center;">
                                    <span style="display:inline-block;padding:14px 22px;border-radius:12px;background:#F1EDE6;font-size:30px;font-weight:700;letter-spacing:8px;color:#1C1A17;font-family:'SFMono-Regular',Menlo,Consolas,monospace;" class="code">{{ $code }}</span>
                                </p>
                            @endif
                            @if ($details)
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:6px 0 14px;font-size:14px;">
                                    @foreach ($details as $label => $value)
                                        <tr class="ligne">
                                            <td style="padding:8px 0;border-top:1px solid #E7E2D9;color:#6B655C;width:42%;vertical-align:top;" class="doux">{{ $label }}</td>
                                            <td style="padding:8px 0;border-top:1px solid #E7E2D9;color:#1C1A17;text-align:right;font-weight:600;vertical-align:top;" class="encre">{{ $value }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            @endif
                            @if ($button)
                                <p style="margin:20px 0 6px;">
                                    <a href="{{ $button['url'] }}" style="display:inline-block;padding:12px 22px;border-radius:999px;background:#1C1A17;color:#FFFFFF;font-size:15px;font-weight:600;text-decoration:none;" class="bouton">{{ $button['label'] }}</a>
                                </p>
                            @endif
                            @if ($note)
                                <p style="margin:16px 0 0;font-size:13px;line-height:1.5;color:#6B655C;" class="doux">{{ $note }}</p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 6px 0;font-family:Inter,'Segoe UI',Helvetica,Arial,sans-serif;font-size:12px;line-height:1.6;color:#6B655C;" class="doux">
                            Kekeli, un produit de {{ $footer['name'] }}@if ($footer['address']) · {{ $footer['address'] }}@endif<br>
                            @if ($footer['phones'])Téléphones : {{ implode(' · ', $footer['phones']) }}<br>@endif
                            @if ($footer['email'])E-mail : {{ $footer['email'] }}@endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
