<?php

namespace App\Domain\Mail\Support;

use Illuminate\Support\Str;

/**
 * Extracts the new part of an emailed reply, dropping the quoted conversation below it.
 */
class ReplyParser
{
    /**
     * Line we put at the top of every outgoing email; everything below it in a reply is quoted history.
     */
    public const string MARKER = '##- Please type your reply above this line -##';

    /**
     * Where mail clients start the quoted original in HTML replies.
     */
    private const array HTML_QUOTE_STARTS = [
        '<div class="gmail_quote',
        '<blockquote type="cite"',
        '<div id="appendonsend"',
        '<div id="divRplyFwdMsg"',
        '<hr id="stopSpelling"',
        '<div class="moz-cite-prefix"',
        '<div class="yahoo_quoted"',
    ];

    /**
     * The reply as HTML, from the HTML part when there is one, else from the plain text.
     */
    public static function body(InboundEmail $email): string
    {
        $html = trim($email->html) !== '' ? self::fromHtml($email->html) : '';

        if (trim(strip_tags($html)) !== '') {
            return $html;
        }

        $text = self::fromText($email->text !== '' ? $email->text : strip_tags($email->html));

        return $text !== '' ? self::textToHtml($text) : '';
    }

    public static function fromHtml(string $html): string
    {
        if (preg_match('/<body[^>]*>(.*)<\/body>/is', $html, $matches) === 1) {
            $html = $matches[1];
        }

        $cut = collect([...self::markers(), ...self::HTML_QUOTE_STARTS])
            ->map(fn (string $needle): int|false => stripos($html, $needle))
            ->filter(fn (int|false $position): bool => $position !== false)
            ->min();

        if ($cut !== null) {
            $html = substr($html, 0, $cut);
        }

        // "On Mon, 5 Oct 2026, Ana <ana@example.com> wrote:" left dangling above the cut.
        return trim((string) preg_replace('/(?:On|Em)\s[^<]{1,300}?(?:wrote|escreveu):\s*(?:<\/?[a-z][^>]*>\s*)*$/iu', '', trim($html)));
    }

    public static function fromText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        foreach (self::markers() as $marker) {
            if (($position = stripos($text, $marker)) !== false) {
                $text = substr($text, 0, $position);
            }
        }

        $text = (string) preg_replace('/^(?:On|Em)\s[\s\S]{1,300}?(?:wrote|escreveu):\s*$[\s\S]*/mu', '', $text);
        $text = (string) preg_replace('/^>.*$[\s\S]*/m', '', $text);
        $text = (string) preg_replace('/^-- $[\s\S]*/m', '', $text);

        return trim($text);
    }

    /**
     * @return list<string>
     */
    public static function markers(): array
    {
        return array_values(array_unique([self::MARKER, __(self::MARKER)]));
    }

    private static function textToHtml(string $text): string
    {
        return collect(preg_split('/\n{2,}/', $text) ?: [])
            ->map(fn (string $paragraph): string => '<p>'.nl2br(e(trim($paragraph)), false).'</p>')
            ->implode('');
    }

    /**
     * Human-readable sender name for a new account.
     */
    public static function nameFor(string $email, string $name): string
    {
        return trim($name) !== '' ? trim($name) : Str::headline(Str::before($email, '@'));
    }
}
