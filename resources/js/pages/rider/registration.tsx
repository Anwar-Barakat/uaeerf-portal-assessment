import { Head, useForm } from '@inertiajs/react';
import { ArrowRight, Loader2, ShieldCheck } from 'lucide-react';
import type { FormEventHandler } from 'react';
import FormSelect, { toOptions } from '@/components/form-select';
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
import { register } from '@/routes/rider';

type ListItem = { Code: number; Name: string };
type CountryItem = ListItem & { ShortName?: string };

interface Props {
    disciplines: ListItem[];
    categories: ListItem[];
    cities: ListItem[];
    countries: CountryItem[];
    genders: ListItem[];
    visaCategories: ListItem[];
    /** Why the federation will not accept a submission from this account, if so. */
    submissionBlockedReason: string | null;
}

export default function RiderRegistration({
    disciplines,
    categories,
    cities,
    countries,
    genders,
    visaCategories,
    submissionBlockedReason,
}: Props) {
    const { data, setData, post, processing, errors, setError, clearErrors } =
        useForm({
            first_name: '',
            last_name: '',
            date_of_birth: '',
            gender_id: '',
            nationality_id: '',
            city_id: '',
            country_id: '',
            email: '',
            mobile: '',
            address: '',
            po_box: '',
            weight: '',
            visa_category: '',
            eid: '',
            register_season: true,
            register_fei: false,
            passport_number: '',
            discipline_id: '',
            category_id: '',
        });

    const listOptions = (items: ListItem[] | undefined) =>
        toOptions(
            items,
            (item) => item.Code,
            (item) => item.Name,
        );

    const visaCategoryOptions = listOptions(visaCategories);
    const genderOptions = listOptions(genders);
    const cityOptions = listOptions(cities);
    const disciplineOptions = listOptions(disciplines);
    const categoryOptions = listOptions(categories);
    // Countries are also searchable by their short code (UAE, GBR, ...).
    const countryOptions = toOptions(
        countries,
        (country) => country.Code,
        (country) => country.Name,
        (country) => (country.ShortName ? [country.ShortName] : []),
    );

    // The searchable selects are buttons, not native inputs, so the browser
    // cannot enforce `required` on them. Catch empty ones here instead.
    const requiredSelects: Array<[keyof typeof data, string]> = [
        ['visa_category', 'Visa category'],
        ['gender_id', 'Gender'],
        ['nationality_id', 'Nationality'],
        ['country_id', 'Country of residence'],
        ['city_id', 'City'],
        ['discipline_id', 'Discipline'],
        ['category_id', 'Category'],
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

        post(register().url);
    };

    const selectNationality = (code: string) => {
        setData('nationality_id', code);
    };

    const fieldError = (key: keyof typeof errors) =>
        errors[key] ? (
            <p className="text-sm text-destructive">{errors[key]}</p>
        ) : null;

    return (
        <>
            <Head title="Rider Registration" />

            <div className="space-y-8 p-6">
                <div className="space-y-1">
                    <h1 className="text-3xl font-bold tracking-tight">
                        Rider Registration
                    </h1>
                    <p className="text-muted-foreground">
                        Register as a new rider with the UAE Equestrian &amp;
                        Racing Federation. Complete the form below to begin your
                        registration process.
                    </p>
                </div>

                <Card>
                    <CardHeader className="border-b pb-6">
                        <div className="flex items-start justify-between gap-4">
                            <div className="space-y-1">
                                <CardTitle className="text-2xl">
                                    New Rider Application
                                </CardTitle>
                                <CardDescription>
                                    All fields marked with * are required
                                </CardDescription>
                            </div>
                            <Badge variant="secondary">Step 1 of 2</Badge>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {submissionBlockedReason && (
                            <Alert className="mb-6">
                                <AlertDescription>
                                    {submissionBlockedReason}
                                </AlertDescription>
                            </Alert>
                        )}
                        <form onSubmit={submit} className="space-y-8">
                            <div className="space-y-4">
                                <h3 className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                                    Rider Details
                                </h3>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <FormSelect
                                        id="visa_category"
                                        label="Visa Category"
                                        required
                                        value={data.visa_category}
                                        onChange={(value) =>
                                            setData('visa_category', value)
                                        }
                                        options={visaCategoryOptions}
                                        placeholder="Select visa category"
                                        searchPlaceholder="Search visa categories..."
                                        error={errors.visa_category}
                                    />

                                    <div className="space-y-2">
                                        <Label htmlFor="eid">
                                            Emirates ID * (784-YYYY-NNNNNNN-N,
                                            YYYY = birth year)
                                        </Label>
                                        <Input
                                            id="eid"
                                            type="text"
                                            value={data.eid}
                                            onChange={(e) =>
                                                setData('eid', e.target.value)
                                            }
                                            placeholder="784-1990-1234567-1"
                                            maxLength={18}
                                            required
                                        />
                                        {fieldError('eid')}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="first_name">
                                            First Name (Including Middle Name) *
                                        </Label>
                                        <Input
                                            id="first_name"
                                            type="text"
                                            value={data.first_name}
                                            onChange={(e) =>
                                                setData(
                                                    'first_name',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="As per passport"
                                            required
                                        />
                                        {fieldError('first_name')}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="last_name">
                                            Last Name *
                                        </Label>
                                        <Input
                                            id="last_name"
                                            type="text"
                                            value={data.last_name}
                                            onChange={(e) =>
                                                setData(
                                                    'last_name',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="As per passport"
                                            required
                                        />
                                        {fieldError('last_name')}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="date_of_birth">
                                            Date of Birth * (min. 11 years old)
                                        </Label>
                                        <Input
                                            id="date_of_birth"
                                            type="date"
                                            value={data.date_of_birth}
                                            onChange={(e) =>
                                                setData(
                                                    'date_of_birth',
                                                    e.target.value,
                                                )
                                            }
                                            required
                                        />
                                        {fieldError('date_of_birth')}
                                    </div>

                                    <FormSelect
                                        id="gender_id"
                                        label="Gender"
                                        required
                                        value={data.gender_id}
                                        onChange={(value) =>
                                            setData('gender_id', value)
                                        }
                                        options={genderOptions}
                                        placeholder="Select gender"
                                        searchPlaceholder="Search genders..."
                                        error={errors.gender_id}
                                    />

                                    <FormSelect
                                        id="nationality_id"
                                        label="Nationality"
                                        required
                                        value={data.nationality_id}
                                        onChange={selectNationality}
                                        options={countryOptions}
                                        placeholder="Select nationality"
                                        searchPlaceholder="Search countries..."
                                        emptyText="No country matches."
                                        error={errors.nationality_id}
                                    />

                                    <div className="space-y-2">
                                        <Label htmlFor="weight">
                                            Weight (kg) *
                                        </Label>
                                        <Input
                                            id="weight"
                                            type="number"
                                            min="20"
                                            max="200"
                                            step="0.1"
                                            value={data.weight}
                                            onChange={(e) =>
                                                setData(
                                                    'weight',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="70"
                                            required
                                        />
                                        {fieldError('weight')}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="passport_number">
                                            Passport Number
                                        </Label>
                                        <Input
                                            id="passport_number"
                                            type="text"
                                            value={data.passport_number}
                                            onChange={(e) =>
                                                setData(
                                                    'passport_number',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        {fieldError('passport_number')}
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-4">
                                <h3 className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                                    Contact Details
                                </h3>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="email">Email *</Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            value={data.email}
                                            onChange={(e) =>
                                                setData('email', e.target.value)
                                            }
                                            placeholder="rider@example.com"
                                            required
                                        />
                                        {fieldError('email')}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="mobile">
                                            Mobile Number *
                                        </Label>
                                        <Input
                                            id="mobile"
                                            type="tel"
                                            value={data.mobile}
                                            onChange={(e) =>
                                                setData(
                                                    'mobile',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="+971501234567"
                                            required
                                        />
                                        {fieldError('mobile')}
                                    </div>

                                    <FormSelect
                                        id="country_id"
                                        label="Country of Residence"
                                        required
                                        value={data.country_id}
                                        onChange={(value) =>
                                            setData('country_id', value)
                                        }
                                        options={countryOptions}
                                        placeholder="Select country"
                                        searchPlaceholder="Search countries..."
                                        emptyText="No country matches."
                                        error={errors.country_id}
                                    />

                                    <FormSelect
                                        id="city_id"
                                        label="City"
                                        required
                                        value={data.city_id}
                                        onChange={(value) =>
                                            setData('city_id', value)
                                        }
                                        options={cityOptions}
                                        placeholder="Select city"
                                        searchPlaceholder="Search cities..."
                                        emptyText="No city matches."
                                        error={errors.city_id}
                                    />

                                    <div className="space-y-2">
                                        <Label htmlFor="address">
                                            Address *
                                        </Label>
                                        <Input
                                            id="address"
                                            type="text"
                                            value={data.address}
                                            onChange={(e) =>
                                                setData(
                                                    'address',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Street, building, area"
                                            required
                                        />
                                        {fieldError('address')}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="po_box">PO Box *</Label>
                                        <Input
                                            id="po_box"
                                            type="text"
                                            value={data.po_box}
                                            onChange={(e) =>
                                                setData(
                                                    'po_box',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="12345"
                                            required
                                        />
                                        {fieldError('po_box')}
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-4">
                                <h3 className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                                    Registration
                                </h3>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <FormSelect
                                        id="discipline_id"
                                        label="Discipline"
                                        required
                                        value={data.discipline_id}
                                        onChange={(value) =>
                                            setData('discipline_id', value)
                                        }
                                        options={disciplineOptions}
                                        placeholder="Select discipline"
                                        searchPlaceholder="Search disciplines..."
                                        error={errors.discipline_id}
                                    />

                                    <FormSelect
                                        id="category_id"
                                        label="Category"
                                        required
                                        value={data.category_id}
                                        onChange={(value) =>
                                            setData('category_id', value)
                                        }
                                        options={categoryOptions}
                                        placeholder="Select category"
                                        searchPlaceholder="Search categories..."
                                        error={errors.category_id}
                                    />
                                </div>
                            </div>

                            <div className="space-y-3">
                                <label className="flex items-center gap-3 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={data.register_season}
                                        onChange={(e) =>
                                            setData(
                                                'register_season',
                                                e.target.checked,
                                            )
                                        }
                                        className="size-4 rounded border-input"
                                    />
                                    I would like to register for the current
                                    season
                                </label>
                                <label className="flex items-center gap-3 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={data.register_fei}
                                        onChange={(e) =>
                                            setData(
                                                'register_fei',
                                                e.target.checked,
                                            )
                                        }
                                        className="size-4 rounded border-input"
                                    />
                                    I would like to Register in FEI (Additional
                                    Charges)
                                </label>
                            </div>

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
                                            Registration Fee
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Secure payment via PayTabs
                                        </p>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-3xl font-bold text-primary">
                                            100
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
                                    processing || !!submissionBlockedReason
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

RiderRegistration.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Rider Registration', href: '/rider/registration' },
    ],
};
