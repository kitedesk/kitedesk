import { ChevronLeft, ChevronRight, Download, Paperclip } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';
import { formatFileSize } from '@/lib/tickets';
import type { Attachment } from '@/types';

/**
 * Raster formats every browser shows. SVGs stay plain files, like any other upload.
 */
const PREVIEWABLE = new Set([
    'image/png',
    'image/jpeg',
    'image/gif',
    'image/webp',
    'image/avif',
]);

export function isPreviewableImage(attachment: Attachment): boolean {
    return PREVIEWABLE.has(attachment.mime_type ?? '');
}

/**
 * A message's attachments: images as thumbnails that open full size, everything else
 * as download links.
 */
export function MessageAttachments({
    attachments,
}: {
    attachments: Attachment[];
}) {
    const [viewing, setViewing] = useState<number | null>(null);
    const images = attachments.filter(isPreviewableImage);
    const files = attachments.filter(
        (attachment) => !isPreviewableImage(attachment),
    );

    if (attachments.length === 0) {
        return null;
    }

    return (
        <div className="mt-3 space-y-2">
            {images.length > 0 && (
                <ul className="flex flex-wrap gap-2">
                    {images.map((image, index) => (
                        <li key={image.id}>
                            <button
                                type="button"
                                onClick={() => setViewing(index)}
                                title={image.name}
                                className="block overflow-hidden rounded-lg border bg-muted outline-none hover:opacity-90 focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                <img
                                    src={image.url}
                                    alt={image.name}
                                    loading="lazy"
                                    decoding="async"
                                    className="h-32 max-w-full object-cover sm:h-40 sm:max-w-72"
                                />
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            {files.length > 0 && (
                <ul className="flex flex-wrap gap-2">
                    {files.map((attachment) => (
                        <li key={attachment.id}>
                            <a
                                href={attachment.url}
                                className="inline-flex items-center gap-1.5 rounded-md border bg-background px-2 py-1 text-xs hover:bg-accent"
                            >
                                <Paperclip className="size-3" />
                                <span className="max-w-48 truncate">
                                    {attachment.name}
                                </span>
                                <span className="text-muted-foreground">
                                    {formatFileSize(attachment.size)}
                                </span>
                            </a>
                        </li>
                    ))}
                </ul>
            )}

            <ImageViewer
                images={images}
                index={viewing}
                onIndexChange={setViewing}
            />
        </div>
    );
}

/**
 * Full-size view of one image, with arrows (and arrow keys) to step through the
 * message's other images.
 */
function ImageViewer({
    images,
    index,
    onIndexChange,
}: {
    images: Attachment[];
    index: number | null;
    onIndexChange: (index: number | null) => void;
}) {
    const { t } = useTranslation();
    const image = index === null ? null : images[index];
    const hasMany = images.length > 1;

    const step = (offset: -1 | 1) => {
        if (index !== null) {
            onIndexChange((index + offset + images.length) % images.length);
        }
    };

    return (
        <Dialog
            open={image !== null}
            onOpenChange={(open) => !open && onIndexChange(null)}
        >
            <DialogContent
                className="max-h-[calc(100vh-2rem)] gap-3 p-3 sm:max-w-5xl"
                onKeyDown={(event) => {
                    if (hasMany && event.key === 'ArrowLeft') {
                        step(-1);
                    }

                    if (hasMany && event.key === 'ArrowRight') {
                        step(1);
                    }
                }}
            >
                {image && (
                    <>
                        <div className="flex min-w-0 items-center gap-2 pr-8">
                            <DialogTitle className="truncate text-sm">
                                {image.name}
                            </DialogTitle>
                            <DialogDescription className="shrink-0 text-xs">
                                {formatFileSize(image.size)}
                                {hasMany &&
                                    ` · ${t(':current of :total', {
                                        current: String((index ?? 0) + 1),
                                        total: String(images.length),
                                    })}`}
                            </DialogDescription>
                            <Button
                                asChild
                                variant="ghost"
                                size="sm"
                                className="ml-auto h-7 shrink-0"
                            >
                                <a href={image.url}>
                                    <Download /> {t('Download')}
                                </a>
                            </Button>
                        </div>

                        <div className="relative flex min-h-0 items-center justify-center">
                            <img
                                key={image.id}
                                src={image.url}
                                alt={image.name}
                                className="max-h-[calc(100vh-7rem)] w-auto max-w-full rounded-md object-contain"
                            />
                            {hasMany && (
                                <>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        size="icon"
                                        aria-label={t('Previous image')}
                                        onClick={() => step(-1)}
                                        className="absolute left-2 rounded-full opacity-80 hover:opacity-100"
                                    >
                                        <ChevronLeft />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        size="icon"
                                        aria-label={t('Next image')}
                                        onClick={() => step(1)}
                                        className="absolute right-2 rounded-full opacity-80 hover:opacity-100"
                                    >
                                        <ChevronRight />
                                    </Button>
                                </>
                            )}
                        </div>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
