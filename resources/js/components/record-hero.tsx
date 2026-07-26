import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { getStatusBadge } from '@/lib/status-badge';

interface Fact {
    label: string;
    value: string | number | null;
    /** Render the value in a monospace face, for references and ids. */
    mono?: boolean;
}

interface RecordHeroProps {
    title: string;
    subtitle?: string | null;
    icon: LucideIcon;
    status: string;
    backHref: string;
    backLabel: string;
    facts: Fact[];
}

/**
 * Banner for a single history record: identity and status up top, with the
 * facts people actually came to check (fee, reference, dates) on one line.
 */
export default function RecordHero({
    title,
    subtitle,
    icon: Icon,
    status,
    backHref,
    backLabel,
    facts,
}: RecordHeroProps) {
    return (
        <section className="relative animate-fade-up overflow-hidden rounded-2xl border bg-gradient-to-br from-primary/10 via-background to-background p-6">
            <div
                aria-hidden
                className="absolute inset-0 bg-grid-pattern [mask-image:radial-gradient(ellipse_at_top_left,black,transparent_75%)]"
            />
            <div className="relative space-y-5">
                <Button
                    variant="ghost"
                    size="sm"
                    asChild
                    className="-ml-2 h-8 text-muted-foreground"
                >
                    <Link href={backHref}>
                        <ArrowLeft className="size-4" />
                        {backLabel}
                    </Link>
                </Button>

                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex items-start gap-3">
                        <span className="flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <Icon className="size-5" />
                        </span>
                        <div className="space-y-1">
                            <h1 className="text-2xl font-bold tracking-tight">
                                {title}
                            </h1>
                            {subtitle && (
                                <p className="text-sm text-muted-foreground">
                                    {subtitle}
                                </p>
                            )}
                        </div>
                    </div>
                    {getStatusBadge(status)}
                </div>

                <dl className="grid gap-4 border-t pt-4 sm:grid-cols-2 lg:grid-cols-4">
                    {facts.map((fact) => (
                        <div key={fact.label} className="space-y-1">
                            <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                {fact.label}
                            </dt>
                            <dd
                                className={`text-sm font-medium ${fact.mono ? 'font-mono text-xs break-all' : ''}`}
                            >
                                {fact.value ?? (
                                    <span className="text-muted-foreground">
                                        —
                                    </span>
                                )}
                            </dd>
                        </div>
                    ))}
                </dl>
            </div>
        </section>
    );
}
