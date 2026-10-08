<?php

namespace App\Domain\Mail\Support;

use App\Domain\Support\StoragePaths;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A file attached to an incoming email. The contents wait on the local disk, so only the path
 * travels through the queue (job payloads stay small whatever the attachment size).
 */
final readonly class InboundAttachment
{
    private const string DIRECTORY = 'inbound-mail';

    public function __construct(
        public string $name,
        public string $mimeType,
        public string $path,
        public int $size,
    ) {}

    public static function fromContents(string $name, string $mimeType, string $contents): self
    {
        $path = StoragePaths::scoped(self::DIRECTORY).'/'.Str::uuid()->toString();
        Storage::disk('local')->put($path, $contents);

        return new self($name, $mimeType, $path, strlen($contents));
    }

    public function contents(): string
    {
        return (string) Storage::disk('local')->get($this->path);
    }

    public function size(): int
    {
        return $this->size;
    }

    /**
     * Remove the waiting file once the email has been handled.
     */
    public function delete(): void
    {
        Storage::disk('local')->delete($this->path);
    }
}
