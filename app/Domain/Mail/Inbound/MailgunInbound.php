<?php

namespace App\Domain\Mail\Inbound;

use App\Domain\Mail\Support\InboundAttachment;
use App\Domain\Mail\Support\InboundEmail;
use Illuminate\Http\Request;
use Symfony\Component\Mime\Address;

/**
 * Mailgun's parsed inbound route POST (store/forward) → InboundEmail.
 *
 * @see https://documentation.mailgun.com/docs/mailgun/user-manual/receive-forward-store/
 */
class MailgunInbound
{
    /**
     * Mailgun signs every webhook with the account's HTTP webhook signing key.
     */
    public static function hasValidSignature(Request $request, string $signingKey): bool
    {
        $timestamp = $request->string('timestamp')->toString();
        $token = $request->string('token')->toString();
        $signature = $request->string('signature')->toString();

        if ($timestamp === '' || abs(time() - (int) $timestamp) > 15 * 60) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $timestamp.$token, $signingKey), $signature);
    }

    public static function toInboundEmail(Request $request): InboundEmail
    {
        $headers = [];

        foreach ((array) json_decode($request->string('message-headers')->toString(), true) as $header) {
            if (is_array($header) && count($header) === 2) {
                $headers[mb_strtolower((string) $header[0])] ??= (string) $header[1];
            }
        }

        // Mailgun also posts its own checks as top-level fields.
        foreach (['X-Mailgun-Dkim-Check-Result', 'X-Mailgun-Spf'] as $name) {
            if ($request->filled($name)) {
                $headers[mb_strtolower($name)] ??= $request->string($name)->toString();
            }
        }

        [$from] = self::addresses($request->string('from')->toString()) + [['email' => $request->string('sender')->lower()->toString(), 'name' => '']];

        $attachments = [];

        foreach ($request->allFiles() as $file) {
            foreach (is_array($file) ? $file : [$file] as $upload) {
                $attachments[] = InboundAttachment::fromContents(
                    $upload->getClientOriginalName(),
                    $upload->getClientMimeType() ?: 'application/octet-stream',
                    (string) file_get_contents($upload->getRealPath()),
                );
            }
        }

        return new InboundEmail(
            fromEmail: $from['email'],
            fromName: $from['name'],
            to: self::addresses($request->string('To')->toString() ?: ($headers['to'] ?? $request->string('recipient')->toString())),
            cc: self::addresses($request->string('Cc')->toString() ?: ($headers['cc'] ?? '')),
            subject: $request->string('subject')->toString(),
            text: $request->string('body-plain')->toString(),
            html: $request->string('body-html')->toString(),
            messageId: InboundEmail::normalizeId($request->string('Message-Id')->toString() ?: ($headers['message-id'] ?? null)),
            inReplyTo: InboundEmail::parseIds($request->string('In-Reply-To')->toString() ?: ($headers['in-reply-to'] ?? null))[0] ?? null,
            references: InboundEmail::parseIds($request->string('References')->toString() ?: ($headers['references'] ?? null)),
            headers: $headers,
            attachments: $attachments,
        );
    }

    /**
     * Parse an address header such as `"Ana" <ana@example.com>, bo@example.com`.
     *
     * @return list<array{email: string, name: string}>
     */
    private static function addresses(string $header): array
    {
        $addresses = [];

        foreach (str_getcsv($header, ',', '"', '\\') as $part) {
            try {
                $address = Address::create(trim((string) $part));
                $addresses[] = ['email' => mb_strtolower($address->getAddress()), 'name' => $address->getName()];
            } catch (\Throwable) {
                continue;
            }
        }

        return $addresses;
    }
}
