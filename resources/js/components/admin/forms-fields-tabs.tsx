import { router } from '@inertiajs/react';
import { AnimatedTabs } from '@/components/ui/animated-tabs';
import { useTranslation } from '@/hooks/use-translation';
import { index as fieldsIndex } from '@/routes/admin/ticket-fields';
import { index as formsIndex } from '@/routes/admin/ticket-forms';

/**
 * Switches between ticket forms and the fields they are built from, which share one
 * entry in the admin center.
 */
export function FormsFieldsTabs({ active }: { active: 'forms' | 'fields' }) {
    const { t } = useTranslation();

    return (
        <AnimatedTabs
            tabs={[
                { id: 'forms', label: t('Forms') },
                { id: 'fields', label: t('Fields') },
            ]}
            activeTab={active}
            onChange={(id) =>
                router.visit(id === 'forms' ? formsIndex() : fieldsIndex())
            }
            renderContent={false}
            className="mb-6"
        />
    );
}
