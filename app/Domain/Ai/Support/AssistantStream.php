<?php

namespace App\Domain\Ai\Support;

use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Streaming\Events\TextDelta;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Streams an agent's text to the ticket page as server-sent events:
 * `{"type":"text","text":"…"}` per chunk, then `{"type":"done"}`, or `{"type":"error","message":"…"}`
 * when the provider fails partway (after the status line has gone out, so it can't be a 502).
 */
final class AssistantStream
{
    public static function response(StreamableAgentResponse $stream): StreamedResponse
    {
        return response()->stream(function () use ($stream) {
            try {
                foreach ($stream as $event) {
                    if ($event instanceof TextDelta && $event->delta !== '') {
                        yield self::event(['type' => 'text', 'text' => $event->delta]);
                    }
                }

                yield self::event(['type' => 'done']);
            } catch (Throwable $exception) {
                Log::warning('AI assistant request failed.', ['exception' => $exception]);

                yield self::event(['type' => 'error', 'message' => self::errorMessage()]);
            }
        }, headers: [
            'Cache-Control' => 'no-cache, no-transform',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * What agents see when the provider fails; the details go to the log.
     */
    public static function errorMessage(): string
    {
        return __('The AI assistant could not answer. Try again, or ask an admin to check the AI settings.');
    }

    /**
     * @param  array<string, string>  $data
     */
    private static function event(array $data): string
    {
        return 'data: '.json_encode($data, JSON_UNESCAPED_UNICODE)."\n\n";
    }
}
