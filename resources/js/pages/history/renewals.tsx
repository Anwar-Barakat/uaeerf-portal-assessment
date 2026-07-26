import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Plus, RefreshCw } from 'lucide-react';
import EmptyState from '@/components/empty-state';
import ListPageHeader from '@/components/list-page-header';
import ListSearch from '@/components/list-search';
import PaginationNav from '@/components/pagination-nav';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useDebouncedSearch } from '@/hooks/use-debounced-search';
import { getStatusBadge } from '@/lib/status-badge';
import type { PaginatedData, Renewal } from '@/types';

interface RenewalsProps {
    renewals: PaginatedData<Renewal>;
    filters: { search: string | null };
}

export default function Renewals({ renewals, filters }: RenewalsProps) {
    const { search, setSearch, isSearching } = useDebouncedSearch(
        '/history/renewals',
        filters.search,
    );

    return (
        <>
            <Head title="My Renewals" />

            <div className="space-y-6 p-6">
                <ListPageHeader
                    title="My Renewals"
                    description="View your complete rider renewal history"
                    icon={RefreshCw}
                    backHref="/dashboard"
                    meta={
                        <Badge variant="secondary" className="tabular-nums">
                            {renewals.total} total
                        </Badge>
                    }
                    action={
                        <Button asChild>
                            <Link href="/rider/renewal">
                                <Plus className="size-4" />
                                Create New
                            </Link>
                        </Button>
                    }
                />

                <Card
                    className="animate-fade-up gap-0 overflow-hidden py-0"
                    style={{ animationDelay: '100ms' }}
                >
                    <CardHeader className="flex flex-col gap-3 border-b bg-muted/20 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="space-y-0.5">
                            <p className="font-semibold">All Renewals</p>
                            <p className="text-sm text-muted-foreground">
                                {search
                                    ? `${renewals.total} matching "${search}"`
                                    : `${renewals.total} renewal${renewals.total === 1 ? '' : 's'} on record`}
                            </p>
                        </div>
                        <ListSearch
                            value={search}
                            onChange={setSearch}
                            isSearching={isSearching}
                            placeholder="Search by rider or transaction..."
                        />
                    </CardHeader>
                    <CardContent className="p-0">
                        {renewals.data.length === 0 ? (
                            <EmptyState
                                icon={RefreshCw}
                                title={
                                    search
                                        ? 'No matching renewals'
                                        : 'No renewals yet'
                                }
                                description={
                                    search
                                        ? 'No renewal matches that search. Try a different rider id or transaction reference.'
                                        : 'Renew your rider registration for the new season.'
                                }
                                action={
                                    !search && (
                                        <Button asChild>
                                            <Link href="/rider/renewal">
                                                <Plus className="size-4" />
                                                Renew Registration
                                            </Link>
                                        </Button>
                                    )
                                }
                            />
                        ) : (
                            <>
                                <Table>
                                    <TableHeader className="bg-muted/40">
                                        <TableRow className="hover:bg-transparent">
                                            <TableHead className="pl-6">
                                                ID
                                            </TableHead>
                                            <TableHead>Rider ID</TableHead>
                                            <TableHead>Season ID</TableHead>
                                            <TableHead>Renewed</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>
                                                Transaction Ref
                                            </TableHead>
                                            <TableHead className="pr-6 text-right">
                                                <span className="sr-only">
                                                    Actions
                                                </span>
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {renewals.data.map((renewal) => (
                                            <TableRow
                                                key={renewal.id}
                                                className="group"
                                            >
                                                <TableCell className="pl-6 font-mono text-xs text-muted-foreground">
                                                    #{renewal.id}
                                                </TableCell>
                                                <TableCell className="font-mono font-medium">
                                                    {renewal.rider_id}
                                                </TableCell>
                                                <TableCell className="tabular-nums">
                                                    {renewal.season_id}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {renewal.created_at}
                                                </TableCell>
                                                <TableCell>
                                                    {getStatusBadge(
                                                        renewal.status,
                                                    )}
                                                </TableCell>
                                                <TableCell className="font-mono text-xs text-muted-foreground">
                                                    {renewal.tran_ref || '—'}
                                                </TableCell>
                                                <TableCell className="pr-6 text-right">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        asChild
                                                        className="opacity-70 transition-opacity group-hover:opacity-100"
                                                    >
                                                        <Link
                                                            href={`/history/renewals/${renewal.id}`}
                                                        >
                                                            Show
                                                            <ArrowRight className="size-4 transition-transform group-hover:translate-x-0.5" />
                                                        </Link>
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>

                                <PaginationNav
                                    page={renewals}
                                    shown={renewals.data.length}
                                    label="renewals"
                                />
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
