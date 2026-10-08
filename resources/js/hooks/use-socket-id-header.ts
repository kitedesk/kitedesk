import { http } from '@inertiajs/react';
import { echo } from '@laravel/echo-react';
import { useEffect } from 'react';

/**
 * Tells the server which socket this tab uses, so the events its own changes cause aren't
 * echoed back to it. Only for signed-in pages: asking Echo for the socket id opens the
 * connection.
 */
export function useSocketIdHeader(enabled = true): void {
    useEffect(() => {
        if (!enabled) {
            return;
        }

        return http.onRequest((config) => {
            const socketId = echo().socketId();

            return socketId
                ? {
                      ...config,
                      headers: { ...config.headers, 'X-Socket-ID': socketId },
                  }
                : config;
        });
    }, [enabled]);
}
