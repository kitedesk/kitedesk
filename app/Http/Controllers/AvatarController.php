<?php

namespace App\Http\Controllers;

use App\Domain\Accounts\Support\UserAvatars;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Profile photos. Each upload gets a new random name, so they can be cached for long.
 */
class AvatarController extends Controller
{
    public function __invoke(string $file): StreamedResponse
    {
        $path = UserAvatars::path($file);

        if (! User::query()->where('avatar_path', $path)->exists() || ! Storage::disk(UserAvatars::DISK)->exists($path)) {
            abort(404);
        }

        return Storage::disk(UserAvatars::DISK)->response($path, $file, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
