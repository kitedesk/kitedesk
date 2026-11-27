import { useEffect, useState } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { emailPreview } from '@/routes/admin';

type PreviewType = 'ticket' | 'notification';

/**
 * The real email, rendered by the server with sample content, so admins see exactly what
 * people receive. Unsaved form values (colour, name, footer, template text) go along in the
 * query; the frame reloads shortly after they stop typing.
 */
export function EmailPreviewFrame({
    params,
    types = ['ticket'],
    className,
}: {
    params: Record<string, string | undefined>;
    /** The samples to offer; with more than one, a switch picks between them. */
    types?: PreviewType[];
    className?: string;
}) {
    const { t } = useTranslation();
    const [type, setType] = useState<PreviewType>(types[0]);
    const query = JSON.stringify({ ...params, type });
    const [src, setSrc] = useState(() => url(query));

    useEffect(() => {
        const timeout = window.setTimeout(() => setSrc(url(query)), 400);

        return () => window.clearTimeout(timeout);
    }, [query]);

    const labels: Record<PreviewType, string> = {
        ticket: t('Ticket reply'),
        notification: t('Team notification'),
    };

    return (
        <div className={cn('space-y-2', className)}>
            {types.length > 1 && (
                <div className="inline-flex rounded-md border p-0.5 text-xs">
                    {types.map((option) => (
                        <button
                            key={option}
                            type="button"
                            onClick={() => setType(option)}
                            className={cn(
                                'rounded px-2.5 py-1',
                                type === option
                                    ? 'bg-accent font-medium'
                                    : 'text-muted-foreground',
                            )}
                        >
                            {labels[option]}
                        </button>
                    ))}
                </div>
            )}
            <iframe
                key={src}
                src={src}
                title={t('Email preview')}
                // No scripts, no navigation: the preview is only to look at.
                sandbox=""
                className="h-[560px] w-full rounded-lg border bg-white"
            />
        </div>
    );
}

function url(query: string): string {
    const params = JSON.parse(query) as Record<string, string | undefined>;

    return emailPreview.url({
        query: Object.fromEntries(
            Object.entries(params).filter(
                (entry): entry is [string, string] => entry[1] !== undefined,
            ),
        ),
    });
}
