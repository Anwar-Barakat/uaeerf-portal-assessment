import { Link } from '@inertiajs/react';
import { Card } from '@/components/ui/card';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="relative min-h-svh overflow-y-auto bg-gradient-to-br from-slate-100 via-white to-slate-100 dark:from-slate-950 dark:via-slate-900 dark:to-slate-950">
            <div className="pointer-events-none absolute inset-0 overflow-hidden">
                <div className="absolute inset-0 bg-grid-pattern [mask-image:radial-gradient(ellipse_60%_60%_at_50%_40%,black,transparent)]" />
                <div className="absolute -top-24 -left-24 h-96 w-96 animate-float rounded-full bg-primary/20 blur-3xl dark:bg-primary/10" />
                <div className="absolute -right-24 -bottom-24 h-96 w-96 animate-float-slow rounded-full bg-emerald-400/20 blur-3xl dark:bg-emerald-500/10" />
                <div className="absolute top-1/2 left-1/2 h-[32rem] w-[32rem] -translate-x-1/2 -translate-y-1/2 animate-float rounded-full bg-sky-300/10 blur-3xl [animation-delay:-8s] dark:bg-sky-500/5" />
            </div>

            <div className="relative flex min-h-svh items-center justify-center p-4 sm:p-6">
                <div className="w-full max-w-md py-8">
                    <Card className="animate-fade-up border border-border/60 bg-card/80 shadow-2xl shadow-primary/10 backdrop-blur-xl">
                        <div className="space-y-7 p-6 sm:p-8">
                            <div className="flex flex-col items-center gap-5">
                                <Link
                                    href={home()}
                                    className="group flex flex-col items-center gap-4"
                                >
                                    <div className="relative">
                                        <div className="absolute inset-0 rounded-2xl bg-primary/20 blur-xl transition-all duration-300 group-hover:bg-primary/35" />
                                        <img
                                            src="/images/logo.jpeg"
                                            alt="UAEERF Logo"
                                            className="relative h-16 w-auto rounded-xl object-contain ring-1 ring-border/50 transition-transform duration-300 group-hover:scale-105 sm:h-20"
                                        />
                                    </div>
                                    <div className="text-center">
                                        <p className="text-lg font-bold tracking-tight text-foreground sm:text-xl">
                                            UAE Equestrian & Racing Federation
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            الاتحاد الإماراتي للفروسية والسباق
                                        </p>
                                    </div>
                                </Link>

                                <div className="h-px w-full bg-gradient-to-r from-transparent via-border to-transparent" />

                                <div
                                    className="w-full animate-fade-up space-y-1.5 text-center"
                                    style={{ animationDelay: '100ms' }}
                                >
                                    <h1 className="text-xl font-bold tracking-tight sm:text-2xl">
                                        {title}
                                    </h1>
                                    <p className="text-sm text-balance text-muted-foreground">
                                        {description}
                                    </p>
                                </div>
                            </div>

                            <div
                                className="animate-fade-up"
                                style={{ animationDelay: '200ms' }}
                            >
                                {children}
                            </div>
                        </div>
                    </Card>

                    <div
                        className="mt-6 animate-fade-in text-center"
                        style={{ animationDelay: '400ms' }}
                    >
                        <p className="text-xs text-muted-foreground">
                            © {new Date().getFullYear()} UAE Equestrian & Racing
                            Federation. All rights reserved.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}
