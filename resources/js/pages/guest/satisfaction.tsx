import { Head } from '@inertiajs/react';
import { Smile } from 'lucide-react';
import { SatisfactionForm } from '@/components/tickets/satisfaction-form';
import type { SatisfactionAnswer } from '@/components/tickets/satisfaction-form';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    ticket: { number: string; subject: string };
    chosenScore: number | null;
    rating: SatisfactionAnswer | null;
    canRate: boolean;
    submitUrl: string;
};

/**
 * Opened from a star in the satisfaction survey email. The chosen score is saved right
 * away; the customer can then change it or add a comment.
 */
export default function SatisfactionSurvey({
    ticket,
    chosenScore,
    rating,
    canRate,
    submitUrl,
}: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Rate our support')} />

            <div className="mx-auto w-full max-w-lg space-y-6 px-4 py-10">
                <div className="space-y-1">
                    <Smile className="mb-3 size-8 text-primary" />
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {t('How did we do?')}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {t('Request :number: :subject', {
                            number: ticket.number,
                            subject: ticket.subject,
                        })}
                    </p>
                </div>

                {canRate ? (
                    <div className="rounded-xl border bg-card p-5 shadow-xs">
                        <SatisfactionForm
                            url={submitUrl}
                            answer={rating}
                            initialScore={chosenScore}
                            submitScoreOnMount={
                                chosenScore !== null &&
                                chosenScore !== rating?.score
                            }
                        />
                    </div>
                ) : (
                    <p className="rounded-xl border bg-card p-5 text-sm text-muted-foreground shadow-xs">
                        {t(
                            'This request was reopened, so it can’t be rated right now. You can rate it once it is solved again.',
                        )}
                    </p>
                )}
            </div>
        </>
    );
}
