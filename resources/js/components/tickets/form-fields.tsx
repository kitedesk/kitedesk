import InputError from '@/components/input-error';
import { findCategory } from '@/components/tickets/category-select';
import { CustomFieldInput } from '@/components/tickets/custom-field-input';
import { Label } from '@/components/ui/label';
import type { CategoryNode, FormFields, TicketField } from '@/types';

/**
 * A custom field value as entered on a form (checkboxes are booleans).
 */
export type CustomFieldValue = string | boolean;

/**
 * The fields of the form that applies to the chosen category, or the default form.
 */
export function fieldsForCategory(
    categories: CategoryNode[],
    forms: FormFields,
    categoryId: number | null,
    defaultFormId: number | null,
): TicketField[] {
    const formId =
        findCategory(categories, categoryId)?.category.form_id ?? defaultFormId;

    return formId !== null ? (forms[formId] ?? []) : [];
}

/**
 * Custom fields of a new ticket, with labels, required markers and errors.
 */
export function TicketFormFields({
    fields,
    values,
    errors,
    onChange,
}: {
    fields: TicketField[];
    values: Record<string, CustomFieldValue>;
    errors: Record<string, string | undefined>;
    onChange: (key: string, value: CustomFieldValue) => void;
}) {
    return (
        <>
            {fields.map((field) => (
                <div key={field.id} className="grid gap-2">
                    <Label htmlFor={`field-${field.key}`}>
                        {field.label}
                        {field.is_required && (
                            <span className="text-destructive"> *</span>
                        )}
                    </Label>
                    <CustomFieldInput
                        id={`field-${field.key}`}
                        field={field}
                        value={values[field.key]}
                        onChange={(value) =>
                            onChange(field.key, value as CustomFieldValue)
                        }
                    />
                    <InputError
                        message={errors[`custom_fields.${field.key}`]}
                    />
                </div>
            ))}
        </>
    );
}
