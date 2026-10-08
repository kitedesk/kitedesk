/**
 * POST JSON to the app and read the JSON answer, with Laravel's CSRF cookie. For endpoints
 * that return data rather than an Inertia page.
 */
export async function postJson<T>(
    url: string,
    body: unknown,
): Promise<
    | { ok: true; data: T }
    | {
          ok: false;
          status: number;
          errors: Record<string, string>;
          message: string;
      }
> {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...xsrfHeader(),
        },
        body: JSON.stringify(body),
    });

    const payload = await response.json().catch(() => ({}));

    if (response.ok) {
        return { ok: true, data: payload as T };
    }

    const errors = Object.fromEntries(
        Object.entries((payload.errors ?? {}) as Record<string, string[]>).map(
            ([key, messages]) => [key, messages[0] ?? ''],
        ),
    );

    return {
        ok: false,
        status: response.status,
        errors,
        message: String(payload.message ?? ''),
    };
}

/**
 * Laravel's CSRF header, read from its XSRF-TOKEN cookie.
 */
export function xsrfHeader(): Record<string, string> {
    const token = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    return token ? { 'X-XSRF-TOKEN': decodeURIComponent(token) } : {};
}
