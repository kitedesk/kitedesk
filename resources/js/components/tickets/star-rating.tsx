import { Star } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

export const SCORE_LABELS: Record<number, string> = {
    1: 'Very unsatisfied',
    2: 'Unsatisfied',
    3: 'Neutral',
    4: 'Satisfied',
    5: 'Very satisfied',
};

/**
 * Five stars for the satisfaction survey. Read-only when there is no onChange.
 */
export function StarRating({
    value,
    onChange,
    size = 'md',
    className,
}: {
    value: number | null;
    onChange?: (score: number) => void;
    size?: 'sm' | 'md' | 'lg';
    className?: string;
}) {
    const { t } = useTranslation();
    const [hovered, setHovered] = useState<number | null>(null);
    const shown = hovered ?? value ?? 0;
    const iconSize = { sm: 'size-3.5', md: 'size-5', lg: 'size-8' }[size];

    if (!onChange) {
        return (
            <span
                className={cn('inline-flex items-center gap-0.5', className)}
                role="img"
                aria-label={t(':score out of 5', { score: value ?? 0 })}
            >
                {[1, 2, 3, 4, 5].map((score) => (
                    <Star
                        key={score}
                        className={cn(
                            iconSize,
                            score <= shown
                                ? 'fill-amber-400 text-amber-400'
                                : 'text-muted-foreground/40',
                        )}
                    />
                ))}
            </span>
        );
    }

    return (
        <div
            role="radiogroup"
            aria-label={t('Rating')}
            className={cn('inline-flex items-center gap-1', className)}
            onMouseLeave={() => setHovered(null)}
        >
            {[1, 2, 3, 4, 5].map((score) => (
                <button
                    key={score}
                    type="button"
                    role="radio"
                    aria-checked={value === score}
                    aria-label={t(SCORE_LABELS[score])}
                    title={t(SCORE_LABELS[score])}
                    onMouseEnter={() => setHovered(score)}
                    onFocus={() => setHovered(score)}
                    onBlur={() => setHovered(null)}
                    onClick={() => onChange(score)}
                    className="rounded-md p-0.5 transition-transform outline-none hover:scale-110 focus-visible:ring-[3px] focus-visible:ring-ring/50"
                >
                    <Star
                        className={cn(
                            iconSize,
                            'transition-colors',
                            score <= shown
                                ? 'fill-amber-400 text-amber-400'
                                : 'text-muted-foreground/40',
                        )}
                    />
                </button>
            ))}
        </div>
    );
}
