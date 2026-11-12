<?php

namespace App\Agent;

use Illuminate\Support\HtmlString;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;

/**
 * An answer of Kekeli written in Markdown, shown like the answers of Claude or ChatGPT: titles, bold, italic, lists,
 * tables that scroll sideways on a phone, and code blocks with a « Copier » button.
 *
 * Safe by construction: raw HTML written by the model is escaped (never interpreted), dangerous addresses are refused,
 * and the links and images written by the model are turned into plain text (only the sources kept by Kekeli become
 * links, as small numbered pills). French typography is added to the text only, never inside code.
 */
class Markdown
{
    private static ?MarkdownConverter $converter = null;

    /**
     * @param  array<int, mixed>  $sources
     */
    public static function render(?string $text, array $sources = []): HtmlString
    {
        $html = self::converter()->convert(self::prepare((string) $text))->getContent();

        $html = self::decorate($html, $sources);

        return new HtmlString($html);
    }

    /**
     * The text before Markdown: emoji and invisible spaces go, like everywhere in Kekeli.
     */
    public static function prepare(string $text): string
    {
        $text = str_replace(["\u{00A0}", "\u{202F}", "\u{2007}", "\u{2060}", "\u{200B}"], ' ', $text);
        $text = str_replace(['&nbsp;', '&#160;', '&#8239;'], ' ', $text);
        $text = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}\x{200D}\x{20E3}]/u', '', $text) ?? $text;

        return trim($text);
    }

    private static function converter(): MarkdownConverter
    {
        if (self::$converter) {
            return self::$converter;
        }

        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
            'renderer' => ['soft_break' => "\n"],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new TableExtension);
        $environment->addExtension(new StrikethroughExtension);

        // Jamais de lien ni d'image écrits par le modèle : ils deviennent du texte (le nom du lien, la légende de l'image).
        $environment->addEventListener(DocumentParsedEvent::class, function (DocumentParsedEvent $event) {
            $walker = $event->getDocument()->walker();
            $found = [];
            while ($step = $walker->next()) {
                $node = $step->getNode();
                if ($step->isEntering() && ($node instanceof Link || $node instanceof Image)) {
                    $found[] = $node;
                }
            }
            foreach (array_reverse($found) as $node) {
                if ($node instanceof Image) {
                    $node->replaceWith(new Text(trim((string) $node->getTitle()) !== '' ? (string) $node->getTitle() : self::childText($node)));

                    continue;
                }
                foreach ($node->children() as $child) {
                    $node->insertBefore($child);
                }
                $node->detach();
            }
        });

        return self::$converter = new MarkdownConverter($environment);
    }

    private static function childText(Node $node): string
    {
        $text = '';
        foreach ($node->children() as $child) {
            $text .= $child instanceof Text ? $child->getLiteral() : self::childText($child);
        }

        return $text;
    }

    /**
     * Tables in a box that scrolls sideways, code blocks with their « Copier » button, French typography and source
     * pills in the text (never inside code).
     *
     * @param  array<int, mixed>  $sources
     */
    private static function decorate(string $html, array $sources): string
    {
        // Le texte seul reçoit la typographie française et les pastilles des sources ; jamais le code ni les balises.
        $parts = preg_split('#(<[^>]+>)#', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $code = 0;
        foreach ($parts as $index => $part) {
            if (str_starts_with($part, '<')) {
                if (preg_match('#^<(pre|code)\b#', $part)) {
                    $code++;
                } elseif (preg_match('#^</(pre|code)>#', $part)) {
                    $code = max(0, $code - 1);
                }

                continue;
            }
            if ($code === 0) {
                $parts[$index] = RunReport::citationPills(RunReport::typography($part), $sources);
            }
        }

        $html = implode('', $parts);

        $html = str_replace(['<table>', '</table>'], ['<div class="kekeli-tableau" data-markdown-table><table>', '</table></div>'], $html);

        $html = preg_replace_callback('#<pre><code(?: class="language-([\w+.-]{1,30})")?>(.*?)</code></pre>#s', function (array $match) {
            $language = $match[1] ?? '';

            return '<div class="kekeli-code" x-data="{ copie: false }" data-markdown-code>'
                .'<div class="kekeli-code-barre"><span>'.e($language).'</span>'
                .'<button type="button" class="kekeli-code-copier" data-copy-code'
                .' x-on:click="const t = $root.querySelector(\'code\').innerText; const fini = () => { copie = true; setTimeout(() => copie = false, 2000) }; if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(t).then(fini) } else { const z = document.createElement(\'textarea\'); z.value = t; document.body.appendChild(z); z.select(); document.execCommand(\'copy\'); z.remove(); fini() }"'
                .' x-text="copie ? '.e(json_encode(__('Copied'))).' : '.e(json_encode(__('Copy'))).'">'.e(__('Copy')).'</button></div>'
                .'<pre><code>'.$match[2].'</code></pre></div>';
        }, $html) ?? $html;

        return $html;
    }
}
