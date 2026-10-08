import { xsrfHeader } from '@/lib/post-json';

type AssistantEvent =
    | { type: 'text'; text: string }
    | { type: 'done' }
    | { type: 'error'; message: string };

/**
 * Raised when the assistant can't answer; `message` is safe to show to the agent.
 */
export class AssistantError extends Error {}

/**
 * POST to an assistant endpoint and read its server-sent events, calling `onText` with each
 * chunk as it arrives. Resolves with the whole text; rejects with an AssistantError (or an
 * AbortError when `signal` is aborted).
 */
export async function streamAssistant(
    url: string,
    body: unknown,
    {
        onText,
        signal,
        messages,
    }: {
        onText: (chunk: string) => void;
        signal?: AbortSignal;
        /** Shown when the request fails without a message of its own, or is rate limited. */
        messages: { failed: string; rateLimited: string };
    },
): Promise<string> {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'text/event-stream',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...xsrfHeader(),
        },
        body: JSON.stringify(body),
        signal,
    });

    if (!response.ok || !response.body) {
        const payload = await response.json().catch(() => ({}));
        const firstError = Object.values(
            (payload.errors ?? {}) as Record<string, string[]>,
        )[0]?.[0];

        throw new AssistantError(
            firstError ??
                (response.status === 429
                    ? messages.rateLimited
                    : response.status === 502 && payload.message
                      ? String(payload.message)
                      : messages.failed),
        );
    }

    const reader = response.body
        .pipeThrough(new TextDecoderStream())
        .getReader();
    let buffer = '';
    let text = '';

    for (;;) {
        const { value, done } = await reader.read();

        if (done) {
            break;
        }

        buffer += value;
        const events = buffer.split('\n\n');
        buffer = events.pop() ?? '';

        for (const raw of events) {
            const data = raw
                .split('\n')
                .filter((line) => line.startsWith('data: '))
                .map((line) => line.slice(6))
                .join('\n');

            if (!data) {
                continue;
            }

            const event = JSON.parse(data) as AssistantEvent;

            if (event.type === 'text') {
                text += event.text;
                onText(event.text);
            } else if (event.type === 'error') {
                throw new AssistantError(event.message);
            }
        }
    }

    return text;
}

/**
 * The assistant's plain text as editor HTML: escaped, paragraphs on blank lines and line
 * breaks kept. The server sanitizes it again when the message is sent.
 */
export function plainTextToHtml(text: string): string {
    const escape = (value: string) =>
        value
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;');

    return text
        .trim()
        .split(/\n\s*\n/)
        .map((paragraph) => paragraph.trim())
        .filter(Boolean)
        .map(
            (paragraph) =>
                `<p>${escape(paragraph).replaceAll('\n', '<br>')}</p>`,
        )
        .join('');
}
