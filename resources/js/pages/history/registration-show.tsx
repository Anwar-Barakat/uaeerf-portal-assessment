import { Head } from '@inertiajs/react';
import { CreditCard, Mail, ShieldCheck, UserRound } from 'lucide-react';
import DetailCard from '@/components/detail-card';
import DetailRow from '@/components/detail-row';
import RecordHero from '@/components/record-hero';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { getStatusBadge } from '@/lib/status-badge';

interface Registration {
    id: number;
    rider_name: string;
    first_name: string | null;
    last_name: string | null;
    date_of_birth: string | null;
    nationality: string | null;
    nationality_id: string | null;
    gender_id: string | null;
    city_id: string | null;
    country_id: string | null;
    email: string | null;
    mobile: string | null;
    address: string | null;
    po_box: string | null;
    weight: string | null;
    visa_category: string | null;
    eid: string | null;
    passport_number: string | null;
    discipline_id: number | null;
    category_id: number | null;
    register_season: boolean | null;
    register_fei: boolean | null;
    status: string;
    tran_ref: string | null;
    error_message: string | null;
    completed_at: string | null;
    created_at: string;
}

export default function RegistrationShow({
    registration,
}: {
    registration: Registration;
}) {
    return (
        <>
            <Head title={`Registration #${registration.id}`} />

            <div className="space-y-6 p-6">
                <RecordHero
                    title={`Registration #${registration.id}`}
                    subtitle={registration.rider_name}
                    icon={UserRound}
                    status={registration.status}
                    backHref="/history/registrations"
                    backLabel="Back to registrations"
                    facts={[
                        { label: 'Fee', value: 'AED 100.00' },
                        {
                            label: 'Transaction ref',
                            value: registration.tran_ref,
                            mono: true,
                        },
                        { label: 'Submitted', value: registration.created_at },
                        {
                            label: 'Completed',
                            value: registration.completed_at,
                        },
                    ]}
                />

                {/* A failed registration means the payment was taken but the
                    federation refused the submission - say so plainly. */}
                {registration.status === 'failed' &&
                    registration.error_message && (
                        <Alert variant="destructive">
                            <AlertDescription>
                                <strong>
                                    The federation could not complete this
                                    registration.
                                </strong>
                                <span className="mt-1 block">
                                    {registration.error_message}
                                </span>
                                {registration.tran_ref && (
                                    <span className="mt-1 block">
                                        Quote reference{' '}
                                        <span className="font-mono">
                                            {registration.tran_ref}
                                        </span>{' '}
                                        when contacting support.
                                    </span>
                                )}
                            </AlertDescription>
                        </Alert>
                    )}

                <div className="grid gap-6 lg:grid-cols-2">
                    <DetailCard
                        title="Rider Details"
                        description="As submitted to the federation"
                        icon={UserRound}
                        delay={100}
                    >
                        <DetailRow label="Full name">
                            {registration.rider_name}
                        </DetailRow>
                        <DetailRow label="First name">
                            {registration.first_name}
                        </DetailRow>
                        <DetailRow label="Last name">
                            {registration.last_name}
                        </DetailRow>
                        <DetailRow label="Date of birth">
                            {registration.date_of_birth}
                        </DetailRow>
                        <DetailRow label="Nationality">
                            {registration.nationality}
                        </DetailRow>
                        <DetailRow label="Emirates ID" mono>
                            {registration.eid}
                        </DetailRow>
                        <DetailRow label="Passport number" mono>
                            {registration.passport_number}
                        </DetailRow>
                        <DetailRow label="Weight">
                            {registration.weight
                                ? `${registration.weight} kg`
                                : null}
                        </DetailRow>
                    </DetailCard>

                    <DetailCard title="Contact" icon={Mail} delay={150}>
                        <DetailRow label="Email">
                            {registration.email}
                        </DetailRow>
                        <DetailRow label="Mobile">
                            {registration.mobile}
                        </DetailRow>
                        <DetailRow label="Address">
                            {registration.address}
                        </DetailRow>
                        <DetailRow label="PO Box">
                            {registration.po_box}
                        </DetailRow>
                    </DetailCard>

                    <DetailCard
                        title="Registration"
                        icon={ShieldCheck}
                        delay={200}
                    >
                        <DetailRow label="Discipline">
                            {registration.discipline_id}
                        </DetailRow>
                        <DetailRow label="Category">
                            {registration.category_id}
                        </DetailRow>
                        <DetailRow label="Visa category">
                            {registration.visa_category}
                        </DetailRow>
                        <DetailRow label="Current season">
                            {registration.register_season ? 'Yes' : 'No'}
                        </DetailRow>
                        <DetailRow label="FEI registration">
                            {registration.register_fei ? 'Yes' : 'No'}
                        </DetailRow>
                    </DetailCard>

                    <DetailCard
                        title="Payment & Status"
                        icon={CreditCard}
                        delay={250}
                    >
                        <DetailRow label="Status">
                            {getStatusBadge(registration.status)}
                        </DetailRow>
                        <DetailRow label="Transaction reference" mono>
                            {registration.tran_ref}
                        </DetailRow>
                        <DetailRow label="Fee">AED 100.00</DetailRow>
                        <DetailRow label="Submitted">
                            {registration.created_at}
                        </DetailRow>
                        <DetailRow label="Completed">
                            {registration.completed_at}
                        </DetailRow>
                    </DetailCard>
                </div>
            </div>
        </>
    );
}
