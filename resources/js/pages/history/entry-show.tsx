import { Head } from '@inertiajs/react';
import { CreditCard, Trophy } from 'lucide-react';
import DetailCard from '@/components/detail-card';
import DetailRow from '@/components/detail-row';
import RecordHero from '@/components/record-hero';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { getStatusBadge } from '@/lib/status-badge';

interface Entry {
    id: number;
    rider_id: string;
    horse_id: string;
    event_id: number;
    class_id: number;
    season_code: number | null;
    event_name: string | null;
    status: string;
    tran_ref: string | null;
    error_message: string | null;
    completed_at: string | null;
    created_at: string;
}

export default function EntryShow({ entry }: { entry: Entry }) {
    return (
        <>
            <Head title={`Competition Entry #${entry.id}`} />

            <div className="space-y-6 p-6">
                <RecordHero
                    title={`Competition Entry #${entry.id}`}
                    subtitle={entry.event_name}
                    icon={Trophy}
                    status={entry.status}
                    backHref="/history/entries"
                    backLabel="Back to entries"
                    facts={[
                        { label: 'Fee', value: 'AED 150.00' },
                        {
                            label: 'Transaction ref',
                            value: entry.tran_ref,
                            mono: true,
                        },
                        { label: 'Submitted', value: entry.created_at },
                        { label: 'Completed', value: entry.completed_at },
                    ]}
                />

                {entry.status === 'failed' && entry.error_message && (
                    <Alert variant="destructive">
                        <AlertDescription>
                            <strong>
                                The federation could not complete this entry.
                            </strong>
                            <span className="mt-1 block">
                                {entry.error_message}
                            </span>
                            {entry.tran_ref && (
                                <span className="mt-1 block">
                                    Quote reference{' '}
                                    <span className="font-mono">
                                        {entry.tran_ref}
                                    </span>{' '}
                                    when contacting support.
                                </span>
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-6 lg:grid-cols-2">
                    <DetailCard
                        title="Entry Details"
                        description="Written to ClassEntriesWeb on payment"
                        icon={Trophy}
                        delay={100}
                    >
                        <DetailRow label="Event">{entry.event_name}</DetailRow>
                        <DetailRow label="Event code" mono>
                            {entry.event_id}
                        </DetailRow>
                        <DetailRow label="Class" mono>
                            #{entry.class_id}
                        </DetailRow>
                        <DetailRow label="Season">
                            {entry.season_code}
                        </DetailRow>
                        <DetailRow label="Rider ID" mono>
                            {entry.rider_id}
                        </DetailRow>
                        <DetailRow label="Horse ID" mono>
                            {entry.horse_id}
                        </DetailRow>
                    </DetailCard>

                    <DetailCard
                        title="Payment & Status"
                        icon={CreditCard}
                        delay={150}
                    >
                        <DetailRow label="Status">
                            {getStatusBadge(entry.status)}
                        </DetailRow>
                        <DetailRow label="Transaction reference" mono>
                            {entry.tran_ref}
                        </DetailRow>
                        <DetailRow label="Fee">AED 150.00</DetailRow>
                        <DetailRow label="Submitted">
                            {entry.created_at}
                        </DetailRow>
                        <DetailRow label="Completed">
                            {entry.completed_at}
                        </DetailRow>
                    </DetailCard>
                </div>
            </div>
        </>
    );
}
