import { Head, useForm } from '@inertiajs/react';
import axios from 'axios';
import { ArrowRight, Loader2, Search, ShieldCheck } from 'lucide-react';
import type { FormEventHandler } from 'react';
import { useState } from 'react';
import {
    initiateEntry,
    validateEligibility as validateEligibilityRoute,
} from '@/actions/App/Http/Controllers/ShowJumpingController';
import FormSelect, { dedupeOptions } from '@/components/form-select';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

// Federation rider/horse ids are alphanumeric (E0007711, H000103), never numeric.
interface Rider {
    rider_id: string;
    name: string;
    registered_current_season: boolean;
}

interface Horse {
    horse_id: string;
    name: string;
    at_rest: boolean;
    registered_current_season: boolean;
}

interface EventClass {
    class_id: number;
    name: string;
    entry_fee: number | null;
    date: string | null;
}

interface CalendarEvent {
    event_id: string;
    name: string;
    location: string;
    closes_at: string | null;
    /** ClassCode is unique only within its event, so classes are per event. */
    classes: EventClass[];
}

interface Props {
    userRiders: Rider[];
    userHorses: Horse[];
    events: CalendarEvent[];
    stableId: string;
}

export default function ShowJumpingEntry({
    userRiders,
    userHorses,
    events,
    stableId,
}: Props) {
    const [validating, setValidating] = useState(false);
    const [eligibility, setEligibility] = useState<{
        eligible: boolean;
        rider_eligible: boolean;
        horse_eligible: boolean;
        message?: string;
        // The federation explains *why*, e.g. "Horse is not registered for the
        // current season." Far more use than a bare cross.
        details?: {
            riderDetails?: string | null;
            horseDetails?: string | null;
        };
    } | null>(null);

    const { data, setData, post, processing, errors, setError, clearErrors } =
        useForm({
            rider_id: '',
            horse_id: '',
            event_id: '',
            class_id: '',
            event_name: '',
        });

    // Classes are scoped to the chosen event.
    const selectedEvent =
        events?.find((event) => event.event_id === data.event_id) ?? null;

    // Entry requires a current-season registration, and a horse at rest cannot
    // compete. Rather than let someone pick an option the federation is certain
    // to refuse, disable it and say why.
    //
    // Deduplicated by id: the federation view holds a row per registration, so
    // the same rider or horse can arrive more than once, and a duplicate option
    // would let a disabled one be picked anyway.
    const riderOptions = dedupeOptions(
        (userRiders ?? []).map((rider) => ({
            value: rider.rider_id,
            label: `${rider.name} (${rider.rider_id})`,
            description: rider.registered_current_season
                ? undefined
                : 'Not registered this season — cannot be entered',
            disabled: !rider.registered_current_season,
            keywords: [rider.rider_id],
        })),
    );

    const horseOptions = dedupeOptions(
        (userHorses ?? []).map((horse) => {
            const reason = !horse.registered_current_season
                ? 'Not registered this season — cannot be entered'
                : horse.at_rest
                  ? 'At rest — cannot be entered'
                  : undefined;

            return {
                value: horse.horse_id,
                label: `${horse.name} (${horse.horse_id})`,
                description: reason,
                disabled: Boolean(reason),
                keywords: [horse.horse_id],
            };
        }),
    );

    const selectableRiders = riderOptions.filter((o) => !o.disabled).length;
    const selectableHorses = horseOptions.filter((o) => !o.disabled).length;

    const eventOptions = dedupeOptions(
        (events ?? []).map((event) => ({
            value: event.event_id,
            label: event.name,
            description:
                [
                    event.location,
                    event.closes_at ? `closes ${event.closes_at}` : '',
                ]
                    .filter(Boolean)
                    .join(' — ') || undefined,
            keywords: [event.event_id, event.location],
        })),
    );

    const classOptions = dedupeOptions(
        (selectedEvent?.classes ?? []).map((cls) => ({
            value: cls.class_id.toString(),
            label: `#${cls.class_id} ${cls.name}`,
            description: cls.date ?? undefined,
        })),
    );

    const validateEligibility = async () => {
        if (
            !data.rider_id ||
            !data.horse_id ||
            !data.event_id ||
            !data.class_id
        ) {
            return;
        }

        setValidating(true);
        setEligibility(null);

        try {
            const response = await axios.post(validateEligibilityRoute().url, {
                rider_id: data.rider_id,
                horse_id: data.horse_id,
                event_id: data.event_id,
                class_id: data.class_id,
            });

            setEligibility(response.data);
        } catch (error: any) {
            // Surface what actually went wrong. A bare "Validation failed"
            // hides a 422 behind what looks like a federation verdict.
            // Named to avoid shadowing the form's own `data`.
            const body = error.response?.data;
            const fieldErrors = body?.errors
                ? Object.values(body.errors).flat().join(' ')
                : null;

            setEligibility({
                eligible: false,
                rider_eligible: false,
                horse_eligible: false,
                message:
                    body?.error ??
                    fieldErrors ??
                    body?.message ??
                    'Could not reach the eligibility service.',
            });
        } finally {
            setValidating(false);
        }
    };

    // The searchable selects are buttons, not native inputs, so the browser
    // cannot enforce `required` on them. Catch empty ones here instead.
    const requiredSelects: Array<[keyof typeof data, string]> = [
        ['rider_id', 'Rider'],
        ['horse_id', 'Horse'],
        ['event_id', 'Event'],
        ['class_id', 'Class'],
    ];

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        const missing = requiredSelects.filter(([field]) => !data[field]);

        if (missing.length > 0) {
            clearErrors();
            missing.forEach(([field, label]) =>
                setError(field, `${label} is required.`),
            );

            return;
        }

        post(initiateEntry().url);
    };

    return (
        <>
            <Head title="Show Jumping Entry" />

            <div className="space-y-8 p-6">
                <div className="space-y-1">
                    <h1 className="text-3xl font-bold tracking-tight">
                        Show Jumping Entry
                    </h1>
                    <p className="text-muted-foreground">
                        Register for show jumping competitions. Enter your
                        details and validate eligibility before proceeding to
                        payment.
                    </p>
                </div>

                <Card>
                    <CardHeader className="border-b pb-6">
                        <div className="flex items-start justify-between gap-4">
                            <div className="space-y-1">
                                <CardTitle className="text-2xl">
                                    Competition Entry
                                </CardTitle>
                                <CardDescription>
                                    Complete the form and verify eligibility
                                </CardDescription>
                            </div>
                            <Badge variant="secondary">Step 1 of 2</Badge>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {!stableId && (
                            <Alert className="mb-6">
                                <AlertDescription>
                                    Your federation profile is not linked to a
                                    stable, so no riders or horses can be
                                    listed. Contact the federation to have your
                                    account linked before entering a
                                    competition.
                                </AlertDescription>
                            </Alert>
                        )}
                        {stableId &&
                            (!userRiders?.length || !userHorses?.length) && (
                                <Alert className="mb-6">
                                    <AlertDescription>
                                        No{' '}
                                        {!userRiders?.length
                                            ? 'riders'
                                            : 'horses'}{' '}
                                        are registered to stable {stableId}.
                                        Entries can only be made for riders and
                                        horses belonging to your stable.
                                    </AlertDescription>
                                </Alert>
                            )}
                        {!events?.length && (
                            <Alert className="mb-6">
                                <AlertDescription>
                                    No show jumping events are currently open
                                    for entries.
                                </AlertDescription>
                            </Alert>
                        )}
                        <form onSubmit={submit} className="space-y-8">
                            {/* Grouped into sections on a two-column grid, so
                                the whole entry is visible at once rather than
                                scrolling through one long column. */}
                            <section className="space-y-4">
                                <div>
                                    <h3 className="text-sm font-semibold">
                                        Combination
                                    </h3>
                                    <p className="text-xs text-muted-foreground">
                                        Who is competing
                                    </p>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <FormSelect
                                                                    id="rider_id"
                                                                    label="Select Rider"
                                                                    required
                                                                    value={data.rider_id}
                                                                    onChange={(value) => {
                                                                        setData('rider_id', value);
                                                                        setEligibility(null);
                                                                    }}
                                                                    options={riderOptions}
                                                                    placeholder="Select rider"
                                                                    searchPlaceholder="Search riders by name or id..."
                                                                    emptyText="No rider matches."
                                                                    hint={`${selectableRiders} of ${riderOptions.length} riders are registered for the current season and can be entered.`}
                                                                    error={errors.rider_id}
                                                                />

                                    <FormSelect
                                                                    id="horse_id"
                                                                    label="Select Horse"
                                                                    required
                                                                    value={data.horse_id}
                                                                    onChange={(value) => {
                                                                        setData('horse_id', value);
                                                                        setEligibility(null);
                                                                    }}
                                                                    options={horseOptions}
                                                                    placeholder="Select horse"
                                                                    searchPlaceholder="Search horses by name or id..."
                                                                    emptyText="No horse matches."
                                                                    hint={`${selectableHorses} of ${horseOptions.length} horses are registered for the current season and can be entered.`}
                                                                    error={errors.horse_id}
                                                                />
                                </div>
                            </section>

                            <section className="space-y-4 border-t pt-6">
                                <div>
                                    <h3 className="text-sm font-semibold">
                                        Competition
                                    </h3>
                                    <p className="text-xs text-muted-foreground">
                                        Event and class being entered
                                    </p>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <FormSelect
                                                                    id="event_id"
                                                                    label="Select Event"
                                                                    required
                                                                    value={data.event_id}
                                                                    onChange={(value) => {
                                                                        setData('event_id', value);
                                                                        setData(
                                                                            'event_name',
                                                                            events?.find(
                                                                                (e) => e.event_id === value,
                                                                            )?.name ?? '',
                                                                        );
                                                                        // A class from the previous event is
                                                                        // meaningless under a new one.
                                                                        setData('class_id', '');
                                                                        setEligibility(null);
                                                                    }}
                                                                    options={eventOptions}
                                                                    placeholder="Select event"
                                                                    searchPlaceholder="Search events..."
                                                                    emptyText="No event matches."
                                                                    error={errors.event_id}
                                                                />

                                    <FormSelect
                                                                    id="class_id"
                                                                    label="Class"
                                                                    required
                                                                    value={data.class_id}
                                                                    onChange={(value) => {
                                                                        setData('class_id', value);
                                                                        setEligibility(null);
                                                                    }}
                                                                    options={classOptions}
                                                                    disabled={!selectedEvent}
                                                                    placeholder={
                                                                        selectedEvent
                                                                            ? 'Select class'
                                                                            : 'Select an event first'
                                                                    }
                                                                    searchPlaceholder="Search classes..."
                                                                    emptyText="No class matches."
                                                                    hint={
                                                                        selectedEvent &&
                                                                        selectedEvent.classes.length === 0
                                                                            ? 'This event has no classes published yet.'
                                                                            : undefined
                                                                    }
                                                                    error={errors.class_id}
                                                                />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="event_name">
                                        Event Name
                                    </Label>
                                    {/* Filled from the selected event: it names
                                        the PayTabs cart, so it must match the
                                        event the entry is actually for. */}
                                    <Input
                                        id="event_name"
                                        type="text"
                                        value={data.event_name}
                                        readOnly
                                        tabIndex={-1}
                                        placeholder="Chosen automatically with the event"
                                        className="bg-muted/50 text-muted-foreground"
                                    />
                                    {errors.event_name && (
                                        <p className="text-sm text-destructive">
                                            {errors.event_name}
                                        </p>
                                    )}
                                </div>
                            </section>

                            <section className="space-y-4 border-t pt-6">
                                <div>
                                    <h3 className="text-sm font-semibold">
                                        Eligibility
                                    </h3>
                                    <p className="text-xs text-muted-foreground">
                                        Confirmed with the federation before any
                                        payment is taken
                                    </p>
                                </div>

                            <Button
                                type="button"
                                variant="outline"
                                className="w-full"
                                onClick={validateEligibility}
                                // All four are needed: the check returns early
                                // without them, so an enabled button would
                                // appear to do nothing.
                                disabled={
                                    validating ||
                                    !data.rider_id ||
                                    !data.horse_id ||
                                    !data.event_id ||
                                    !data.class_id
                                }
                            >
                                {validating ? (
                                    <>
                                        <Loader2 className="size-4 animate-spin" />
                                        Validating...
                                    </>
                                ) : (
                                    <>
                                        <Search className="size-4" />
                                        Check Eligibility
                                    </>
                                )}
                            </Button>

                            {eligibility && (
                                <Alert
                                    variant={
                                        eligibility.eligible
                                            ? 'default'
                                            : 'destructive'
                                    }
                                    className={
                                        eligibility.eligible
                                            ? 'border-green-500'
                                            : ''
                                    }
                                >
                                    <AlertDescription>
                                        {eligibility.eligible ? (
                                            <>
                                                <strong>✓ Eligible</strong>
                                                <br />
                                                Rider:{' '}
                                                {eligibility.rider_eligible
                                                    ? '✓'
                                                    : '✗'}{' '}
                                                | Horse:{' '}
                                                {eligibility.horse_eligible
                                                    ? '✓'
                                                    : '✗'}
                                            </>
                                        ) : (
                                            <>
                                                <strong>✗ Not Eligible</strong>
                                                <br />
                                                Rider:{' '}
                                                {eligibility.rider_eligible
                                                    ? '✓'
                                                    : '✗'}{' '}
                                                | Horse:{' '}
                                                {eligibility.horse_eligible
                                                    ? '✓'
                                                    : '✗'}
                                                {/* The federation's own wording
                                                    tells the user what to fix. */}
                                                {/* The service returns several
                                                    reasons in one string, each
                                                    prefixed "BASIC:*". Split
                                                    them into separate lines. */}
                                                <ul className="mt-2 list-inside list-disc space-y-0.5">
                                                    {/* Only explain the side
                                                        that failed - the passing
                                                        side answers "YES", which
                                                        reads as noise. */}
                                                    {[
                                                        eligibility.rider_eligible
                                                            ? null
                                                            : eligibility
                                                                  .details
                                                                  ?.riderDetails,
                                                        eligibility.horse_eligible
                                                            ? null
                                                            : eligibility
                                                                  .details
                                                                  ?.horseDetails,
                                                    ]
                                                        .filter(Boolean)
                                                        .flatMap((reason) =>
                                                            String(reason)
                                                                .split(
                                                                    /BASIC:\*?|\r?\n/,
                                                                )
                                                                .map((part) =>
                                                                    part.trim(),
                                                                )
                                                                .filter(
                                                                    Boolean,
                                                                ),
                                                        )
                                                        .map(
                                                            (reason, index) => (
                                                                <li key={index}>
                                                                    {reason}
                                                                </li>
                                                            ),
                                                        )}
                                                </ul>
                                                {eligibility.message && (
                                                    <span className="mt-1 block">
                                                        {eligibility.message}
                                                    </span>
                                                )}
                                            </>
                                        )}
                                    </AlertDescription>
                                </Alert>
                            )}
                            </section>

                            <div className="rounded-lg border bg-muted/40 p-5">
                                <div className="mb-3 flex items-center justify-between">
                                    <div>
                                        <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                            Entry Fee
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Secure payment via PayTabs
                                        </p>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-3xl font-bold text-primary">
                                            150
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            AED
                                        </p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                    <ShieldCheck className="size-4 text-primary" />
                                    <span>Encrypted and secure payment</span>
                                </div>
                            </div>

                            <Button
                                type="submit"
                                size="lg"
                                className="w-full"
                                disabled={
                                    processing ||
                                    !userRiders?.length ||
                                    !userHorses?.length ||
                                    !events?.length ||
                                    (eligibility !== null &&
                                        !eligibility.eligible)
                                }
                            >
                                {processing ? (
                                    <>
                                        <Loader2 className="size-4 animate-spin" />
                                        Processing...
                                    </>
                                ) : (
                                    <>
                                        Proceed to Secure Payment
                                        <ArrowRight className="size-4" />
                                    </>
                                )}
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ShowJumpingEntry.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Show Jumping Entry', href: '/jumping/entry' },
    ],
};
