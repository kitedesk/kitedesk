<?php

namespace App\Domain\Mail\Inbound;

use App\Domain\Mail\Support\InboundAttachment;
use App\Domain\Mail\Support\InboundEmail;

/**
 * Postmark's inbound webhook JSON → InboundEmail.
 *
 * @see https://postmarkapp.com/developer/webhooks/inbound-webhook
 */
class PostmarkInbound
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function toInboundEmail(array $payload): InboundEmail
    {
        $headers = [];

        foreach ((array) ($payload['Headers'] ?? []) as $header) {
            if (is_array($header) && isset($header['Name'], $header['Value'])) {
                $headers[mb_strtolower((string) $header['Name'])] ??= (string) $header['Value'];
            }
        }

        $from = (array) ($payload['FromFull'] ?? []);

        return new InboundEmail(
            fromEmail: mb_strtolower((string) ($from['Email'] ?? $payload['From'] ?? '')),
            fromName: (string) ($from['Name'] ?? $payload['FromName'] ?? ''),
            to: self::addresses($payload['ToFull'] ?? []),
            cc: self::addresses($payload['CcFull'] ?? []),
            subject: (string) ($payload['Subject'] ?? ''),
            text: (string) ($payload['TextBody'] ?? ''),
            html: (string) ($payload['HtmlBody'] ?? ''),
            messageId: InboundEmail::normalizeId($headers['message-id'] ?? (isset($payload['MessageID']) ? $payload['MessageID'].'@postmark' : null)),
            inReplyTo: InboundEmail::parseIds($headers['in-reply-to'] ?? null)[0] ?? null,
            references: InboundEmail::parseIds($headers['references'] ?? null),
            headers: $headers,
            attachments: array_values(array_map(
                fn (array $attachment): InboundAttachment => InboundAttachment::fromContents(
                    (string) ($attachment['Name'] ?? 'attachment'),
                    (string) ($attachment['ContentType'] ?? 'application/octet-stream'),
                    (string) base64_decode((string) ($attachment['Content'] ?? ''), true),
                ),
                array_filter((array) ($payload['Attachments'] ?? []), 'is_array'),
            )),
        );
    }

    /**
     * @return list<array{email: string, name: string}>
     */
    private static function addresses(mixed $list): array
    {
        return array_values(array_map(fn (array $address): array => [
            'email' => mb_strtolower((string) ($address['Email'] ?? '')),
            'name' => (string) ($address['Name'] ?? ''),
        ], array_filter((array) $list, 'is_array')));
    }
}
