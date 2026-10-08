import type { NamedRecord, Option, TicketPriority } from '@/types';

export type SlaMetric = 'first_response' | 'next_reply' | 'resolution';

export type SlaTargets = Record<
    TicketPriority,
    Partial<Record<SlaMetric, number | null>>
>;

export type SlaConditions = {
    priorities: TicketPriority[];
    group_ids: number[];
    organization_ids: number[];
};

export type SlaPolicy = {
    id: number;
    name: string;
    description: string | null;
    business_schedule_id: number | null;
    conditions: SlaConditions;
    targets: SlaTargets;
    position: number;
    is_active: boolean;
};

export type SlaPolicyListItem = SlaPolicy & {
    business_schedule: NamedRecord | null;
    condition_labels: {
        priorities: string[];
        groups: string[];
        organizations: string[];
    };
};

export type SlaPolicyOptions = {
    priorities: Option<TicketPriority>[];
    groups: NamedRecord[];
    organizations: NamedRecord[];
    schedules: (NamedRecord & { timezone: string })[];
};

export type ScheduleInterval = { start: string; end: string };

/** Keyed by ISO weekday "1" (Monday) … "7" (Sunday). */
export type WeeklyHours = Record<string, ScheduleInterval[]>;

export type Holiday = { id: number; name: string; date: string };

export type BusinessSchedule = {
    id: number;
    name: string;
    timezone: string;
    hours: WeeklyHours;
    holidays?: Holiday[];
};

export type BusinessScheduleListItem = BusinessSchedule & {
    holidays_count: number;
    policies_count: number;
};
