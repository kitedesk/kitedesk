import { router } from '@inertiajs/react';
import { BadgeCheck, Building2, CircleAlert } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/tickets';
import { cn } from '@/lib/utils';
import { lookup } from '@/routes/agent/organizations';
import { organization as saveOrganization } from '@/routes/agent/tickets/requester';

type OrganizationRef = { id: number; name: string };

type Role = 'member' | 'authorized_contact';

/**
 * What the ticket page knows about the requester's organization.
 */
export type RequesterOrganizationInfo = {
    name: string;
    organization: OrganizationRef | null;
    organization_role: Role | null;
    organization_linked: { by: string | null; at: string | null } | null;
    email_verified: boolean;
    suggested_organization: OrganizationRef | null;
    can_link_organization: boolean;
};

/**
 * A short line saying whether the requester may speak for an organization: an authorized
 * contact, a member, or only an email domain that matches one (not confirmed).
 */
export function OrganizationBadge({
    requester,
    className,
}: {
    requester: RequesterOrganizationInfo;
    className?: string;
}) {
    const { t } = useTranslation();

    if (
        requester.organization &&
        requester.organization_role === 'authorized_contact'
    ) {
        return (
            <span
                className={cn(
                    'inline-flex items-center gap-1 rounded bg-emerald-500/15 px-1.5 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-300',
                    className,
                )}
            >
                <BadgeCheck className="size-3.5" />
                {t('Authorized contact · :organization', {
                    organization: requester.organization.name,
                })}
            </span>
        );
    }

    if (requester.organization) {
        return (
            <span
                className={cn(
                    'inline-flex items-center gap-1 rounded bg-muted px-1.5 py-0.5 text-xs font-medium text-muted-foreground',
                    className,
                )}
            >
                <Building2 className="size-3.5" />
                {t('Member of :organization', {
                    organization: requester.organization.name,
                })}
            </span>
        );
    }

    if (requester.suggested_organization) {
        return (
            <span
                className={cn(
                    'inline-flex items-center gap-1 rounded bg-amber-500/15 px-1.5 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-300',
                    className,
                )}
            >
                <CircleAlert className="size-3.5" />
                {t('Email matches :organization — not confirmed', {
                    organization: requester.suggested_organization.name,
                })}
            </span>
        );
    }

    return null;
}

/**
 * The organization part of the requester card: the badge, who confirmed it, and (with the
 * permission) linking the suggested organization in one click or changing it.
 */
export function RequesterOrganization({
    ticketId,
    requester,
}: {
    ticketId: number;
    requester: RequesterOrganizationInfo;
}) {
    const { t } = useTranslation();
    const [changing, setChanging] = useState(false);
    const [saving, setSaving] = useState(false);

    const save = (organizationId: number | null, role: Role | null) => {
        setSaving(true);
        router.put(
            saveOrganization.url(ticketId),
            { organization_id: organizationId, role },
            {
                preserveScroll: true,
                only: ['requester', 'ticket'],
                onSuccess: () => setChanging(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    const linked = requester.organization_linked;

    return (
        <div className="space-y-1.5">
            {requester.organization || requester.suggested_organization ? (
                <OrganizationBadge requester={requester} />
            ) : (
                <p className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Building2 className="size-3.5 shrink-0" />
                    {t('No organization')}
                </p>
            )}

            {requester.organization && linked?.at && (
                <p className="text-xs text-muted-foreground">
                    {linked.by
                        ? t('Confirmed by :name on :date', {
                              name: linked.by,
                              date: formatDateTime(linked.at),
                          })
                        : t('Confirmed on :date', {
                              date: formatDateTime(linked.at),
                          })}
                </p>
            )}

            {!requester.organization &&
                requester.suggested_organization &&
                !requester.email_verified && (
                    <p className="text-xs text-muted-foreground">
                        {t('They have not verified this email address.')}
                    </p>
                )}

            {requester.can_link_organization && (
                <div className="flex flex-wrap gap-1">
                    {!requester.organization &&
                        requester.suggested_organization && (
                            <Button
                                size="sm"
                                variant="secondary"
                                disabled={saving}
                                onClick={() =>
                                    save(
                                        requester.suggested_organization!.id,
                                        'member',
                                    )
                                }
                            >
                                {t('Link to :organization', {
                                    organization:
                                        requester.suggested_organization.name,
                                })}
                            </Button>
                        )}
                    <Button
                        size="sm"
                        variant="ghost"
                        onClick={() => setChanging(true)}
                    >
                        {requester.organization
                            ? t('Change…')
                            : t('Choose organization…')}
                    </Button>
                </div>
            )}

            {changing && (
                <ChangeOrganizationDialog
                    requester={requester}
                    saving={saving}
                    onSave={save}
                    onClose={() => setChanging(false)}
                />
            )}
        </div>
    );
}

function ChangeOrganizationDialog({
    requester,
    saving,
    onSave,
    onClose,
}: {
    requester: RequesterOrganizationInfo;
    saving: boolean;
    onSave: (organizationId: number | null, role: Role | null) => void;
    onClose: () => void;
}) {
    const { t } = useTranslation();
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<OrganizationRef[]>([]);
    const [chosen, setChosen] = useState<OrganizationRef | null>(
        requester.organization ?? requester.suggested_organization,
    );
    const [role, setRole] = useState<Role>(
        requester.organization_role ?? 'member',
    );

    // Search organizations by name as the agent types.
    useEffect(() => {
        const controller = new AbortController();
        const timeout = window.setTimeout(async () => {
            try {
                const response = await fetch(
                    lookup.url({ query: { q: query } }),
                    {
                        headers: { Accept: 'application/json' },
                        signal: controller.signal,
                    },
                );

                if (response.ok) {
                    setResults(
                        (
                            (await response.json()) as {
                                organizations: OrganizationRef[];
                            }
                        ).organizations,
                    );
                }
            } catch {
                // Aborted by the next keystroke, or offline: keep the last results.
            }
        }, 250);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [query]);

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogTitle>{t('Organization')}</DialogTitle>
                <DialogDescription>
                    {t(
                        'Confirm which organization :name belongs to, and whether they may act for it. Their open tickets move with them.',
                        { name: requester.name },
                    )}
                </DialogDescription>

                <div className="space-y-2">
                    <Input
                        autoFocus
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder={t('Search organizations')}
                        aria-label={t('Search organizations')}
                    />
                    <ul className="max-h-48 overflow-y-auto rounded-lg border text-sm">
                        {results.map((organization) => (
                            <li key={organization.id}>
                                <button
                                    type="button"
                                    onClick={() => setChosen(organization)}
                                    className={cn(
                                        'flex w-full items-center gap-2 px-3 py-2 text-left hover:bg-accent',
                                        chosen?.id === organization.id &&
                                            'bg-accent font-medium',
                                    )}
                                >
                                    <Building2 className="size-3.5 shrink-0 text-muted-foreground" />
                                    {organization.name}
                                </button>
                            </li>
                        ))}
                        {results.length === 0 && (
                            <li className="px-3 py-2 text-muted-foreground">
                                {t('No organizations found.')}
                            </li>
                        )}
                    </ul>
                </div>

                <fieldset className="space-y-2">
                    <legend className="mb-1 text-sm font-medium">
                        {t('Role')}
                    </legend>
                    {(
                        [
                            {
                                value: 'member',
                                label: t('Member'),
                                description: t(
                                    'Works there. Their tickets count as the organization’s.',
                                ),
                            },
                            {
                                value: 'authorized_contact',
                                label: t('Authorized contact'),
                                description: t(
                                    'May also act for the organization: request changes, approve and decide.',
                                ),
                            },
                        ] as const
                    ).map((option) => (
                        <Label
                            key={option.value}
                            className="flex items-start gap-2 font-normal"
                        >
                            <input
                                type="radio"
                                name="organization-role"
                                value={option.value}
                                checked={role === option.value}
                                onChange={() => setRole(option.value)}
                                className="mt-1 accent-[var(--primary)]"
                            />
                            <span>
                                <span className="block font-medium">
                                    {option.label}
                                </span>
                                <span className="block text-xs text-muted-foreground">
                                    {option.description}
                                </span>
                            </span>
                        </Label>
                    ))}
                </fieldset>

                <DialogFooter className="gap-2 sm:justify-between">
                    {requester.organization ? (
                        <Button
                            type="button"
                            variant="ghost"
                            className="text-destructive"
                            disabled={saving}
                            onClick={() => onSave(null, null)}
                        >
                            {t('Remove from :organization', {
                                organization: requester.organization.name,
                            })}
                        </Button>
                    ) : (
                        <span />
                    )}
                    <Button
                        type="button"
                        disabled={saving || chosen === null}
                        onClick={() => chosen && onSave(chosen.id, role)}
                    >
                        {chosen
                            ? t('Link to :organization', {
                                  organization: chosen.name,
                              })
                            : t('Choose an organization')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
