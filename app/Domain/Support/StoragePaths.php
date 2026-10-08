<?php

namespace App\Domain\Support;

/**
 * Folders for files the app stores itself, under the same prefix as ticket attachments
 * (`media-library.prefix`, MEDIA_PREFIX). Empty by default; the hosted edition sets it per
 * workspace so workspaces sharing a disk never share paths. Read on every call.
 */
class StoragePaths
{
    public static function scoped(string $directory): string
    {
        $prefix = trim((string) config('media-library.prefix', ''), '/');

        return $prefix === '' ? $directory : "{$prefix}/{$directory}";
    }
}
