import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

interface EmptyStateProps {
    icon: LucideIcon;
    title: string;
    description: string;
    action?: ReactNode;
}

/** Placeholder for a listing with nothing in it yet. */
export default function EmptyState({
    icon: Icon,
    title,
    description,
    action,
}: EmptyStateProps) {
    return (
        <div className="flex flex-col items-center justify-center px-6 py-16 text-center">
            <div className="flex size-16 items-center justify-center rounded-2xl border border-dashed bg-muted/40">
                <Icon className="size-7 text-muted-foreground/60" />
            </div>
            <h3 className="mt-4 text-lg font-semibold">{title}</h3>
            <p className="mt-2 max-w-sm text-sm text-muted-foreground">
                {description}
            </p>
            {action && <div className="mt-6">{action}</div>}
        </div>
    );
}
