import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Award,
    BadgeCheck,
    CheckCircle2,
    Clock,
    FileCheck,
    FileText,
    Globe,
    Mail,
    MapPin,
    QrCode,
    ShieldCheck,
    Sparkles,
    Trophy,
    UserPlus,
    Users,
    Zap,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard, login, register } from '@/routes';

const stats = [
    { value: '5,000+', label: 'Registered Riders', icon: Users },
    { value: '350+', label: 'Annual Competitions', icon: Trophy },
    { value: '60+', label: 'Affiliated Clubs', icon: Award },
    { value: '7', label: 'Emirates Covered', icon: Globe },
];

const emirates = [
    'Abu Dhabi',
    'Dubai',
    'Sharjah',
    'Ajman',
    'Umm Al Quwain',
    'Ras Al Khaimah',
    'Fujairah',
];

const services = [
    {
        title: 'Rider Registration',
        description:
            'Register as a new rider and obtain your official UAEERF ID.',
        body: 'Complete your registration with official documentation and certification.',
        fee: 'AED 100',
        href: '/rider/registration',
        icon: Users,
        chip: 'bg-emerald-500',
        border: 'border-t-emerald-500',
        text: 'text-emerald-600 dark:text-emerald-400',
        glow: 'group-hover:shadow-emerald-500/25',
    },
    {
        title: 'Season Renewal',
        description:
            'Renew your rider registration for continued participation.',
        body: 'Keep your status active and maintain access to all federation services.',
        fee: 'AED 50',
        href: '/rider/renewal',
        icon: FileText,
        chip: 'bg-blue-500',
        border: 'border-t-blue-500',
        text: 'text-blue-600 dark:text-blue-400',
        glow: 'group-hover:shadow-blue-500/25',
    },
    {
        title: 'Competition Entry',
        description: 'Register for show jumping and equestrian competitions.',
        body: 'Enter sanctioned competitions and track your competitive record.',
        fee: 'AED 150',
        href: '/jumping/entry',
        icon: Trophy,
        chip: 'bg-amber-500',
        border: 'border-t-amber-500',
        text: 'text-amber-600 dark:text-amber-400',
        glow: 'group-hover:shadow-amber-500/25',
    },
];

const steps = [
    {
        number: '01',
        title: 'Create your account',
        description: 'Sign up in minutes with your email and personal details.',
        icon: UserPlus,
    },
    {
        number: '02',
        title: 'Complete registration',
        description:
            'Submit your documents and pay securely online via PayTabs.',
        icon: FileCheck,
    },
    {
        number: '03',
        title: 'Ride & compete',
        description:
            'Receive your official UAEERF ID and enter sanctioned competitions.',
        icon: Trophy,
    },
];

const features = [
    {
        title: 'Official Certification',
        description: 'Government-recognized rider IDs and credentials.',
        icon: BadgeCheck,
        chip: 'bg-emerald-500',
    },
    {
        title: 'Secure Payments',
        description: 'Encrypted checkout powered by PayTabs.',
        icon: ShieldCheck,
        chip: 'bg-blue-500',
    },
    {
        title: 'Fast Processing',
        description: 'Instant confirmations and digital receipts.',
        icon: Zap,
        chip: 'bg-amber-500',
    },
    {
        title: '24/7 Access',
        description: 'Manage registrations anytime, anywhere.',
        icon: Clock,
        chip: 'bg-violet-500',
    },
    {
        title: 'Nationwide Coverage',
        description: 'Serving riders across all seven Emirates.',
        icon: Globe,
        chip: 'bg-rose-500',
    },
    {
        title: 'Digital Records',
        description: 'Your full history in one secure place.',
        icon: FileCheck,
        chip: 'bg-teal-500',
    },
];

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Welcome - UAE Equestrian & Racing Federation" />

            <div className="min-h-screen bg-background">
                {/* Header */}
                <header className="sticky top-0 z-50 border-b border-border/60 bg-background/85 shadow-sm shadow-black/[0.03] backdrop-blur-md">
                    <div className="mx-auto max-w-7xl px-4 py-3 sm:px-6 lg:px-8">
                        <div className="flex items-center justify-between">
                            <Link
                                href="/"
                                className="group flex items-center gap-3"
                            >
                                <img
                                    src="/images/logo.jpeg"
                                    alt="UAEERF"
                                    className="h-11 w-auto rounded-md ring-1 ring-border/50 transition-transform duration-300 group-hover:scale-105"
                                />
                                <div className="hidden sm:block">
                                    <p className="text-base leading-tight font-bold text-foreground">
                                        UAE Equestrian &amp; Racing Federation
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        الاتحاد الإماراتي للفروسية والسباق
                                    </p>
                                </div>
                            </Link>

                            <nav className="flex items-center gap-2">
                                {auth.user ? (
                                    <Button
                                        asChild
                                        className="shadow-md shadow-primary/20 transition-all duration-300 hover:shadow-lg hover:shadow-primary/30"
                                    >
                                        <Link href={dashboard()}>
                                            Dashboard
                                            <ArrowRight className="size-4" />
                                        </Link>
                                    </Button>
                                ) : (
                                    <>
                                        <Button asChild variant="ghost">
                                            <Link href={login()}>Log in</Link>
                                        </Button>
                                        <Button
                                            asChild
                                            className="shadow-md shadow-primary/20 transition-all duration-300 hover:shadow-lg hover:shadow-primary/30"
                                        >
                                            <Link href={register()}>
                                                Register
                                            </Link>
                                        </Button>
                                    </>
                                )}
                            </nav>
                        </div>
                    </div>
                </header>

                {/* Hero */}
                <section className="relative overflow-hidden">
                    <div className="pointer-events-none absolute inset-0">
                        <div className="absolute inset-0 bg-grid-pattern [mask-image:radial-gradient(ellipse_80%_70%_at_50%_30%,black,transparent)]" />
                        <div className="absolute -top-32 -left-32 size-[30rem] animate-float rounded-full bg-primary/15 blur-3xl" />
                        <div className="absolute top-0 right-0 size-[30rem] animate-float-slow rounded-full bg-amber-400/15 blur-3xl" />
                        <div className="absolute bottom-0 left-1/2 h-80 w-[60rem] -translate-x-1/2 rounded-[100%] bg-emerald-400/10 blur-3xl" />
                    </div>

                    <div className="relative mx-auto max-w-7xl px-4 pt-16 pb-12 sm:px-6 sm:pt-20 lg:px-8 lg:pt-28 lg:pb-16">
                        <div className="grid items-center gap-14 lg:grid-cols-2 lg:gap-10">
                            {/* Copy */}
                            <div className="space-y-7 text-center lg:text-left">
                                <div className="inline-flex animate-fade-up items-center gap-2 rounded-full border border-primary/25 bg-primary/5 px-4 py-1.5 text-sm font-medium text-primary shadow-sm shadow-primary/10">
                                    <span className="relative flex size-2">
                                        <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary opacity-60" />
                                        <span className="relative inline-flex size-2 rounded-full bg-primary" />
                                    </span>
                                    Official UAE Government Portal
                                    <Sparkles className="size-4" />
                                </div>

                                <h1
                                    className="animate-fade-up text-4xl leading-[1.08] font-bold tracking-tight text-balance sm:text-5xl lg:text-6xl xl:text-7xl"
                                    style={{ animationDelay: '100ms' }}
                                >
                                    Where UAE riders{' '}
                                    <span className="animate-shine bg-gradient-to-r from-amber-500 via-primary to-amber-600 bg-[length:200%_auto] bg-clip-text text-transparent">
                                        rise to victory
                                    </span>
                                </h1>

                                <p
                                    className="mx-auto max-w-xl animate-fade-up text-base leading-relaxed text-muted-foreground sm:text-lg lg:mx-0"
                                    style={{ animationDelay: '200ms' }}
                                >
                                    The official home of equestrian sport in the
                                    Emirates. Register as a rider, renew your
                                    season, and enter sanctioned competitions —
                                    all in one place.
                                </p>

                                <div
                                    className="flex animate-fade-up flex-wrap items-center justify-center gap-3 sm:gap-4 lg:justify-start"
                                    style={{ animationDelay: '300ms' }}
                                >
                                    {auth.user ? (
                                        <Button
                                            asChild
                                            size="lg"
                                            className="h-12 px-8 text-base font-semibold shadow-lg shadow-primary/25 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-primary/35"
                                        >
                                            <Link href={dashboard()}>
                                                Go to Dashboard
                                                <ArrowRight className="size-5" />
                                            </Link>
                                        </Button>
                                    ) : (
                                        <>
                                            <Button
                                                asChild
                                                size="lg"
                                                className="h-12 px-8 text-base font-semibold shadow-lg shadow-primary/25 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-primary/35"
                                            >
                                                <Link href={register()}>
                                                    Get Started
                                                    <ArrowRight className="size-5" />
                                                </Link>
                                            </Button>
                                            <Button
                                                asChild
                                                size="lg"
                                                variant="outline"
                                                className="h-12 bg-background/60 px-8 text-base backdrop-blur transition-all duration-300 hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md"
                                            >
                                                <Link href={login()}>
                                                    Sign In
                                                </Link>
                                            </Button>
                                        </>
                                    )}
                                </div>

                                <ul
                                    className="flex animate-fade-up flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm text-muted-foreground lg:justify-start"
                                    style={{ animationDelay: '400ms' }}
                                >
                                    <li className="flex items-center gap-2">
                                        <BadgeCheck className="size-4 text-primary" />
                                        Government recognized
                                    </li>
                                    <li className="flex items-center gap-2">
                                        <ShieldCheck className="size-4 text-primary" />
                                        Secure payments
                                    </li>
                                    <li className="flex items-center gap-2">
                                        <Zap className="size-4 text-primary" />
                                        Instant confirmation
                                    </li>
                                </ul>
                            </div>

                            {/* Hero visual: horse photo + rider ID card */}
                            <div
                                className="relative mx-auto mb-10 w-full max-w-md animate-fade-up lg:mb-6 lg:max-w-lg"
                                style={{ animationDelay: '250ms' }}
                            >
                                <div className="pointer-events-none absolute inset-0 -z-10 scale-110 rounded-full bg-primary/15 blur-3xl" />

                                <div className="relative overflow-hidden rounded-3xl shadow-xl ring-1 shadow-black/20 ring-black/10">
                                    <img
                                        src="/images/hero-horse.jpg"
                                        alt="Bay horse wearing a competition bridle"
                                        className="h-[26rem] w-full object-cover object-top transition-transform duration-700 hover:scale-105 sm:h-[30rem] lg:h-[34rem]"
                                    />
                                    <div className="pointer-events-none absolute inset-0 bg-gradient-to-t from-[#07130c] via-transparent to-transparent" />
                                    <div className="pointer-events-none absolute inset-0 bg-gradient-to-br from-emerald-950/30 via-transparent to-transparent" />
                                </div>

                                {/* Rider ID card overlay */}
                                <div className="absolute -bottom-8 left-1/2 w-[88%] max-w-xs -translate-x-1/2 animate-float-card rounded-2xl border border-white/15 bg-gradient-to-br from-white/15 via-white/10 to-white/5 p-4 shadow-2xl shadow-black/50 backdrop-blur-xl sm:left-6 sm:w-72 sm:translate-x-0 sm:p-5">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2.5">
                                            <img
                                                src="/images/logo.jpeg"
                                                alt=""
                                                className="h-8 w-auto rounded-md ring-1 ring-white/20"
                                            />
                                            <div>
                                                <p className="text-[10px] font-semibold tracking-widest text-amber-300 uppercase">
                                                    UAEERF
                                                </p>
                                                <p className="text-[10px] text-white/60">
                                                    Official Rider ID
                                                </p>
                                            </div>
                                        </div>
                                        <span className="rounded-full border border-amber-400/40 bg-amber-400/15 px-2.5 py-0.5 text-[10px] font-semibold tracking-wide text-amber-300">
                                            2026
                                        </span>
                                    </div>

                                    <div className="mt-4 flex items-end justify-between">
                                        <div className="space-y-2.5">
                                            <div>
                                                <p className="text-[10px] tracking-widest text-white/50 uppercase">
                                                    Rider
                                                </p>
                                                <p className="text-sm font-bold tracking-wide text-white">
                                                    AHMED AL MANSOORI
                                                </p>
                                                <p className="mt-0.5 font-mono text-xs font-semibold tracking-wider text-white/70">
                                                    AE-2026-05481
                                                </p>
                                            </div>
                                            <div className="flex items-center gap-1.5 text-emerald-300">
                                                <BadgeCheck className="size-3.5" />
                                                <span className="text-[10px] font-semibold tracking-wide">
                                                    VERIFIED
                                                </span>
                                            </div>
                                        </div>
                                        <div className="flex size-12 items-center justify-center rounded-lg border border-white/15 bg-white/10">
                                            <QrCode className="size-8 text-white/80" />
                                        </div>
                                    </div>
                                </div>

                                {/* floating toasts */}
                                <div
                                    className="absolute top-5 -right-3 hidden animate-float items-center gap-2 rounded-xl border border-border/60 bg-card/90 px-3.5 py-2.5 shadow-lg backdrop-blur-xl sm:flex"
                                    style={{ animationDelay: '-3s' }}
                                >
                                    <CheckCircle2 className="size-4 text-emerald-500" />
                                    <span className="text-xs font-medium text-foreground">
                                        Entry confirmed
                                    </span>
                                </div>
                                <div
                                    className="absolute right-4 -bottom-5 hidden animate-float items-center gap-2 rounded-xl border border-border/60 bg-card/90 px-3.5 py-2.5 shadow-lg backdrop-blur-xl lg:flex"
                                    style={{ animationDelay: '-7s' }}
                                >
                                    <ShieldCheck className="size-4 text-primary" />
                                    <span className="text-xs font-medium text-foreground">
                                        Payment received · AED 100
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* Stats band */}
                        <div className="mt-16 grid grid-cols-2 gap-3 sm:gap-4 lg:mt-20 lg:grid-cols-4">
                            {stats.map((stat, index) => (
                                <div
                                    key={stat.label}
                                    className="group animate-fade-up rounded-2xl border bg-card/80 p-5 text-center shadow-sm backdrop-blur transition-all duration-300 hover:-translate-y-1 hover:border-primary/30 hover:shadow-lg sm:p-6"
                                    style={{
                                        animationDelay: `${500 + index * 100}ms`,
                                    }}
                                >
                                    <div className="mx-auto mb-3 flex size-11 items-center justify-center rounded-xl border border-primary/25 bg-primary/10 text-primary transition-transform duration-300 group-hover:scale-110 group-hover:-rotate-3">
                                        <stat.icon className="size-5" />
                                    </div>
                                    <p className="text-2xl font-bold text-foreground sm:text-3xl">
                                        {stat.value}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground sm:text-sm">
                                        {stat.label}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Emirates marquee */}
                    <div className="relative border-t border-border/60 py-4">
                        <div className="overflow-hidden [mask-image:linear-gradient(to_right,transparent,black_10%,black_90%,transparent)]">
                            <div className="flex w-max animate-marquee gap-10 hover:[animation-play-state:paused]">
                                {[...emirates, ...emirates].map(
                                    (emirate, index) => (
                                        <span
                                            key={`${emirate}-${index}`}
                                            className="flex items-center gap-10 text-sm font-medium tracking-widest whitespace-nowrap text-muted-foreground/60 uppercase"
                                        >
                                            {emirate}
                                            <span className="size-1.5 rounded-full bg-primary/50" />
                                        </span>
                                    ),
                                )}
                            </div>
                        </div>
                    </div>
                </section>

                {/* Services */}
                <section className="bg-muted/30 py-16 sm:py-20">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="mb-10 text-center sm:mb-12">
                            <p className="mb-2 text-sm font-semibold tracking-widest text-primary uppercase">
                                What we offer
                            </p>
                            <h2 className="text-3xl font-bold tracking-tight lg:text-4xl">
                                Our Services
                            </h2>
                            <p className="mx-auto mt-3 max-w-xl text-muted-foreground">
                                Comprehensive equestrian services for riders and
                                clubs
                            </p>
                        </div>

                        <div className="mx-auto grid max-w-5xl gap-5 sm:gap-6 md:grid-cols-3">
                            {services.map((service) => (
                                <Card
                                    key={service.title}
                                    className={`group relative overflow-hidden border-t-4 ${service.border} transition-all duration-300 hover:-translate-y-1.5 hover:shadow-xl ${service.glow}`}
                                >
                                    <CardHeader>
                                        <div className="mb-4 flex items-center justify-between">
                                            <div
                                                className={`inline-flex size-12 items-center justify-center rounded-xl text-white shadow-md transition-transform duration-300 group-hover:scale-110 group-hover:-rotate-3 ${service.chip}`}
                                            >
                                                <service.icon className="size-6" />
                                            </div>
                                            <span
                                                className={`rounded-full bg-muted px-3 py-1 text-sm font-bold ${service.text}`}
                                            >
                                                {service.fee}
                                            </span>
                                        </div>
                                        <CardTitle className="text-lg">
                                            {service.title}
                                        </CardTitle>
                                        <CardDescription>
                                            {service.description}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="flex flex-1 flex-col justify-between gap-4">
                                        <p className="text-sm text-muted-foreground">
                                            {service.body}
                                        </p>
                                        <Button
                                            asChild
                                            variant="ghost"
                                            className={`w-fit px-0 ${service.text} hover:bg-transparent`}
                                        >
                                            <Link
                                                href={
                                                    auth.user
                                                        ? service.href
                                                        : register()
                                                }
                                            >
                                                Learn more
                                                <ArrowRight className="size-4 transition-transform duration-300 group-hover:translate-x-1.5" />
                                            </Link>
                                        </Button>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    </div>
                </section>

                {/* How it works */}
                <section className="py-16 sm:py-20">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="mb-10 text-center sm:mb-14">
                            <p className="mb-2 text-sm font-semibold tracking-widest text-primary uppercase">
                                Simple process
                            </p>
                            <h2 className="text-3xl font-bold tracking-tight lg:text-4xl">
                                How It Works
                            </h2>
                            <p className="mx-auto mt-3 max-w-xl text-muted-foreground">
                                From sign-up to the arena in three steps
                            </p>
                        </div>

                        <div className="relative mx-auto grid max-w-5xl gap-10 md:grid-cols-3 md:gap-8">
                            <div
                                aria-hidden
                                className="absolute top-8 right-[16%] left-[16%] hidden border-t-2 border-dashed border-primary/30 md:block"
                            />
                            {steps.map((step) => (
                                <div
                                    key={step.number}
                                    className="group relative text-center"
                                >
                                    <div className="relative mx-auto mb-5 flex size-16 items-center justify-center rounded-2xl border border-primary/25 bg-gradient-to-br from-amber-400 to-amber-600 text-white shadow-lg shadow-amber-500/25 transition-transform duration-300 group-hover:scale-110 group-hover:-rotate-3">
                                        <step.icon className="size-7" />
                                        <span className="absolute -top-2 -right-2 flex size-7 items-center justify-center rounded-full bg-emerald-950 text-[11px] font-bold text-amber-300 ring-2 ring-background">
                                            {step.number}
                                        </span>
                                    </div>
                                    <h3 className="text-lg font-semibold">
                                        {step.title}
                                    </h3>
                                    <p className="mx-auto mt-2 max-w-xs text-sm text-muted-foreground">
                                        {step.description}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* Features */}
                <section className="border-t bg-muted/30 py-16 sm:py-20">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="mb-10 text-center sm:mb-12">
                            <p className="mb-2 text-sm font-semibold tracking-widest text-primary uppercase">
                                Built for riders
                            </p>
                            <h2 className="text-3xl font-bold tracking-tight lg:text-4xl">
                                Why UAEERF
                            </h2>
                            <p className="mx-auto mt-3 max-w-xl text-muted-foreground">
                                A modern, trusted platform built for the UAE
                                equestrian community
                            </p>
                        </div>

                        <div className="mx-auto grid max-w-5xl gap-5 sm:grid-cols-2 sm:gap-6 lg:grid-cols-3">
                            {features.map((feature) => (
                                <div
                                    key={feature.title}
                                    className="group flex gap-4 rounded-xl border bg-card p-5 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-primary/30 hover:shadow-md"
                                >
                                    <div
                                        className={`flex size-11 shrink-0 items-center justify-center rounded-lg text-white shadow-sm transition-transform duration-300 group-hover:scale-110 ${feature.chip}`}
                                    >
                                        <feature.icon className="size-5" />
                                    </div>
                                    <div>
                                        <h3 className="font-semibold">
                                            {feature.title}
                                        </h3>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {feature.description}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* CTA */}
                <section className="py-16 sm:py-20">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="relative overflow-hidden rounded-3xl bg-[#07130c] px-6 py-14 text-center shadow-2xl sm:px-8 sm:py-16 lg:px-16">
                            <div className="pointer-events-none absolute inset-0">
                                <img
                                    src="/images/cta-horse.jpg"
                                    alt=""
                                    className="h-full w-full object-cover object-center opacity-25"
                                />
                                <div className="absolute inset-0 bg-gradient-to-r from-[#07130c] via-[#07130c]/70 to-[#07130c]" />
                                <div className="absolute inset-0 bg-grid-pattern-light [mask-image:radial-gradient(ellipse_70%_80%_at_50%_50%,black,transparent)]" />
                                <div className="absolute -top-16 -right-16 size-64 animate-float rounded-full bg-amber-400/20 blur-3xl" />
                                <div className="absolute -bottom-16 -left-16 size-64 animate-float-slow rounded-full bg-emerald-500/20 blur-3xl" />
                            </div>
                            <div className="relative mx-auto max-w-2xl space-y-5">
                                <h2 className="text-3xl font-bold tracking-tight text-white lg:text-4xl">
                                    Ready to{' '}
                                    <span className="animate-shine bg-gradient-to-r from-amber-300 via-yellow-500 to-amber-300 bg-[length:200%_auto] bg-clip-text text-transparent">
                                        ride
                                    </span>
                                    ?
                                </h2>
                                <p className="text-base text-white/80 sm:text-lg">
                                    Join the UAE Equestrian &amp; Racing
                                    Federation today and access all our
                                    services.
                                </p>
                                {!auth.user && (
                                    <div className="pt-2">
                                        <Button
                                            asChild
                                            size="lg"
                                            className="h-12 bg-amber-400 px-8 text-base font-semibold text-emerald-950 shadow-lg shadow-amber-400/25 transition-all duration-300 hover:-translate-y-0.5 hover:bg-amber-300 hover:shadow-xl hover:shadow-amber-400/40"
                                        >
                                            <Link href={register()}>
                                                Create Account
                                                <ArrowRight className="size-5" />
                                            </Link>
                                        </Button>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </section>

                {/* Footer */}
                <footer className="border-t bg-muted/30">
                    <div className="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                        <div className="grid gap-10 md:grid-cols-3">
                            <div className="flex items-start gap-3">
                                <img
                                    src="/images/logo.jpeg"
                                    alt="UAEERF"
                                    className="h-10 w-auto rounded-md ring-1 ring-border/50"
                                />
                                <div>
                                    <p className="text-sm font-semibold">
                                        UAE Equestrian &amp; Racing Federation
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        الاتحاد الإماراتي للفروسية والسباق
                                    </p>
                                    <p className="mt-3 max-w-xs text-xs leading-relaxed text-muted-foreground">
                                        The official governing body for
                                        equestrian sport and racing in the
                                        United Arab Emirates.
                                    </p>
                                </div>
                            </div>

                            <div>
                                <p className="mb-3 text-sm font-semibold">
                                    Services
                                </p>
                                <ul className="space-y-2">
                                    {services.map((service) => (
                                        <li key={service.title}>
                                            <Link
                                                href={
                                                    auth.user
                                                        ? service.href
                                                        : register()
                                                }
                                                className="text-sm text-muted-foreground transition-colors hover:text-primary"
                                            >
                                                {service.title}
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </div>

                            <div>
                                <p className="mb-3 text-sm font-semibold">
                                    Contact
                                </p>
                                <ul className="space-y-2 text-sm text-muted-foreground">
                                    <li className="flex items-center gap-2">
                                        <MapPin className="size-4 shrink-0 text-primary" />
                                        United Arab Emirates
                                    </li>
                                    <li className="flex items-center gap-2">
                                        <Mail className="size-4 shrink-0 text-primary" />
                                        info@uaeerf.ae
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div className="mt-10 flex flex-col items-center justify-between gap-2 border-t pt-6 md:flex-row">
                            <p className="text-xs text-muted-foreground">
                                © {new Date().getFullYear()} UAE Equestrian
                                &amp; Racing Federation. All rights reserved.
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Official Federation Portal
                            </p>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
