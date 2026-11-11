<?php

namespace App\Agent;

/**
 * Whether a message of the client is a question (Kekeli answers without changing the site), a request for a change
 * (Kekeli works on the site), or unclear (Kekeli answers, then asks whether it should change the site), like Lovable
 * and Claude Code. Free: no request to the model.
 */
class MessageIntent
{
    public const CHANGE = 'change';

    public const QUESTION = 'question';

    public const DOUBT = 'doubt';

    /** Words that start a question. */
    private const QUESTION_STARTS = '/(*UCP)^(où|ou est|ou sont|combien|comment|pourquoi|quand|quel|quelle|quels|quelles|qui|que|qu[\'’]est[- ]ce|est[- ]ce qu|c[\'’]est quoi|à quoi|a quoi|peut[- ]on|puis[- ]je|dois[- ]je|faut[- ]il|y a[- ]t[- ]il|explique|expliquez|dis[- ]moi|dites[- ]moi|quelle est|quel est)\b/u';

    /** Verbs that ask for a change of the site. */
    private const CHANGE_VERBS = '/(*UCP)\b(ajout\w*|chang\w*|modifi\w*|met|mets|mettez|mettre|cré\w*|cree\w*|supprim\w*|enlev\w*|enlèv\w*|retir\w*|corrig\w*|remplac\w*|rends|rendez|rendre|tradui\w*|déplac\w*|deplac\w*|agrandi\w*|rédui\w*|redui\w*|fais|faites|faire|refai\w*|utilis\w*|intègr\w*|integr\w*|insèr\w*|inser\w*|affich\w*|écri\w*|ecri\w*|rédig\w*|redig\w*|amélior\w*|amelior\w*|install\w*|publi\w*|construi\w*|génèr\w*|gener\w*|transform\w*|renomm\w*|colori\w*|remets|remettez|répar\w*|repar\w*)\b/u';

    /** A polite request: « Peux-tu ajouter… ? » is a request for a change, not a question. */
    private const REQUEST_FORMS = '/(*UCP)\b(peux[- ]tu|pouvez[- ]vous|tu peux|vous pouvez|pourrais[- ]tu|pourriez[- ]vous|tu pourrais|vous pourriez|je veux|je voudrais|j[\'’]aimerais|merci de|il faut que tu|est[- ]ce que tu peux|est[- ]ce que vous pouvez)\b/u';

    public function detect(string $message): string
    {
        $text = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $message)));
        if ($text === '') {
            return self::CHANGE;
        }

        $asks = str_ends_with(rtrim($text, " .!\u{00A0}"), '?') || preg_match(self::QUESTION_STARTS, $text);
        if (! $asks) {
            return self::CHANGE;
        }

        $change = (bool) preg_match(self::CHANGE_VERBS, $text);
        if ($change && preg_match(self::REQUEST_FORMS, $text)) {
            return self::CHANGE;
        }

        return $change ? self::DOUBT : self::QUESTION;
    }
}
