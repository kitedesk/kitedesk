import { useForm } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import InputError from '@/components/input-error';
import { SCORE_LABELS, StarRating } from '@/components/tickets/star-rating';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';

export type SatisfactionAnswer = {
    score: number | null;
    comment: string | null;
};

/**
 * Stars plus an optional comment, posted to `url`. With `submitScoreOnMount` the chosen
 * score is saved as soon as the page opens (the one-click link in the survey email).
 */
export function SatisfactionForm({
    url,
    answer,
    initialScore = null,
    submitScoreOnMount = false,
}: {
    url: string;
    answer: SatisfactionAnswer | null;
    initialScore?: number | null;
    submitScoreOnMount?: boolean;
}) {
    const { t } = useTranslation();
    const form = useForm({
        score: initialScore ?? answer?.score ?? null,
        comment: answer?.comment ?? '',
    });
    const autoSubmitted = useRef(false);
    const answered = answer?.score != null;
    const submit = () =>
        form.post(url, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => form.setDefaults(),
        });

    useEffect(() => {
        if (!submitScoreOnMount || autoSubmitted.current || !form.data.score) {
            return;
        }

        autoSubmitted.current = true;
        submit();
    }, []);

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                submit();
            }}
            className="space-y-4"
        >
            <div className="grid gap-2">
                <div className="flex flex-wrap items-center gap-3">
                    <StarRating
                        value={form.data.score}
                        onChange={(score) => form.setData('score', score)}
                        size="lg"
                    />
                    {form.data.score && (
                        <span className="text-sm text-muted-foreground">
                            {t(SCORE_LABELS[form.data.score])}
                        </span>
                    )}
                </div>
                <InputError message={form.errors.score} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="satisfaction-comment">
                    {t('Anything you want to tell us? (optional)')}
                </Label>
                <textarea
                    id="satisfaction-comment"
                    value={form.data.comment}
                    rows={3}
                    maxLength={2000}
                    onChange={(event) =>
                        form.setData('comment', event.target.value)
                    }
                    className="rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                />
                <InputError message={form.errors.comment} />
            </div>

            <div className="flex items-center justify-end gap-3">
                {answered && !form.isDirty && (
                    <span className="text-sm text-muted-foreground">
                        {t('Thanks, we received your rating.')}
                    </span>
                )}
                <Button
                    type="submit"
                    disabled={!form.data.score || form.processing}
                >
                    {answered ? t('Update rating') : t('Send rating')}
                </Button>
            </div>
        </form>
    );
}

/**
 * The rating box under a solved request in the portal.
 */
export function SatisfactionCard({
    url,
    answer,
}: {
    url: string;
    answer: SatisfactionAnswer;
}) {
    const { t } = useTranslation();

    return (
        <section className="space-y-3 rounded-xl border bg-card p-5 shadow-xs">
            <div className="space-y-1">
                <h2 className="font-semibold">
                    {answer.score
                        ? t('Your rating')
                        : t('How would you rate our support?')}
                </h2>
                <p className="text-sm text-muted-foreground">
                    {t(
                        'Your feedback helps us improve. You can change it while the request stays solved.',
                    )}
                </p>
            </div>
            <SatisfactionForm url={url} answer={answer} />
        </section>
    );
}
