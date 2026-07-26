import { Head } from '@inertiajs/react';
import { CreditCard, RefreshCw } from 'lucide-react';
import DetailCard from '@/components/detail-card';
import DetailRow from '@/components/detail-row';
import RecordHero from '@/components/record-hero';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { getStatusBadge } from '@/lib/status-badge';

interface Renewal {
    id: number;
    rider_id: string;
    season_id: number;
    discipline_id: number | null;
    weight: string | null;
    jumping_division_level: number | null;
    competition_level: string | null;
    status: string;
    tran_ref: string | null;
    error_message: string | null;
    completed_at: string | null;
    created_at: string;
}

export default function RenewalShow({ renewal }: { renewal: Renewal }) {
    return (
        <>
            <Head title={`Renewal #${renewal.id}`} />

            <div className="space-y-6 p-6">
                <RecordHero
                    title={`Renewal #${renewal.id}`}
                    subtitle={`Rider ${renewal.rider_id}`}
                    icon={RefreshCw}
                    status={renewal.status}
                    backHref="/history/renewals"
                    backLabel="Back to renewals"
                    facts={[
                        { label: 'Fee', value: 'AED 50.00' },
                        {
                            label: 'Transaction ref',
                            value: renewal.tran_ref,
                            mono: true,
                        },
                        { label: 'Submitted', value: renewal.created_at },
                        { label: 'Completed', value: renewal.completed_at },
                    ]}
                />

                {renewal.status === 'failed' && renewal.error_message && (
                    <Alert variant="destructive">
                        <AlertDescription>
                            <strong>
                                The federation could not complete this renewal.
                            </strong>
                            <span className="mt-1 block">
                                {renewal.error_message}
                            </span>
                            {renewal.tran_ref && (
                                <span className="mt-1 block">
                                    Quote reference{' '}
                                    <span className="font-mono">
                                        {renewal.tran_ref}
                                    </span>{' '}
                                    when contacting support.
                                </span>
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-6 lg:grid-cols-2">
                    <DetailCard
                        title="Renewal Details"
                        description="As submitted to the federation"
                        icon={RefreshCw}
                        delay={100}
                    >
                        <DetailRow label="Rider ID" mono>
                            {renewal.rider_id}
                        </DetailRow>
                        <DetailRow label="Season">
                            {renewal.season_id}
                        </DetailRow>
                        <DetailRow label="Discipline">
                            {renewal.discipline_id}
                        </DetailRow>
                        <DetailRow label="Weight">
                            {renewal.weight ? `${renewal.weight} kg` : null}
                        </DetailRow>
                        {/* Show jumping only. */}
                        <DetailRow label="Division level">
                            {renewal.jumping_division_level}
                        </DetailRow>
                        <DetailRow label="Competition category">
                            {renewal.competition_level}
                        </DetailRow>
                    </DetailCard>

                    <DetailCard
                        title="Payment & Status"
                        icon={CreditCard}
                        delay={150}
                    >
                        <DetailRow label="Status">
                            {getStatusBadge(renewal.status)}
                        </DetailRow>
                        <DetailRow label="Transaction reference" mono>
                            {renewal.tran_ref}
                        </DetailRow>
                        <DetailRow label="Fee">AED 50.00</DetailRow>
                        <DetailRow label="Submitted">
                            {renewal.created_at}
                        </DetailRow>
                        <DetailRow label="Completed">
                            {renewal.completed_at}
                        </DetailRow>
                    </DetailCard>
                </div>
            </div>
        </>
    );
}
