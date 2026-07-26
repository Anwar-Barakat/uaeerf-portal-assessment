import { useId } from 'react';
import InputError from '@/components/input-error';
import type { ComboboxOption, ComboboxProps } from '@/components/ui/combobox';
import { Combobox, dedupeOptions } from '@/components/ui/combobox';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

interface FormSelectProps extends Omit<ComboboxProps, 'invalid'> {
    label: string;
    required?: boolean;
    /** Server-side validation message, e.g. `errors.nationality_id`. */
    error?: string;
    /** Helper text shown under the field when there is no error. */
    hint?: string;
    containerClassName?: string;
}

/**
 * Searchable select for forms: label, combobox with type-ahead filtering and
 * the Inertia validation error, all wired to one `id`.
 */
export default function FormSelect({
    label,
    required = false,
    error,
    hint,
    containerClassName,
    id,
    ...props
}: FormSelectProps) {
    const generatedId = useId();
    const fieldId = id ?? generatedId;

    return (
        <div className={cn('space-y-2', containerClassName)}>
            <Label htmlFor={fieldId}>
                {label}
                {required && ' *'}
            </Label>
            <Combobox id={fieldId} invalid={Boolean(error)} {...props} />
            {error ? (
                <InputError message={error} />
            ) : hint ? (
                <p className="text-muted-foreground text-sm">{hint}</p>
            ) : null}
        </div>
    );
}

/**
 * Map a federation list (`{ Code, Name }` and friends) to combobox options.
 *
 * Deduplicated by value: the federation views return one row per
 * registration, so the same code can arrive several times.
 */
export function toOptions<T>(
    items: T[] | undefined,
    getValue: (item: T) => string | number,
    getLabel: (item: T) => string,
    getKeywords?: (item: T) => string[],
): ComboboxOption[] {
    return dedupeOptions(
        (items ?? []).map((item) => ({
            value: String(getValue(item)),
            label: getLabel(item),
            keywords: getKeywords?.(item),
        })),
    );
}

export { dedupeOptions };
