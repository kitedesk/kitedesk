<?php

namespace App\Http\Controllers;

use App\Domain\Tickets\Models\TicketMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    /**
     * Download a ticket attachment the current user is allowed to see.
     */
    public function __invoke(Request $request, Media $media): StreamedResponse
    {
        $message = $media->model;

        abort_unless($message instanceof TicketMessage, 404);
        abort_if($message->is_internal && ! $request->user()->isStaff(), 404);
        Gate::authorize('view', $message->ticket);

        // Opened before the response starts, so a missing file is a 404 rather than an error
        // halfway through a download.
        $stream = $media->stream();

        if (! is_resource($stream)) {
            Log::warning('A ticket attachment is missing from storage.', [
                'media' => $media->id,
                'disk' => $media->disk,
                'path' => $media->getPathRelativeToRoot(),
            ]);

            abort(404);
        }

        return response()->streamDownload(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, $media->file_name, ['Content-Type' => $media->mime_type ?? 'application/octet-stream']);
    }
}
