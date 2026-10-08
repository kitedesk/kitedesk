<?php

namespace App\Domain\Support;

use Dom\Element;
use Dom\HTMLDocument;

/**
 * Reads and removes the agent-only markup the reply editor produces: @mentions
 * (span[data-type=mention]) and ticket references (a[data-type=ticket]).
 */
class Mentions
{
    private const string SELECTOR = 'span[data-type="mention"], a[data-type="ticket"]';

    /**
     * Ids of the users mentioned in sanitized message HTML, without duplicates.
     *
     * @return list<int>
     */
    public static function userIds(string $html): array
    {
        if (! str_contains($html, 'data-type="mention"')) {
            return [];
        }

        $ids = [];

        foreach (self::parse($html)->querySelectorAll('span[data-type="mention"][data-id]') as $mention) {
            $id = (int) $mention->getAttribute('data-id');

            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /**
     * Replace mentions and ticket references with their plain text, for content customers can see.
     */
    public static function flatten(string $html): string
    {
        if (! str_contains($html, 'data-type=')) {
            return $html;
        }

        $document = self::parse($html);

        foreach ($document->querySelectorAll(self::SELECTOR) as $element) {
            /** @var Element $element */
            $element->replaceWith($document->createTextNode($element->textContent ?? ''));
        }

        return $document->body->innerHTML ?? '';
    }

    private static function parse(string $html): HTMLDocument
    {
        return HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NOERROR);
    }
}
