import { Head, useForm } from '@inertiajs/react';
import { ArrowRight, Loader2, ShieldCheck } from 'lucide-react';
import type { FormEventHandler } from 'react';
import { initiateRenewal } from '@/actions/App/Http/Controllers/RiderController';
import FormSelect, { dedupeOptions, toOptions } from '@/components/form-select';
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

type RiderResult = {
    rider_id: string;
    name: string;
    stable_id?: string | null;
    from_stable?: boolean;
    dob: string | null;
    nationality: string | null;
    registered_current_season: boolean;
    /** Why the federation would refuse this rider, decided server-side. */
    blocked_reason?: string | null;
};

const SHOW_JUMPING = '2';

interface Props {
    /** Only the season the federation is currently renewing into. */
    seasons: Array<{ Code: number; Name: string }>;
    disciplines: Array<{ Code: number; Name: string }>;
    divisionLevels: Array<{ Code: number; Name: string }>;
    /** `DivisionLevel` ties a category to a jumping division; "0" is neither. */
    categories: Array<{ Code: number; Name: string; DivisionLevel?: string }>;
    stableId: string;
    /** Only riders the federation would accept a renewal for. */
    riders: RiderResult[];
    /** How many were left out because they cannot be renewed. */
    hiddenRiders: number;
}

export default function RiderRenewal({
    seasons,
    disciplines,
    divisionLevels,
    categories,
    stableId,
    riders,
    hiddenRiders,
}: Props) {
    const { data, setData, post, processing, errors, setError, clearErrors } =
        useForm({
            rider_id: '',
            // One renewable season: nothing to choose, so it starts chosen.
            season_id: seasons.length === 1 ? String(seasons[0].Code) : '',
            // DivisionID on the renewal web service; it rejects the submission
            // without one.
            discipline_id: '',
            // Required for every discipline.
            weight: '',
            // Show jumping only.
            jumping_division_level: '',
            competition_level: '',
        });

    // The list holds only riders the federation would accept - the server has
    // already dropped the rest - so every option here can be submitted.
    // Deduplicated because the federation view lists a rider once per stable he
    // has been kept at.
    const riderOptions = dedupeOptions(
        (riders ?? []).map((rider) => ({
            value: rider.rider_id,
            label: `${rider.name} (${rider.rider_id})`,
            description: rider.from_stable ? 'Your stable' : undefined,
            keywords: [rider.rider_id],
        })),
    );

    const listOptions = (items: Array<{ Code: number; Name: string }>) =>
        toOptions(
            items,
            (item) => item.Code,
            (item) => item.Name,
        );

    // A category belongs to one jumping division, and the federation's own list
    // says which: `DivisionLevel`. Offering all 25 let someone pick an
    // endurance grade or an official's role as a show jumping level.
    const categoryOptions = listOptions(
        categories.filter(
            (category) =>
                String(category.DivisionLevel ?? '') ===
                data.jumping_division_level,
        ),
    );

    // The searchable selects are buttons, not native inputs, so the browser
    // cannot enforce `required` on them. Catch empty ones here instead.
    const requiredSelects: Array<[keyof typeof data, string]> = [
        ['season_id', 'Season'],
        ['discipline_id', 'Discipline'],
        ...(data.discipline_id === SHOW_JUMPING
            ? ([
                  ['jumping_division_level', 'Division level'],
                  ['competition_level', 'Competition category'],
              ] as Array<[keyof typeof data, string]>)
            : []),
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

        post(initiateRenewal().url);
    };

    return (
        <>
            <Head title="Rider Renewal" />

            <div className="space-y-8 p-6">
                <div className="space-y-1">
                    <h1 className="text-3xl font-bold tracking-tight">
                        Rider Renewal
                    </h1>
                    <p className="text-muted-foreground">
                        Renew your rider registration for the upcoming season
                        and keep your status active for continued participation.
                    </p>
                </div>

                <Card>
                    <CardHeader className="border-b pb-6">
                        <div className="flex items-start justify-between gap-4">
                            <div className="space-y-1">
                                <CardTitle className="text-2xl">
                                    Season Renewal
                                </CardTitle>
                                <CardDescription>
                                    Select your rider profile and season to
                                    renew
                                </CardDescription>
                            </div>
                            <Badge variant="secondary">Step 1 of 2</Badge>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-8">
                            {/* Grouped into sections on a two-column grid: the
                                form is short enough to take in at a glance
                                instead of scrolling through one long column. */}
                            <section className="space-y-4">
                                <div>
                                    <h3 className="text-sm font-semibold">
                                        Rider
                                    </h3>
                                    <p className="text-xs text-muted-foreground">
                                        Who the renewal is for
                                    </p>
                                </div>
                                <FormSelect
                                    id="rider_id"
                                    label="Rider"
                                    required
                                    value={data.rider_id}
                                    onChange={(value) =>
                                        setData('rider_id', value)
                                    }
                                    options={riderOptions}
                                    placeholder="Select rider"
                                    searchPlaceholder="Search by name or rider id..."
                                    emptyText="No rider matches."
                                    hint={`${riderOptions.length} rider${riderOptions.length === 1 ? '' : 's'} can be renewed${
                                        hiddenRiders > 0
                                            ? `. ${hiddenRiders} not shown: already registered for this season, or an application is already in progress`
                                            : ''
                                    }${
                                        stableId
                                            ? `. Your stable (${stableId}) is listed first`
                                            : ''
                                    }.`}
                                    error={errors.rider_id}
                                />
                            </section>

                            <section className="space-y-4 border-t pt-6">
                                <div>
                                    <h3 className="text-sm font-semibold">
                                        Renewal Details
                                    </h3>
                                    <p className="text-xs text-muted-foreground">
                                        Season and discipline being renewed
                                    </p>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <FormSelect
                                        id="season_id"
                                        label="Season"
                                        required
                                        value={data.season_id}
                                        onChange={(value) =>
                                            setData('season_id', value)
                                        }
                                        options={listOptions(seasons)}
                                        placeholder="Select season"
                                        searchPlaceholder="Search seasons..."
                                        error={errors.season_id}
                                        hint={
                                            seasons.length === 1
                                                ? 'The season the federation is currently renewing into'
                                                : undefined
                                        }
                                    />

                                    <FormSelect
                                        id="discipline_id"
                                        label="Discipline"
                                        required
                                        value={data.discipline_id}
                                        onChange={(value) =>
                                            setData('discipline_id', value)
                                        }
                                        options={listOptions(disciplines)}
                                        placeholder="Select discipline"
                                        searchPlaceholder="Search disciplines..."
                                        error={errors.discipline_id}
                                    />

                                    <div className="space-y-2">
                                        <Label htmlFor="weight">
                                            Weight (kg) *
                                        </Label>
                                        <Input
                                            id="weight"
                                            type="number"
                                            min={20}
                                            max={250}
                                            value={data.weight}
                                            onChange={(e) =>
                                                setData(
                                                    'weight',
                                                    e.target.value,
                                                )
                                            }
                                            required
                                        />
                                        {errors.weight && (
                                            <p className="text-sm text-destructive">
                                                {errors.weight}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </section>

                            {/* Show jumping asks for two extra fields; the
                                service rejects the submission without them. */}
                            {data.discipline_id === SHOW_JUMPING && (
                                <section className="space-y-4 border-t pt-6">
                                    <div>
                                        <h3 className="text-sm font-semibold">
                                            Show Jumping
                                        </h3>
                                        <p className="text-xs text-muted-foreground">
                                            Required for this discipline
                                        </p>
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <FormSelect
                                            id="jumping_division_level"
                                            label="Division Level"
                                            required
                                            value={data.jumping_division_level}
                                            onChange={(value) => {
                                                setData(
                                                    'jumping_division_level',
                                                    value,
                                                );

                                                // The categories on offer
                                                // change with the division, so
                                                // a category picked under the
                                                // old one no longer applies.
                                                setData('competition_level', '');
                                            }}
                                            options={listOptions(
                                                divisionLevels,
                                            )}
                                            placeholder="Select division level"
                                            searchPlaceholder="Search division levels..."
                                            error={
                                                errors.jumping_division_level
                                            }
                                        />

                                        <FormSelect
                                            id="competition_level"
                                            label="Competition Category"
                                            required
                                            value={data.competition_level}
                                            onChange={(value) =>
                                                setData(
                                                    'competition_level',
                                                    value,
                                                )
                                            }
                                            options={categoryOptions}
                                            placeholder={
                                                data.jumping_division_level
                                                    ? 'Select category'
                                                    : 'Choose a division level first'
                                            }
                                            disabled={
                                                !data.jumping_division_level
                                            }
                                            searchPlaceholder="Search categories..."
                                            error={errors.competition_level}
                                            hint={`Only categories for the chosen division level`}
                                        />
                                    </div>
                                </section>
                            )}

                            {/* Without this, a validation error on a field the
                                form does not render fails silently - the submit
                                button simply appears to do nothing. */}
                            {Object.keys(errors).length > 0 && (
                                <div className="rounded-lg border border-destructive/40 bg-destructive/10 p-4">
                                    <p className="mb-2 text-sm font-semibold text-destructive">
                                        Please fix the following before
                                        continuing:
                                    </p>
                                    <ul className="list-inside list-disc space-y-1 text-sm text-destructive">
                                        {Object.entries(errors).map(
                                            ([key, message]) => (
                                                <li key={key}>
                                                    {message as string}
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                </div>
                            )}

                            <div className="rounded-lg border bg-muted/40 p-5">
                                <div className="mb-3 flex items-center justify-between">
                                    <div>
                                        <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                            Renewal Fee
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Secure payment via PayTabs
                                        </p>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-3xl font-bold text-primary">
                                            50
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
                                disabled={processing || !data.rider_id}
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

RiderRenewal.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Rider Renewal', href: '/rider/renewal' },
    ],
};
