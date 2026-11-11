<?php

namespace App\WebApps;

use Illuminate\Support\Str;

/**
 * Name of a new site, guessed from the client's description (free: no request to the model).
 * The client can change it later on the project page.
 */
class SiteNames
{
    /** Kinds of activities often named in a description, with the name used when no proper name is found. */
    private const ACTIVITIES = [
        'restaurant' => 'Restaurant', 'maquis' => 'Maquis', 'bar' => 'Bar', 'hôtel' => 'Hôtel', 'hotel' => 'Hôtel',
        'résidence' => 'Résidence', 'salon de coiffure' => 'Salon de coiffure', 'salon' => 'Salon', 'boutique' => 'Boutique',
        'magasin' => 'Magasin', 'pressing' => 'Pressing', 'école' => 'École', 'ecole' => 'École', 'collège' => 'Collège',
        'lycée' => 'Lycée', 'église' => 'Église', 'eglise' => 'Église', 'association' => 'Association', 'ong' => 'ONG',
        'cabinet' => 'Cabinet', 'clinique' => 'Clinique', 'pharmacie' => 'Pharmacie', 'garage' => 'Garage',
        'agence' => 'Agence', 'atelier' => 'Atelier', 'entreprise' => 'Entreprise', 'société' => 'Société',
        'boulangerie' => 'Boulangerie', 'pâtisserie' => 'Pâtisserie', 'supermarché' => 'Supermarché', 'librairie' => 'Librairie',
        'centre' => 'Centre', 'studio' => 'Studio', 'cyber' => 'Cyber', 'imprimerie' => 'Imprimerie', 'portfolio' => 'Portfolio',
        'blog' => 'Blog', 'annuaire' => 'Annuaire',
    ];

    /** Small words allowed inside a proper name (« Le Palmier », « Chez Ama », « La Paix de Dieu »). */
    private const LINK_WORDS = ['de', 'du', 'des', 'la', 'le', 'les', 'l’', "l'", 'd’', "d'", 'et', '&'];

    /**
     * @return array{name: string, sure: bool} « sure » is true when the client wrote the name itself.
     */
    public function guess(string $description): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $description) ?? '');

        // 1. Un nom entre guillemets : « Le Palmier », "Le Palmier", “Le Palmier”.
        if (preg_match('/[«“"]\s*([^«»“”"]{2,60}?)\s*[»”"]/u', $text, $m)) {
            return ['name' => $this->withActivity(trim($m[1]), $text), 'sure' => true];
        }

        // 2. « s'appelle Le Palmier », « nommé Chez Ama », « appelée Bel Avenir ».
        if (preg_match('/\b(?:s[’\']appelle|appel[ée]e?s?|nomm[ée]e?s?)\s+(.+)$/iu', $text, $m) && ($name = $this->properName($m[1]))) {
            return ['name' => $this->withActivity($name, $text), 'sure' => true];
        }

        // 3. Une activité suivie d'un nom propre : « mon restaurant Le Palmier à Lomé ».
        foreach (self::ACTIVITIES as $word => $label) {
            if (preg_match('/\b'.preg_quote($word, '/').'\s+(.+)$/iu', $text, $m) && ($name = $this->properName($m[1]))) {
                return ['name' => Str::limit($label.' '.$name, 100, ''), 'sure' => true];
            }
        }

        // 4. Seulement l'activité : « Mon restaurant », que le client pourra renommer.
        foreach (self::ACTIVITIES as $word => $label) {
            if (preg_match('/\b'.preg_quote($word, '/').'\b/iu', $text)) {
                return ['name' => $this->possessive($label), 'sure' => false];
            }
        }

        return ['name' => __('My website'), 'sure' => false];
    }

    /**
     * Proper name at the start of a piece of text: words with a capital letter, with small link words between them.
     */
    private function properName(string $text): ?string
    {
        $words = [];
        foreach (preg_split('/\s+/u', trim($text)) as $word) {
            $clean = trim($word, ' ,.;:!?()');
            $isLink = in_array(Str::lower($clean), self::LINK_WORDS, true);
            $isCapital = $clean !== '' && preg_match('/^\p{Lu}/u', $clean);
            if (! $isCapital && ! ($isLink && $words !== [])) {
                break;
            }
            $words[] = $clean;
            if ($clean !== $word || count($words) >= 5) {
                break;
            }
        }
        while ($words !== [] && in_array(Str::lower(end($words)), self::LINK_WORDS, true)) {
            array_pop($words);
        }

        // « Lomé », « Togo » seuls ne sont pas un nom de site.
        $name = implode(' ', $words);

        return $words === [] || in_array($name, ['Lomé', 'Togo', 'Kara', 'Kpalimé', 'Sokodé', 'Atakpamé', 'Aného'], true) ? null : $name;
    }

    /**
     * « Le Palmier » for a restaurant becomes « Restaurant Le Palmier » (unless the activity is already in the name).
     */
    private function withActivity(string $name, string $text): string
    {
        foreach (self::ACTIVITIES as $word => $label) {
            if (preg_match('/\b'.preg_quote($word, '/').'\b/iu', $text)) {
                return Str::limit(preg_match('/\b'.preg_quote($word, '/').'\b/iu', $name) ? $name : $label.' '.$name, 100, '');
            }
        }

        return Str::limit($name, 100, '');
    }

    /**
     * « Mon restaurant », « Ma boutique », « Mon école »…
     */
    private function possessive(string $label): string
    {
        $feminine = ['Boutique', 'École', 'Église', 'Association', 'ONG', 'Clinique', 'Pharmacie', 'Agence', 'Entreprise', 'Société', 'Boulangerie', 'Pâtisserie', 'Librairie', 'Imprimerie', 'Résidence'];
        $lower = in_array($label, ['ONG'], true) ? $label : Str::lower($label);
        $vowel = preg_match('/^[aeiouéèêh]/iu', $lower);

        return (in_array($label, $feminine, true) && ! $vowel ? 'Ma ' : 'Mon ').$lower;
    }
}
