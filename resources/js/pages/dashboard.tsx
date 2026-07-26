import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarClock,
    CircleCheck,
    RefreshCw,
    Sparkles,
    Trophy,
    UserPlus,
} from 'lucide-react';
import { DashboardActivityChart } from '@/components/dashboard-activity-chart';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';
import type { ActivityPoint, Stats } from '@/types';

interface DashboardProps {
    stats?: Stats;
    activity?: ActivityPoint[];
}

const statMeta = [
    {
        key: 'activeRegistrations',
        label: 'Active Registrations',
        zeroHint: 'No active registrations',
        icon: CircleCheck,
        chip: 'bg-emerald-500',
        value_text: 'text-emerald-600 dark:text-emerald-400',
        tint: 'from-emerald-500/10',
        bar: 'bg-emerald-500',
    },
    {
        key: 'competitionEntries',
        label: 'Competition Entries',
        zeroHint: 'No competition entries',
        icon: Trophy,
        chip: 'bg-amber-500',
        value_text: 'text-amber-600 dark:text-amber-400',
        tint: 'from-amber-500/10',
        bar: 'bg-amber-500',
    },
    {
        key: 'renewals',
        label: 'Season Renewals',
        zeroHint: 'No renewals yet',
        icon: CalendarClock,
        chip: 'bg-blue-500',
        value_text: 'text-blue-600 dark:text-blue-400',
        tint: 'from-blue-500/10',
        bar: 'bg-blue-500',
    },
] as const;

const actions = [
    {
        title: 'Rider Registration',
        description: 'Register as a new rider with UAEERF',
        body: 'Complete your rider registration and receive your official UAEERF rider ID.',
        href: '/rider/registration',
        cta: 'Register Now',
        price: 'AED 100',
        icon: UserPlus,
        iconClass: 'bg-primary/10 text-primary',
        accent: 'bg-primary',
        primary: true,
    },
    {
        title: 'Rider Renewal',
        description: 'Renew your rider registration',
        body: 'Renew your existing rider registration for the new season.',
        href: '/rider/renewal',
        cta: 'Renew Registration',
        price: 'AED 50',
        icon: RefreshCw,
        iconClass: 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
        accent: 'bg-blue-500',
        primary: false,
    },
    {
        title: 'Show Jumping Entry',
        description: 'Enter a competition',
        body: 'Register for show jumping competitions and events.',
        href: '/jumping/entry',
        cta: 'Enter Competition',
        price: 'AED 150',
        icon: Sparkles,
        iconClass: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
        accent: 'bg-amber-500',
        primary: false,
    },
];

export default function Dashboard({ stats, activity = [] }: DashboardProps) {
    const { auth } = usePage().props;
    const firstName = auth?.user?.name?.split(' ')[0] ?? '';

    const values = stats ?? {
        activeRegistrations: 0,
        competitionEntries: 0,
        renewals: 0,
    };
    const recordTotal =
        values.activeRegistrations +
        values.competitionEntries +
        values.renewals;

    return (
        <>
            <Head title="Dashboard" />

            <div className="space-y-8 p-6">
                <section className="relative animate-fade-up overflow-hidden rounded-2xl border bg-gradient-to-br from-primary/12 via-background to-background p-6 sm:p-8">
                    <div
                        aria-hidden
                        className="absolute inset-0 bg-grid-pattern [mask-image:radial-gradient(ellipse_at_top_left,black,transparent_75%)]"
                    />
                    <div className="relative flex flex-wrap items-end justify-between gap-6">
                        <div className="space-y-2">
                            <h1 className="text-3xl font-bold tracking-tight sm:text-4xl">
                                {firstName
                                    ? `Welcome back, ${firstName}`
                                    : 'Dashboard'}
                            </h1>
                            <p className="max-w-2xl text-muted-foreground">
                                Manage your registrations, renewals, and
                                competition entries with the UAE Equestrian
                                &amp; Racing Federation.
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {recordTotal > 0
                                    ? `${recordTotal} record${recordTotal === 1 ? '' : 's'} on your account`
                                    : 'No records yet — start with a rider registration below.'}
                            </p>
                        </div>
                        <span className="inline-flex items-center gap-2 rounded-full border border-primary/25 bg-primary/5 px-3.5 py-1.5 text-xs font-semibold tracking-wide text-primary">
                            <Sparkles className="size-3.5" />
                            2026 SEASON
                        </span>
                    </div>
                </section>

                <div className="grid gap-6 md:grid-cols-3">
                    {statMeta.map((stat, index) => {
                        const value = values[stat.key];
                        // Share of all records, so the bar reads as weight
                        // rather than an arbitrary length.
                        const share =
                            recordTotal > 0 ? (value / recordTotal) * 100 : 0;

                        return (
                            <Card
                                key={stat.label}
                                className={`group relative animate-fade-up overflow-hidden bg-gradient-to-br ${stat.tint} to-transparent transition-all duration-300 hover:-translate-y-1 hover:shadow-lg`}
                                style={{
                                    animationDelay: `${100 + index * 100}ms`,
                                }}
                            >
                                <span
                                    aria-hidden
                                    className={`absolute inset-x-0 top-0 h-1 ${stat.bar} opacity-70 transition-opacity duration-300 group-hover:opacity-100`}
                                />
                                <CardHeader className="flex flex-row items-start justify-between space-y-0">
                                    <div className="space-y-1">
                                        <CardDescription className="text-xs font-medium tracking-wide uppercase">
                                            {stat.label}
                                        </CardDescription>
                                        <CardTitle
                                            className={`text-4xl font-bold tabular-nums ${stat.value_text}`}
                                        >
                                            {value}
                                        </CardTitle>
                                    </div>
                                    <div
                                        className={`flex size-11 items-center justify-center rounded-xl text-white shadow-sm transition-transform duration-300 group-hover:scale-110 group-hover:-rotate-3 ${stat.chip}`}
                                    >
                                        <stat.icon className="size-5" />
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-2">
                                    <div className="h-1.5 overflow-hidden rounded-full bg-border/60">
                                        <span
                                            className={`block h-full rounded-full ${stat.bar} transition-[width] duration-500`}
                                            style={{ width: `${share}%` }}
                                        />
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        {value > 0
                                            ? `${value} on record · ${Math.round(share)}% of your activity`
                                            : stat.zeroHint}
                                    </p>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>

                <DashboardActivityChart activity={activity} />

                <div className="space-y-1">
                    <h2 className="text-xl font-semibold tracking-tight">
                        Quick Actions
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Get started with these common tasks
                    </p>
                </div>

                <div className="grid gap-6 md:grid-cols-3">
                    {actions.map((action, index) => (
                        <Card
                            key={action.title}
                            className="group relative animate-fade-up overflow-hidden transition-all duration-300 hover:-translate-y-1 hover:border-primary/30 hover:shadow-lg"
                            style={{ animationDelay: `${450 + index * 100}ms` }}
                        >
                            <span
                                aria-hidden
                                className={`absolute inset-x-0 top-0 h-1 ${action.accent} opacity-0 transition-opacity duration-300 group-hover:opacity-80`}
                            />
                            <CardHeader>
                                <div className="flex items-start justify-between">
                                    <div
                                        className={`flex size-11 items-center justify-center rounded-lg transition-transform duration-300 group-hover:scale-110 ${action.iconClass}`}
                                    >
                                        <action.icon className="size-5" />
                                    </div>
                                    <Badge variant="secondary">
                                        {action.price}
                                    </Badge>
                                </div>
                                <CardTitle className="mt-4">
                                    {action.title}
                                </CardTitle>
                                <CardDescription>
                                    {action.description}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-1 flex-col justify-between gap-4">
                                <p className="text-sm text-muted-foreground">
                                    {action.body}
                                </p>
                                <Button
                                    asChild
                                    variant={
                                        action.primary ? 'default' : 'outline'
                                    }
                                    className={`w-full transition-all duration-300 ${action.primary ? 'shadow-md shadow-primary/20 group-hover:shadow-lg group-hover:shadow-primary/30' : 'group-hover:border-primary/40'}`}
                                >
                                    <Link href={action.href}>
                                        {action.cta}
                                        <ArrowRight className="size-4 transition-transform duration-300 group-hover:translate-x-1" />
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
