import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';

interface ListPageHeaderProps {
    title: string;
    description: string;
    icon: LucideIcon;
    /** Where the back arrow goes; the dashboard for every listing today. */
    backHref: string;
    /** Primary call to action, rendered on the right. */
    action?: ReactNode;
    /** Short facts shown under the title, e.g. record counts. */
    meta?: ReactNode;
}

/**
 * Shared banner for the history listings so the three pages open the same way.
 */
export default function ListPageHeader({
    title,
    description,
    icon: Icon,
    backHref,
    action,
    meta,
}: ListPageHeaderProps) {
    return (
        <section className="relative animate-fade-up overflow-hidden rounded-2xl border bg-gradient-to-br from-primary/10 via-background to-background p-6">
            <div
                aria-hidden
                className="absolute inset-0 bg-grid-pattern [mask-image:radial-gradient(ellipse_at_top_left,black,transparent_75%)]"
            />
            <div className="relative flex flex-wrap items-start justify-between gap-4">
                <div className="flex items-start gap-4">
                    <Button asChild variant="outline" size="icon">
                        <Link href={backHref} aria-label="Back">
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div className="space-y-1.5">
                        <div className="flex items-center gap-2.5">
                            <span className="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <Icon className="size-4.5" />
                            </span>
                            <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                                {title}
                            </h1>
                        </div>
                        <p className="text-muted-foreground">{description}</p>
                        {meta && (
                            <div className="flex flex-wrap items-center gap-2 pt-1">
                                {meta}
                            </div>
                        )}
                    </div>
                </div>
                {action}
            </div>
        </section>
    );
}
