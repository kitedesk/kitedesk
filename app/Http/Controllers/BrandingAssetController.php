<?php

namespace App\Http\Controllers;

use App\Domain\Branding\BrandAssets;
use App\Domain\Branding\Branding;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Public logos and favicons. URLs carry the branding version, so they can be cached for long.
 */
class BrandingAssetController extends Controller
{
    public function __invoke(string $file): StreamedResponse
    {
        $branding = Branding::current();
        $path = BrandAssets::path($file);

        $isCurrent = in_array($path, [$branding->logo, $branding->logoDark, $branding->favicon], true);

        if (! $isCurrent || ! Storage::disk(BrandAssets::DISK)->exists($path)) {
            abort(404);
        }

        $headers = [
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if (str_ends_with($file, '.svg')) {
            // Opened directly, an uploaded SVG must not be able to run scripts on our origin.
            $headers['Content-Security-Policy'] = "default-src 'none'; style-src 'unsafe-inline'; sandbox";
        }

        return Storage::disk(BrandAssets::DISK)->response($path, $file, $headers, 'inline');
    }
}
