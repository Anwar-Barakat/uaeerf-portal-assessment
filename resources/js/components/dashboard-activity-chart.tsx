import { BarChart3 } from 'lucide-react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { ActivityPoint } from '@/types';

interface DashboardActivityChartProps {
    activity: ActivityPoint[];
}

const SERIES = [
    {
        key: 'registrations',
        label: 'Registrations',
        dot: 'bg-primary',
        bar: 'from-primary/55 to-primary',
    },
    {
        key: 'entries',
        label: 'Entries',
        dot: 'bg-blue-500',
        bar: 'from-blue-500/55 to-blue-500',
    },
] as const;

export function DashboardActivityChart({
    activity,
}: DashboardActivityChartProps) {
    const totals = activity.reduce(
        (sum, month) => ({
            registrations: sum.registrations + month.registrations,
            entries: sum.entries + month.entries,
        }),
        { registrations: 0, entries: 0 },
    );
    const grandTotal = totals.registrations + totals.entries;
    // The axis needs a ceiling even when every month is empty, or the bar
    // heights divide by zero.
    const axisMax = Math.max(
        1,
        ...activity.map((month) =>
            Math.max(month.registrations, month.entries),
        ),
    );

    return (
        <Card
            className="animate-fade-up overflow-hidden"
            style={{ animationDelay: '350ms' }}
        >
            <CardHeader className="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-start sm:justify-between sm:space-y-0">
                <div className="space-y-1">
                    <CardTitle>Activity Overview</CardTitle>
                    <CardDescription>
                        Registrations and entries over the last 6 months
                    </CardDescription>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    {SERIES.map((series) => (
                        <span
                            key={series.key}
                            className="inline-flex items-center gap-2 rounded-full border bg-muted/40 px-3 py-1.5 text-xs font-medium text-muted-foreground"
                        >
                            <span
                                className={`size-2 rounded-full ${series.dot}`}
                            />
                            {series.label}
                            <span className="font-semibold text-foreground tabular-nums">
                                {totals[series.key]}
                            </span>
                        </span>
                    ))}
                </div>
            </CardHeader>
            <CardContent className="pt-6">
                <div className="flex gap-3">
                    <div className="flex h-52 w-6 flex-col justify-between pb-px text-right text-[10px] leading-none text-muted-foreground tabular-nums">
                        <span>{axisMax}</span>
                        <span>{Math.round(axisMax / 2)}</span>
                        <span>0</span>
                    </div>

                    <div className="relative flex-1">
                        <div
                            aria-hidden
                            className="absolute inset-x-0 top-0 flex h-52 flex-col justify-between"
                        >
                            <span className="border-t border-dashed border-border/70" />
                            <span className="border-t border-dashed border-border/70" />
                            <span className="border-t border-border" />
                        </div>

                        {grandTotal === 0 && (
                            <div className="absolute inset-x-0 top-0 z-10 flex h-52 flex-col items-center justify-center gap-2 text-center">
                                <BarChart3 className="size-9 text-muted-foreground/40" />
                                <p className="text-sm font-medium text-muted-foreground">
                                    No activity to display yet
                                </p>
                                <p className="max-w-xs text-xs text-muted-foreground/70">
                                    Your registrations and competition entries
                                    will appear here.
                                </p>
                            </div>
                        )}

                        <div className="relative flex h-52 items-end justify-between gap-2 sm:gap-4">
                            {activity.map((month) => (
                                <div
                                    key={month.month}
                                    className="group flex h-full flex-1 items-end justify-center gap-1 rounded-t-md transition-colors hover:bg-muted/40 sm:gap-1.5"
                                >
                                    {SERIES.map((series) => (
                                        <div
                                            key={series.key}
                                            className={`w-1/3 max-w-8 rounded-t-md bg-gradient-to-t ${series.bar} opacity-90 transition-all duration-300 group-hover:opacity-100`}
                                            style={{
                                                height: `${Math.max(2, (month[series.key] / axisMax) * 100)}%`,
                                            }}
                                            title={`${month.month}: ${month[series.key]} ${series.label.toLowerCase()}`}
                                        />
                                    ))}
                                </div>
                            ))}
                        </div>

                        <div className="mt-2 flex justify-between gap-2 sm:gap-4">
                            {activity.map((month) => (
                                <span
                                    key={month.month}
                                    className="flex-1 text-center text-xs text-muted-foreground"
                                >
                                    {month.month}
                                </span>
                            ))}
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

export default DashboardActivityChart;
