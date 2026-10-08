import { Form } from '@inertiajs/react';
import { Monitor, Smartphone } from 'lucide-react';
import { useRef } from 'react';
import BrowserSessionController from '@/actions/App/Http/Controllers/Settings/BrowserSessionController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { relativeTime } from '@/lib/tickets';

export type BrowserSession = {
    id: string;
    browser: string | null;
    platform: string | null;
    is_mobile: boolean;
    ip_address: string | null;
    last_active_at: string;
    is_current: boolean;
};

/**
 * The browsers someone is signed in on (see App\Domain\Accounts\Support\UserSessions).
 */
export function SessionList({ sessions }: { sessions: BrowserSession[] }) {
    const { t } = useTranslation();

    if (sessions.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                {t('Not signed in anywhere.')}
            </p>
        );
    }

    return (
        <ul className="divide-y rounded-lg border">
            {sessions.map((session) => {
                const Icon = session.is_mobile ? Smartphone : Monitor;

                return (
                    <li
                        key={session.id}
                        className="flex items-center gap-3 px-4 py-3"
                    >
                        <Icon className="size-5 shrink-0 text-muted-foreground" />
                        <div className="min-w-0 flex-1 text-sm">
                            <p className="truncate font-medium">
                                {session.browser && session.platform
                                    ? t(':browser on :platform', {
                                          browser: session.browser,
                                          platform: session.platform,
                                      })
                                    : (session.browser ??
                                      session.platform ??
                                      t('Unknown device'))}
                            </p>
                            <p className="truncate text-xs text-muted-foreground">
                                {session.ip_address ?? '—'} ·{' '}
                                {session.is_current ? (
                                    <span className="font-medium text-emerald-600 dark:text-emerald-400">
                                        {t('This device')}
                                    </span>
                                ) : (
                                    t('Active :time', {
                                        time: relativeTime(
                                            session.last_active_at,
                                        ),
                                    })
                                )}
                            </p>
                        </div>
                    </li>
                );
            })}
        </ul>
    );
}

/**
 * Settings section: your sessions, and signing out of the others.
 */
export default function BrowserSessions({
    sessions,
}: {
    sessions: BrowserSession[];
}) {
    const { t } = useTranslation();
    const passwordInput = useRef<HTMLInputElement>(null);
    const hasOthers = sessions.some((session) => !session.is_current);

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title={t('Browser sessions')}
                description={t(
                    'Where you are signed in. Sign out anywhere you don’t recognize, then change your password.',
                )}
            />

            <SessionList sessions={sessions} />

            {hasOthers && (
                <Dialog>
                    <DialogTrigger asChild>
                        <Button variant="outline">
                            {t('Sign out other sessions')}
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>
                            {t('Sign out other sessions')}
                        </DialogTitle>
                        <DialogDescription>
                            {t(
                                'Enter your password to sign out of every other browser and device. This one stays signed in.',
                            )}
                        </DialogDescription>

                        <Form
                            {...BrowserSessionController.destroy.form()}
                            options={{ preserveScroll: true }}
                            onError={() => passwordInput.current?.focus()}
                            resetOnSuccess
                            className="space-y-6"
                        >
                            {({ resetAndClearErrors, processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor="sessions-password"
                                            className="sr-only"
                                        >
                                            {t('Password')}
                                        </Label>
                                        <PasswordInput
                                            id="sessions-password"
                                            name="password"
                                            ref={passwordInput}
                                            placeholder={t('Password')}
                                            autoComplete="current-password"
                                        />
                                        <InputError message={errors.password} />
                                    </div>

                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button
                                                variant="secondary"
                                                onClick={() =>
                                                    resetAndClearErrors()
                                                }
                                            >
                                                {t('Cancel')}
                                            </Button>
                                        </DialogClose>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {t('Sign out other sessions')}
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            )}
        </div>
    );
}
