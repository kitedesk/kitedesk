<?php

namespace App\Domain\Support\Broadcasting;

/**
 * How the browser connects to Reverb, read from the server's configuration on every page so a
 * prebuilt frontend (the Docker image) works with any installation's settings.
 */
class Realtime
{
    /**
     * @return array{key: string, wsHost: string, wsPort: int, wssPort: int, forceTLS: bool}|null null when live updates are off
     */
    public static function clientOptions(): ?array
    {
        $reverb = config('broadcasting.connections.reverb');

        if (config('broadcasting.default') !== 'reverb' || blank($reverb['key'] ?? null)) {
            return null;
        }

        $port = (int) $reverb['options']['port'];

        return [
            'key' => (string) $reverb['key'],
            'wsHost' => (string) $reverb['options']['host'],
            'wsPort' => $port,
            'wssPort' => $port,
            'forceTLS' => $reverb['options']['scheme'] === 'https',
        ];
    }
}
