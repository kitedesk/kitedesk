import { router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    Eye,
    EyeOff,
    SlidersHorizontal,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useTranslation } from '@/hooks/use-translation';
import { statusColors } from '@/lib/tickets';
import { cn } from '@/lib/utils';
import { board as saveBoardPreferences } from '@/routes/agent/preferences';
import type {
    BoardCardField,
    BoardGroupBy,
    BoardPreferences,
    TicketBoardData,
} from '@/types';

const GROUP_BY: { value: BoardGroupBy; label: string }[] = [
    { value: 'status', label: 'Status' },
    { value: 'priority', label: 'Priority' },
    { value: 'assignee', label: 'Assignee' },
    { value: 'group', label: 'Group' },
];

const CARD_FIELDS: { value: BoardCardField; label: string }[] = [
    { value: 'requester', label: 'Requester' },
    { value: 'assignee', label: 'Assignee' },
    { value: 'priority', label: 'Priority' },
    { value: 'status', label: 'Status' },
    { value: 'group', label: 'Group' },
    { value: 'sla', label: 'SLA' },
    { value: 'tags', label: 'Tags' },
    { value: 'latest_message', label: 'Latest message' },
    { value: 'updated', label: 'Last update' },
];

/**
 * The agent's own board setup: what lanes group by, which lanes show and in what order,
 * and what each card shows. Every change is saved right away.
 */
export function BoardSettings({
    board,
    preferences,
}: {
    board: TicketBoardData;
    preferences: BoardPreferences;
}) {
    const { t } = useTranslation();
    const lanes = board.available;

    const save = (changes: Record<string, unknown>) =>
        router.patch(saveBoardPreferences.url(), changes as never, {
            only: ['board', 'boardPreferences'],
            preserveState: true,
            preserveScroll: true,
        });

    const saveLanes = (order: string[], hidden: string[]) =>
        save({ columns: { [board.group_by]: { order, hidden } } });

    const hidden = lanes.filter((lane) => lane.hidden).map((lane) => lane.key);

    const moveLane = (index: number, offset: -1 | 1) => {
        const order = lanes.map((lane) => lane.key);
        [order[index], order[index + offset]] = [
            order[index + offset],
            order[index],
        ];
        saveLanes(order, hidden);
    };

    const toggleLane = (key: string) =>
        saveLanes(
            lanes.map((lane) => lane.key),
            hidden.includes(key)
                ? hidden.filter((item) => item !== key)
                : [...hidden, key],
        );

    const toggleField = (field: BoardCardField) =>
        save({
            card_fields: preferences.card_fields.includes(field)
                ? preferences.card_fields.filter((item) => item !== field)
                : [...preferences.card_fields, field],
        });

    return (
        <Sheet>
            <SheetTrigger asChild>
                <Button variant="outline" size="sm" className="h-8">
                    <SlidersHorizontal /> {t('Customize')}
                </Button>
            </SheetTrigger>
            <SheetContent className="w-full overflow-y-auto sm:max-w-sm">
                <SheetHeader>
                    <SheetTitle>{t('Customize the board')}</SheetTitle>
                    <SheetDescription>
                        {t('Only you see these settings.')}
                    </SheetDescription>
                </SheetHeader>

                <div className="space-y-6 px-4 pb-6">
                    <div className="grid gap-2">
                        <Label>{t('Group by')}</Label>
                        <Select
                            value={board.group_by}
                            onValueChange={(value) => save({ group_by: value })}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {GROUP_BY.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {t(option.label)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="grid gap-2">
                        <Label>{t('Lanes')}</Label>
                        <ul className="divide-y rounded-lg border">
                            {lanes.map((lane, index) => (
                                <li
                                    key={lane.key}
                                    className={cn(
                                        'flex items-center gap-2 px-2 py-1.5 text-sm',
                                        lane.hidden && 'text-muted-foreground',
                                    )}
                                >
                                    <div className="flex flex-col">
                                        <button
                                            type="button"
                                            aria-label={t('Move up')}
                                            disabled={index === 0}
                                            onClick={() => moveLane(index, -1)}
                                            className="rounded p-0.5 text-muted-foreground hover:bg-accent disabled:opacity-30"
                                        >
                                            <ArrowUp className="size-3" />
                                        </button>
                                        <button
                                            type="button"
                                            aria-label={t('Move down')}
                                            disabled={
                                                index === lanes.length - 1
                                            }
                                            onClick={() => moveLane(index, 1)}
                                            className="rounded p-0.5 text-muted-foreground hover:bg-accent disabled:opacity-30"
                                        >
                                            <ArrowDown className="size-3" />
                                        </button>
                                    </div>
                                    {lane.color && (
                                        <span
                                            className={cn(
                                                'size-2 shrink-0 rounded-full',
                                                statusColors[lane.color].dot,
                                            )}
                                        />
                                    )}
                                    <span
                                        className={cn(
                                            'min-w-0 flex-1 truncate',
                                            lane.hidden && 'line-through',
                                        )}
                                    >
                                        {lane.label}
                                    </span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-7"
                                        title={
                                            lane.hidden
                                                ? t('Show lane')
                                                : t('Hide lane')
                                        }
                                        onClick={() => toggleLane(lane.key)}
                                    >
                                        {lane.hidden ? <EyeOff /> : <Eye />}
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    </div>

                    <div className="grid gap-2">
                        <Label>{t('Show on cards')}</Label>
                        <div className="grid grid-cols-2 gap-2">
                            {CARD_FIELDS.map((field) => (
                                <label
                                    key={field.value}
                                    className="flex items-center gap-2 text-sm"
                                >
                                    <input
                                        type="checkbox"
                                        checked={preferences.card_fields.includes(
                                            field.value,
                                        )}
                                        onChange={() =>
                                            toggleField(field.value)
                                        }
                                        className="size-4 accent-[var(--primary)]"
                                    />
                                    {t(field.label)}
                                </label>
                            ))}
                        </div>
                    </div>

                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => save({ reset: true })}
                    >
                        {t('Reset to defaults')}
                    </Button>
                </div>
            </SheetContent>
        </Sheet>
    );
}
