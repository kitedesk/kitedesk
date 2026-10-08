<?php

namespace App\Domain\Mail\Support;

use App\Domain\Mail\Enums\SenderVerification;
use Illuminate\Support\Str;

/**
 * An incoming email, normalized from IMAP or a provider webhook.
 */
final readonly class InboundEmail
{
    /**
     * @param  list<array{email: string, name: string}>  $to
     * @param  list<array{email: string, name: string}>  $cc
     * @param  list<string>  $references  Message-IDs without angle brackets, oldest first.
     * @param  array<string, string>  $headers  Lower-case header name => value.
     * @param  list<InboundAttachment>  $attachments
     */
    public function __construct(
        public string $fromEmail,
        public string $fromName,
        public array $to,
        public array $cc,
        public string $subject,
        public string $text,
        public string $html,
        public ?string $messageId,
        public ?string $inReplyTo,
        public array $references,
        public array $headers = [],
        public array $attachments = [],
    ) {}

    /**
     * Normalize a Message-ID header value ("<abc@host>" → "abc@host").
     */
    public static function normalizeId(?string $id): ?string
    {
        $id = trim((string) $id, " \t\r\n<>");

        return $id === '' ? null : $id;
    }

    /**
     * All Message-IDs in a References / In-Reply-To header value.
     *
     * @return list<string>
     */
    public static function parseIds(?string $value): array
    {
        preg_match_all('/<([^<>\s]+)>/', (string) $value, $matches);

        $ids = $matches[1] !== [] ? $matches[1] : array_filter([self::normalizeId($value)]);

        return array_values(array_unique($ids));
    }

    /**
     * Auto-replies, bounces and bulk mail must never create tickets or replies (mail loops).
     */
    public function isAutomated(): bool
    {
        $header = fn (string $name): string => Str::lower(trim($this->headers[$name] ?? ''));

        if ($header('auto-submitted') !== '' && $header('auto-submitted') !== 'no') {
            return true;
        }

        if (in_array($header('precedence'), ['bulk', 'junk', 'list', 'auto_reply'], true)) {
            return true;
        }

        if ($header('x-autoreply') !== '' || $header('x-autorespond') !== '' || $header('x-auto-response-suppress') === 'all') {
            return true;
        }

        return Str::startsWith(Str::lower($this->fromEmail), ['mailer-daemon@', 'postmaster@']);
    }

    /**
     * What the receiving server's authentication results say about the From address.
     *
     * Reads the topmost Authentication-Results header (the one our own provider added), falling
     * back to provider-specific headers (Mailgun's DKIM check, Received-SPF). DKIM and SPF only
     * count when their domain matches the From domain, like DMARC alignment.
     */
    public function senderVerification(): SenderVerification
    {
        $domain = Str::lower(Str::after($this->fromEmail, '@'));
        $results = $this->authenticationResults();

        if ($results !== null) {
            if (preg_match('/\bdmarc=(\w+)/i', $results, $dmarc) === 1) {
                return Str::lower($dmarc[1]) === 'pass' ? SenderVerification::Passed : SenderVerification::Failed;
            }

            $dkimDomains = preg_match_all('/\bdkim=pass\b[^;]*?\bheader\.[di]=@?([^\s;]+)/i', $results, $dkim) > 0 ? $dkim[1] : [];
            $spfDomains = preg_match_all('/\bspf=pass\b[^;]*?\bsmtp\.mailfrom=(?:[^\s;@]*@)?([^\s;]+)/i', $results, $spf) > 0 ? $spf[1] : [];

            if (collect([...$dkimDomains, ...$spfDomains])->contains(fn (string $other): bool => self::aligned($domain, $other))) {
                return SenderVerification::Passed;
            }

            if (preg_match('/\b(dkim|spf)=/i', $results) === 1) {
                return SenderVerification::Failed;
            }
        }

        return $this->providerVerification($domain);
    }

    /**
     * Mailgun's own DKIM check and the Received-SPF header (Postmark, many IMAP servers).
     */
    private function providerVerification(string $domain): SenderVerification
    {
        $header = fn (string $name): string => trim($this->headers[$name] ?? '');
        $checked = false;

        if (($dkimResult = Str::lower($header('x-mailgun-dkim-check-result'))) !== '') {
            $checked = true;
            $signedBy = preg_match('/\bd=([^\s;]+)/i', $header('dkim-signature'), $match) === 1 ? $match[1] : '';

            if ($dkimResult === 'pass' && self::aligned($domain, $signedBy)) {
                return SenderVerification::Passed;
            }
        }

        if (($receivedSpf = $header('received-spf')) !== '') {
            $checked = true;
            $envelope = preg_match('/\benvelope-from=["<]?(?:[^\s;@"<>]*@)?([^\s;"<>]+)/i', $receivedSpf, $match) === 1 ? $match[1] : '';

            if (Str::startsWith(Str::lower($receivedSpf), 'pass') && self::aligned($domain, $envelope)) {
                return SenderVerification::Passed;
            }
        }

        return $checked ? SenderVerification::Failed : SenderVerification::Unknown;
    }

    /**
     * The topmost Authentication-Results header, when it comes from a server we trust.
     */
    private function authenticationResults(): ?string
    {
        $results = trim($this->headers['authentication-results'] ?? '');

        if ($results === '') {
            return null;
        }

        $trusted = array_map(Str::lower(...), (array) config('kitedesk.mail.authserv_ids', []));
        $authservId = Str::lower(trim(Str::before($results, ';')));

        return $trusted === [] || in_array($authservId, $trusted, true) ? $results : null;
    }

    /**
     * Relaxed alignment: the same domain, or one is a subdomain of the other.
     */
    private static function aligned(string $fromDomain, string $other): bool
    {
        $other = Str::lower(trim($other, " \t.>\"'"));

        return $fromDomain !== '' && $other !== ''
            && ($fromDomain === $other || str_ends_with($fromDomain, '.'.$other) || str_ends_with($other, '.'.$fromDomain));
    }

    /**
     * Every address the email was sent or copied to.
     *
     * @return list<string>
     */
    public function recipientAddresses(): array
    {
        return array_values(array_unique(array_map(
            fn (array $address): string => Str::lower($address['email']),
            [...$this->to, ...$this->cc],
        )));
    }

    /**
     * Thread ids to look up, most specific first.
     *
     * @return list<string>
     */
    public function threadIds(): array
    {
        return array_values(array_unique(array_filter([$this->inReplyTo, ...array_reverse($this->references)])));
    }
}
