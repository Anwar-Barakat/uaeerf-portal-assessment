import type { ReactNode } from 'react';

interface DetailRowProps {
    label: string;
    children: ReactNode;
    /** Render the value in a monospace face, for ids and references. */
    mono?: boolean;
}

/**
 * One label/value line in a record detail panel. Values that are absent render
 * as a dash rather than an empty gap, so a missing field reads as "no value"
 * instead of looking like a layout fault.
 */
export default function DetailRow({
    label,
    children,
    mono = false,
}: DetailRowProps) {
    const empty =
        children === null || children === undefined || children === '';

    return (
        <div className="flex items-start justify-between gap-4 py-3">
            <dt className="shrink-0 text-sm text-muted-foreground">{label}</dt>
            <dd
                className={
                    'max-w-[60%] text-right text-sm font-medium break-words' +
                    (mono ? ' font-mono text-xs' : '')
                }
            >
                {empty ? (
                    <span className="text-muted-foreground">—</span>
                ) : (
                    children
                )}
            </dd>
        </div>
    );
}
