<?php

namespace App\Domain\Accounts\Support;

use App\Domain\Support\StoragePaths;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Profile photos, kept on the private disk under a random name and served by AvatarController.
 * The name is the only key, so the URL works wherever the person appears, including the
 * pages guests open from emailed links.
 */
class UserAvatars
{
    public const string DIRECTORY = 'avatars';

    public const string DISK = 'local';

    /**
     * Store a new photo for the user, deleting the one it replaces.
     */
    public static function replace(User $user, UploadedFile $upload): void
    {
        $path = $upload->store(StoragePaths::scoped(self::DIRECTORY), self::DISK);

        self::remove($user);

        $user->forceFill(['avatar_path' => $path !== false ? $path : null])->save();
    }

    public static function remove(User $user): void
    {
        if ($user->avatar_path === null) {
            return;
        }

        Storage::disk(self::DISK)->delete($user->avatar_path);

        $user->forceFill(['avatar_path' => null])->save();
    }

    public static function url(?string $path): ?string
    {
        return $path === null ? null : route('avatars.show', basename($path));
    }

    public static function path(string $file): string
    {
        return StoragePaths::scoped(self::DIRECTORY).'/'.$file;
    }
}
