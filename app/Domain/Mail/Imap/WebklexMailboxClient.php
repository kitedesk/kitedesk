<?php

namespace App\Domain\Mail\Imap;

use App\Domain\Mail\Contracts\MailboxClient;
use App\Domain\Mail\Models\Mailbox;
use App\Domain\Mail\Support\InboundAttachment;
use App\Domain\Mail\Support\InboundEmail;
use Closure;
use RuntimeException;
use Throwable;
use Webklex\PHPIMAP\Address;
use Webklex\PHPIMAP\Attachment;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Message;

/**
 * IMAP access through webklex/php-imap (pure PHP, no imap extension needed).
 */
class WebklexMailboxClient implements MailboxClient
{
    public function fetch(Mailbox $mailbox, Closure $handle, int $limit = 25): int
    {
        $client = $this->connect($mailbox);
        $handled = 0;

        try {
            $messages = $this->folder($client, $mailbox)->messages()->unseen()->leaveUnread()->setFetchOrderAsc()->limit($limit)->get();

            foreach ($messages as $message) {
                $handle($this->toInboundEmail($message));
                $handled++;

                $mailbox->delete_after_import ? $message->delete() : $message->setFlag('Seen');
            }
        } finally {
            $client->disconnect();
        }

        return $handled;
    }

    public function test(Mailbox $mailbox): void
    {
        $client = $this->connect($mailbox);

        try {
            $this->folder($client, $mailbox);
        } finally {
            $client->disconnect();
        }
    }

    private function connect(Mailbox $mailbox): Client
    {
        try {
            return (new ClientManager)->make([
                'host' => $mailbox->imap_host,
                'port' => $mailbox->imap_port ?? 993,
                'encryption' => $mailbox->imap_encryption ?: false,
                'validate_cert' => true,
                'username' => $mailbox->imap_username,
                'password' => $mailbox->imap_password,
                'protocol' => 'imap',
                'timeout' => 30,
            ])->connect();
        } catch (Throwable $exception) {
            throw new RuntimeException(__('Could not connect to :host: :error', ['host' => (string) $mailbox->imap_host, 'error' => $exception->getMessage()]), previous: $exception);
        }
    }

    private function folder(Client $client, Mailbox $mailbox): Folder
    {
        $folder = $client->getFolderByPath($mailbox->imap_folder ?: 'INBOX', soft_fail: true);

        if ($folder === null) {
            throw new RuntimeException(__('The folder ":folder" does not exist.', ['folder' => $mailbox->imap_folder]));
        }

        return $folder;
    }

    private function toInboundEmail(Message $message): InboundEmail
    {
        $header = $message->getHeader();
        $from = $this->addresses($message->get('from'))[0] ?? ['email' => '', 'name' => ''];

        return new InboundEmail(
            fromEmail: $from['email'],
            fromName: $from['name'],
            to: $this->addresses($message->get('to')),
            cc: $this->addresses($message->get('cc')),
            subject: (string) $message->get('subject'),
            text: $message->getTextBody(),
            html: $message->getHTMLBody(),
            messageId: InboundEmail::normalizeId((string) $message->get('message_id')),
            inReplyTo: InboundEmail::parseIds((string) $message->get('in_reply_to'))[0] ?? null,
            references: InboundEmail::parseIds(implode(' ', (array) $message->get('references')->all())),
            headers: $this->rawHeaders($header->raw ?? ''),
            attachments: array_values($message->getAttachments()->map(fn (Attachment $attachment): InboundAttachment => InboundAttachment::fromContents(
                (string) ($attachment->getName() ?: 'attachment'),
                (string) ($attachment->getMimeType() ?: 'application/octet-stream'),
                (string) $attachment->getContent(),
            ))->all()),
        );
    }

    /**
     * @return list<array{email: string, name: string}>
     */
    private function addresses(mixed $attribute): array
    {
        $values = is_object($attribute) && method_exists($attribute, 'all') ? $attribute->all() : [];

        return array_values(array_map(fn (Address $address): array => [
            'email' => mb_strtolower($address->mail),
            'name' => $address->personal,
        ], array_filter($values, fn (mixed $value): bool => $value instanceof Address)));
    }

    /**
     * @return array<string, string>
     */
    private function rawHeaders(string $raw): array
    {
        $headers = [];
        $unfolded = (string) preg_replace('/\r?\n[ \t]+/', ' ', $raw);

        foreach (preg_split('/\r?\n/', $unfolded) ?: [] as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[mb_strtolower(trim($name))] ??= trim($value);
            }
        }

        return $headers;
    }
}
