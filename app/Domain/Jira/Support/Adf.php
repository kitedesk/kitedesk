<?php

namespace App\Domain\Jira\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Converts between the HTML of ticket messages (what the rich-text editor writes) and Jira's
 * Atlassian Document Format (ADF), which Jira Cloud uses for descriptions and comments.
 *
 * Only what the editor produces is mapped closely: paragraphs, headings, bold, italic,
 * strikethrough, code, links, lists, quotes, code blocks and line breaks. Anything else
 * becomes its text, so nothing is lost even if it looks plainer.
 */
final class Adf
{
    /**
     * @return array{type: 'doc', version: 1, content: list<array<string, mixed>>}
     */
    public static function fromHtml(string $html): array
    {
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="utf-8"?><body>'.$html.'</body>', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NODEFDTD);
        $body = $document->getElementsByTagName('body')->item(0);

        return self::document($body !== null ? self::blocks($body) : []);
    }

    /**
     * A document from blocks (see `paragraph()`).
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return array{type: 'doc', version: 1, content: list<array<string, mixed>>}
     */
    public static function document(array $blocks): array
    {
        return ['type' => 'doc', 'version' => 1, 'content' => $blocks];
    }

    /**
     * A paragraph of text, optionally a link.
     *
     * @return array<string, mixed>
     */
    public static function paragraph(string $text, ?string $href = null, bool $strong = false): array
    {
        $marks = [
            ...($strong ? [['type' => 'strong']] : []),
            ...($href !== null ? [['type' => 'link', 'attrs' => ['href' => $href]]] : []),
        ];

        return $text === ''
            ? ['type' => 'paragraph']
            : ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text, ...($marks !== [] ? ['marks' => $marks] : [])]]];
    }

    /**
     * HTML for a Jira description or comment. Pass it through `RichText::sanitize()` before storing it.
     *
     * @param  array<string, mixed>|null  $document
     */
    public static function toHtml(?array $document): string
    {
        return $document === null ? '' : self::nodesToHtml(self::children($document));
    }

    /**
     * Block nodes for the children of an element; loose inline content becomes paragraphs.
     *
     * @return list<array<string, mixed>>
     */
    private static function blocks(DOMNode $parent): array
    {
        $blocks = [];
        $inline = [];

        $flush = function () use (&$blocks, &$inline): void {
            $content = self::trimInline($inline);

            if ($content !== []) {
                $blocks[] = ['type' => 'paragraph', 'content' => $content];
            }

            $inline = [];
        };

        foreach ($parent->childNodes as $node) {
            $block = $node instanceof DOMElement ? self::block($node) : null;

            if ($block === null) {
                $inline = [...$inline, ...self::inline($node, [])];

                continue;
            }

            $flush();
            $blocks = [...$blocks, ...$block];
        }

        $flush();

        return $blocks;
    }

    /**
     * The blocks for a block-level element, or null when it is inline.
     *
     * @return list<array<string, mixed>>|null
     */
    private static function block(DOMElement $element): ?array
    {
        $tag = strtolower($element->tagName);

        return match (true) {
            $tag === 'p' => [self::withContent(['type' => 'paragraph'], self::trimInline(self::inlineChildren($element, [])))],
            (bool) preg_match('/^h([1-6])$/', $tag, $level) => [self::withContent(['type' => 'heading', 'attrs' => ['level' => (int) $level[1]]], self::trimInline(self::inlineChildren($element, [])))],
            $tag === 'ul', $tag === 'ol' => [self::list($element, $tag === 'ul' ? 'bulletList' : 'orderedList')],
            $tag === 'blockquote' => [self::withContent(['type' => 'blockquote'], self::blocks($element) ?: [['type' => 'paragraph']])],
            $tag === 'pre' => [self::withContent(['type' => 'codeBlock'], $element->textContent === '' ? [] : [['type' => 'text', 'text' => rtrim($element->textContent, "\n")]])],
            $tag === 'hr' => [['type' => 'rule']],
            in_array($tag, ['div', 'section', 'article', 'li'], true) => self::blocks($element),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function list(DOMElement $element, string $type): array
    {
        $items = [];

        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement && strtolower($child->tagName) === 'li') {
                $items[] = ['type' => 'listItem', 'content' => self::blocks($child) ?: [['type' => 'paragraph']]];
            }
        }

        return ['type' => $type, 'content' => $items ?: [['type' => 'listItem', 'content' => [['type' => 'paragraph']]]]];
    }

    /**
     * @param  list<array<string, mixed>>  $marks
     * @return list<array<string, mixed>>
     */
    private static function inlineChildren(DOMNode $parent, array $marks): array
    {
        $content = [];

        foreach ($parent->childNodes as $child) {
            $content = [...$content, ...self::inline($child, $marks)];
        }

        return $content;
    }

    /**
     * @param  list<array<string, mixed>>  $marks
     * @return list<array<string, mixed>>
     */
    private static function inline(DOMNode $node, array $marks): array
    {
        if ($node instanceof DOMText) {
            $text = preg_replace('/\s+/u', ' ', $node->textContent) ?? '';

            return $text === '' ? [] : [['type' => 'text', 'text' => $text, ...($marks !== [] ? ['marks' => $marks] : [])]];
        }

        if (! $node instanceof DOMElement) {
            return [];
        }

        $tag = strtolower($node->tagName);

        if ($tag === 'br') {
            return [['type' => 'hardBreak']];
        }

        // Mentions are written as <span data-type="mention" data-label="Name">.
        if ($node->getAttribute('data-type') === 'mention') {
            $label = $node->getAttribute('data-label') ?: ltrim($node->textContent, '@');

            return [['type' => 'text', 'text' => '@'.$label, ...($marks !== [] ? ['marks' => $marks] : [])]];
        }

        $mark = match ($tag) {
            'strong', 'b' => ['type' => 'strong'],
            'em', 'i' => ['type' => 'em'],
            's', 'del', 'strike' => ['type' => 'strike'],
            'u' => ['type' => 'underline'],
            'code' => ['type' => 'code'],
            'a' => self::linkMark($node->getAttribute('href')),
            default => null,
        };

        return self::inlineChildren($node, $mark !== null ? [...$marks, $mark] : $marks);
    }

    /**
     * @return array{type: 'link', attrs: array{href: string}}|null
     */
    private static function linkMark(string $href): ?array
    {
        return preg_match('/^(https?:|mailto:)/i', $href) === 1 ? ['type' => 'link', 'attrs' => ['href' => $href]] : null;
    }

    /**
     * Drops the spaces at the edges of a paragraph that came from the HTML's line breaks.
     *
     * @param  list<array<string, mixed>>  $content
     * @return list<array<string, mixed>>
     */
    private static function trimInline(array $content): array
    {
        foreach ([0, count($content) - 1] as $index) {
            if (($content[$index]['type'] ?? null) === 'text') {
                $content[$index]['text'] = $index === 0 ? ltrim($content[$index]['text']) : rtrim($content[$index]['text']);
            }
        }

        return array_values(array_filter($content, fn (array $node): bool => $node['type'] !== 'text' || $node['text'] !== ''));
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<array<string, mixed>>  $content
     * @return array<string, mixed>
     */
    private static function withContent(array $node, array $content): array
    {
        return $content === [] ? $node : [...$node, 'content' => $content];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<array<string, mixed>>
     */
    private static function children(array $node): array
    {
        return array_values(array_filter(is_array($node['content'] ?? null) ? $node['content'] : [], is_array(...)));
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    private static function nodesToHtml(array $nodes): string
    {
        return implode('', array_map(self::nodeToHtml(...), $nodes));
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function nodeToHtml(array $node): string
    {
        $inner = fn (): string => self::nodesToHtml(self::children($node));
        $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];

        return match ($node['type'] ?? null) {
            'paragraph' => '<p>'.$inner().'</p>',
            // The editor only has level 2 and 3 headings.
            'heading' => sprintf('<h%1$d>%2$s</h%1$d>', min(3, max(2, (int) ($attrs['level'] ?? 2))), $inner()),
            'bulletList' => '<ul>'.$inner().'</ul>',
            'orderedList' => '<ol>'.$inner().'</ol>',
            'listItem' => '<li>'.$inner().'</li>',
            'blockquote', 'panel' => '<blockquote>'.$inner().'</blockquote>',
            'codeBlock' => '<pre><code>'.e(self::plainText($node)).'</code></pre>',
            'rule' => '<hr>',
            'hardBreak' => '<br>',
            'text' => self::textToHtml($node),
            'mention' => e((string) ($attrs['text'] ?? '@'.($attrs['displayName'] ?? ''))),
            'emoji' => e((string) ($attrs['text'] ?? $attrs['shortName'] ?? '')),
            'status' => e((string) ($attrs['text'] ?? '')),
            'date' => isset($attrs['timestamp']) ? e(date('Y-m-d', (int) ((int) $attrs['timestamp'] / 1000))) : '',
            'inlineCard', 'blockCard', 'embedCard' => isset($attrs['url']) ? self::link((string) $attrs['url'], e((string) $attrs['url'])) : '',
            'mediaSingle', 'mediaGroup', 'media', 'mediaInline' => '<p><em>'.e(__('[attachment in Jira]')).'</em></p>',
            'table' => $inner(),
            'tableRow' => '<p>'.implode(' | ', array_map(fn (array $cell): string => strip_tags(self::nodesToHtml(self::children($cell)), '<strong><em><s><code><a>'), self::children($node))).'</p>',
            default => $inner(),
        };
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function textToHtml(array $node): string
    {
        $html = e((string) ($node['text'] ?? ''));

        foreach (is_array($node['marks'] ?? null) ? $node['marks'] : [] as $mark) {
            $html = match ($mark['type'] ?? null) {
                'strong' => "<strong>{$html}</strong>",
                'em' => "<em>{$html}</em>",
                'strike' => "<s>{$html}</s>",
                'underline' => "<u>{$html}</u>",
                'code' => "<code>{$html}</code>",
                'link' => self::link((string) ($mark['attrs']['href'] ?? ''), $html),
                default => $html,
            };
        }

        return $html;
    }

    private static function link(string $href, string $html): string
    {
        return preg_match('/^(https?:|mailto:)/i', $href) === 1 ? '<a href="'.e($href).'">'.$html.'</a>' : $html;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function plainText(array $node): string
    {
        return ($node['type'] ?? null) === 'text'
            ? (string) ($node['text'] ?? '')
            : implode('', array_map(self::plainText(...), self::children($node)));
    }
}
