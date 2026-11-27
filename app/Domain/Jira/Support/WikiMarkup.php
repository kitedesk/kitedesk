<?php

namespace App\Domain\Jira\Support;

/**
 * Converts Jira wiki markup (the older text format, still used in some webhook payloads) to
 * HTML. Covers what comments commonly use: paragraphs, line breaks, bold, italic, monospace,
 * links, bullet and numbered lists, headings and code blocks. Anything else stays as text.
 * Pass the result through `RichText::sanitize()` before storing it.
 */
final class WikiMarkup
{
    public static function toHtml(string $markup): string
    {
        $blocks = [];
        $code = [];

        // Code blocks first, so nothing inside them is read as markup.
        $markup = (string) preg_replace_callback('/\{(code|noformat)(?::[^}]*)?\}(.*?)\{\1\}/s', function (array $match) use (&$code): string {
            $code[] = '<pre><code>'.e(trim($match[2], "\n")).'</code></pre>';

            return "\n\n\u{E000}".(count($code) - 1)."\u{E000}\n\n";
        }, str_replace("\r\n", "\n", $markup));

        foreach (preg_split('/\n{2,}/', trim($markup)) ?: [] as $block) {
            $blocks[] = self::block(trim($block, "\n"), $code);
        }

        return implode('', $blocks);
    }

    /**
     * @param  list<string>  $code
     */
    private static function block(string $block, array $code): string
    {
        if (preg_match('/^\x{E000}(\d+)\x{E000}$/u', $block, $match) === 1) {
            return $code[(int) $match[1]] ?? '';
        }

        if (preg_match('/^h([1-6])\.\s+(.*)$/s', $block, $match) === 1) {
            $level = min(3, max(2, (int) $match[1]));

            return "<h{$level}>".self::inline($match[2])."</h{$level}>";
        }

        $lines = explode("\n", $block);

        if (preg_match('/^([*#])\s/', $lines[0], $marker) === 1 && collect($lines)->every(fn (string $line): bool => preg_match('/^[*#]+\s/', $line) === 1)) {
            $tag = $marker[1] === '#' ? 'ol' : 'ul';
            $items = array_map(fn (string $line): string => '<li>'.self::inline((string) preg_replace('/^[*#]+\s+/', '', $line)).'</li>', $lines);

            return "<{$tag}>".implode('', $items)."</{$tag}>";
        }

        return '<p>'.implode('<br>', array_map(self::inline(...), $lines)).'</p>';
    }

    private static function inline(string $text): string
    {
        $html = e($text);

        // [text|https://…] and [https://…]; other targets (user mentions, anchors) stay as text.
        $html = (string) preg_replace_callback('/\[(?:([^\]|]*)\|)?((?:https?:|mailto:)[^\]\s]+)\]/', fn (array $match): string => '<a href="'.$match[2].'">'.($match[1] !== '' ? $match[1] : $match[2]).'</a>', $html);
        $html = (string) preg_replace('/\{\{(.+?)\}\}/', '<code>$1</code>', $html);
        $html = (string) preg_replace('/(?<![\w*])\*(?=\S)(.+?)(?<=\S)\*(?![\w*])/', '<strong>$1</strong>', $html);

        return (string) preg_replace('/(?<![\w_])_(?=\S)(.+?)(?<=\S)_(?![\w_])/', '<em>$1</em>', $html);
    }
}
