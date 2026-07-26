import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Plus, Trophy } from 'lucide-react';
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
import type { Entry, PaginatedData } from '@/types';

interface EntriesProps {
    entries: PaginatedData<Entry>;
    filters: { search: string | null };
}

export default function Entries({ entries, filters }: EntriesProps) {
    const { search, setSearch, isSearching } = useDebouncedSearch(
        '/history/entries',
        filters.search,
    );

    return (
        <>
            <Head title="My Competition Entries" />

            <div className="space-y-6 p-6">
                <ListPageHeader
                    title="My Competition Entries"
                    description="View your complete competition entry history"
                    icon={Trophy}
                    backHref="/dashboard"
                    meta={
                        <Badge variant="secondary" className="tabular-nums">
                            {entries.total} total
                        </Badge>
                    }
                    action={
                        <Button asChild>
                            <Link href="/jumping/entry">
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
                            <p className="font-semibold">All Entries</p>
                            <p className="text-sm text-muted-foreground">
                                {search
                                    ? `${entries.total} matching "${search}"`
                                    : `${entries.total} entr${entries.total === 1 ? 'y' : 'ies'} on record`}
                            </p>
                        </div>
                        <ListSearch
                            value={search}
                            onChange={setSearch}
                            isSearching={isSearching}
                            placeholder="Search by rider, horse, event..."
                        />
                    </CardHeader>
                    <CardContent className="p-0">
                        {entries.data.length === 0 ? (
                            <EmptyState
                                icon={Trophy}
                                title={
                                    search
                                        ? 'No matching entries'
                                        : 'No entries yet'
                                }
                                description={
                                    search
                                        ? 'No entry matches that search. Try a different rider, horse, or event.'
                                        : 'Register for show jumping competitions to see your entries here.'
                                }
                                action={
                                    !search && (
                                        <Button asChild>
                                            <Link href="/jumping/entry">
                                                <Plus className="size-4" />
                                                Enter Competition
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
                                            <TableHead>Horse ID</TableHead>
                                            <TableHead>Event ID</TableHead>
                                            <TableHead>Class ID</TableHead>
                                            <TableHead>Entered</TableHead>
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
                                        {entries.data.map((entry) => (
                                            <TableRow
                                                key={entry.id}
                                                className="group"
                                            >
                                                <TableCell className="pl-6 font-mono text-xs text-muted-foreground">
                                                    #{entry.id}
                                                </TableCell>
                                                <TableCell className="font-mono font-medium">
                                                    {entry.rider_id}
                                                </TableCell>
                                                <TableCell className="font-mono">
                                                    {entry.horse_id}
                                                </TableCell>
                                                <TableCell className="tabular-nums">
                                                    {entry.event_id}
                                                </TableCell>
                                                <TableCell className="tabular-nums">
                                                    {entry.class_id}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {entry.created_at}
                                                </TableCell>
                                                <TableCell>
                                                    {getStatusBadge(
                                                        entry.status,
                                                    )}
                                                </TableCell>
                                                <TableCell className="font-mono text-xs text-muted-foreground">
                                                    {entry.tran_ref || '—'}
                                                </TableCell>
                                                <TableCell className="pr-6 text-right">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        asChild
                                                        className="opacity-70 transition-opacity group-hover:opacity-100"
                                                    >
                                                        <Link
                                                            href={`/history/entries/${entry.id}`}
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
                                    page={entries}
                                    shown={entries.data.length}
                                    label="entries"
                                />
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
