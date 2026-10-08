<?php

namespace App\Domain\Support;

use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;

/**
 * Sanitizes HTML produced by the rich text editor before it is stored.
 */
class RichText
{
    /**
     * @param  bool  $keepMentions  Keep @mentions and ticket references; only for staff internal notes.
     *                              Elsewhere they become plain text so agent links and user ids stay internal.
     */
    public static function sanitize(string $html, bool $keepMentions = false): string
    {
        $clean = trim((string) Purifier::clean($html, 'rich_text'));

        return $keepMentions ? $clean : Mentions::flatten($clean);
    }

    /**
     * Sanitize HTML from an incoming email, also dropping remote images.
     */
    public static function sanitizeInboundEmail(string $html): string
    {
        return trim((string) Purifier::clean($html, 'inbound_email'));
    }

    /**
     * Whether the sanitized HTML contains any visible content.
     */
    public static function isBlank(string $html): bool
    {
        return trim(html_entity_decode(strip_tags(Str::replace(['<img', '<hr'], ['x<img', 'x<hr'], $html)))) === '';
    }

    /**
     * Plain-text excerpt of the HTML, for previews and notifications.
     */
    public static function excerpt(string $html, int $limit = 140): string
    {
        $text = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(Str::replace('</p>', '</p> ', $html)))) ?? '';

        return Str::limit(trim($text), $limit);
    }

    /**
     * The HTML as readable plain text, keeping paragraphs, line breaks and list items: what an
     * AI model or an MCP client reads.
     */
    public static function toPlainText(string $html): string
    {
        $text = preg_replace(['/<br\s*\/?>/i', '/<li[^>]*>/i', '/<\/li>/i', '/<\/(p|div|h[1-6]|ul|ol|blockquote|pre|tr)>/i'], ["\n", '- ', "\n", "\n\n"], $html) ?? $html;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5);
        $text = preg_replace(['/[ \t\x{00A0}]+/u', '/ *\n */', '/\n{3,}/'], [' ', "\n", "\n\n"], $text) ?? $text;

        return trim($text);
    }

    /**
     * Plain text (from an AI model or an MCP client) as editor HTML: escaped, blank lines become
     * paragraphs and single line breaks become <br>. Text that already contains HTML tags is
     * returned as is, for sanitize() to clean.
     */
    public static function fromPlainText(string $text): string
    {
        if ($text !== strip_tags($text)) {
            return $text;
        }

        $paragraphs = preg_split('/\n\s*\n/', trim(str_replace("\r\n", "\n", $text))) ?: [];

        return collect($paragraphs)
            ->map(fn (string $paragraph): string => trim($paragraph))
            ->filter(fn (string $paragraph): bool => $paragraph !== '')
            ->map(fn (string $paragraph): string => '<p>'.nl2br(e($paragraph), false).'</p>')
            ->join('');
    }
}
