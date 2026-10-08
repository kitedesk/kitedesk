<?php

namespace App\Domain\Branding;

use App\Domain\Support\StoragePaths;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Uploaded logos and favicons, kept on the private disk and served by BrandingAssetController.
 */
class BrandAssets
{
    public const string DIRECTORY = 'branding';

    public const string DISK = 'local';

    /**
     * The file to keep after an upload or removal; the replaced file is deleted.
     */
    public static function replace(?string $current, ?UploadedFile $upload, bool $remove): ?string
    {
        if ($upload === null && ! $remove) {
            return $current;
        }

        if ($current !== null) {
            Storage::disk(self::DISK)->delete($current);
        }

        if ($upload === null) {
            return null;
        }

        $path = $upload->store(StoragePaths::scoped(self::DIRECTORY), self::DISK);

        return $path !== false ? $path : null;
    }

    public static function path(string $file): string
    {
        return StoragePaths::scoped(self::DIRECTORY).'/'.$file;
    }
}
