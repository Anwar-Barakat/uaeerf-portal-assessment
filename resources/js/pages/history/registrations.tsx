import { Head, Link } from '@inertiajs/react';
import { ArrowRight, FileText, Plus } from 'lucide-react';
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
import type { PaginatedData, Registration } from '@/types';

interface RegistrationsProps {
    registrations: PaginatedData<Registration>;
    filters: { search: string | null };
}

export default function Registrations({
    registrations,
    filters,
}: RegistrationsProps) {
    const { search, setSearch, isSearching } = useDebouncedSearch(
        '/history/registrations',
        filters.search,
    );

    return (
        <>
            <Head title="My Registrations" />

            <div className="space-y-6 p-6">
                <ListPageHeader
                    title="My Registrations"
                    description="View your complete rider registration history"
                    icon={FileText}
                    backHref="/dashboard"
                    meta={
                        <Badge variant="secondary" className="tabular-nums">
                            {registrations.total} total
                        </Badge>
                    }
                    action={
                        <Button asChild>
                            <Link href="/rider/registration">
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
                            <p className="font-semibold">All Registrations</p>
                            <p className="text-sm text-muted-foreground">
                                {search
                                    ? `${registrations.total} matching "${search}"`
                                    : `${registrations.total} registration${registrations.total === 1 ? '' : 's'} on record`}
                            </p>
                        </div>
                        <ListSearch
                            value={search}
                            onChange={setSearch}
                            isSearching={isSearching}
                            placeholder="Search by name or transaction..."
                        />
                    </CardHeader>
                    <CardContent className="p-0">
                        {registrations.data.length === 0 ? (
                            <EmptyState
                                icon={FileText}
                                title={
                                    search
                                        ? 'No matching registrations'
                                        : 'No registrations yet'
                                }
                                description={
                                    search
                                        ? 'No registration matches that search. Try a different name or transaction reference.'
                                        : 'Start by registering as a rider to access competitions and events.'
                                }
                                action={
                                    !search && (
                                        <Button asChild>
                                            <Link href="/rider/registration">
                                                <Plus className="size-4" />
                                                Register as Rider
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
                                            <TableHead>Rider Name</TableHead>
                                            <TableHead>Date of Birth</TableHead>
                                            <TableHead>Registered</TableHead>
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
                                        {registrations.data.map((reg) => (
                                            <TableRow
                                                key={reg.id}
                                                className="group"
                                            >
                                                <TableCell className="pl-6 font-mono text-xs text-muted-foreground">
                                                    #{reg.id}
                                                </TableCell>
                                                <TableCell className="font-medium">
                                                    {reg.rider_name}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {reg.date_of_birth}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {reg.created_at}
                                                </TableCell>
                                                <TableCell>
                                                    {getStatusBadge(reg.status)}
                                                </TableCell>
                                                <TableCell className="font-mono text-xs text-muted-foreground">
                                                    {reg.tran_ref || '—'}
                                                </TableCell>
                                                <TableCell className="pr-6 text-right">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        asChild
                                                        className="opacity-70 transition-opacity group-hover:opacity-100"
                                                    >
                                                        <Link
                                                            href={`/history/registrations/${reg.id}`}
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
                                    page={registrations}
                                    shown={registrations.data.length}
                                    label="registrations"
                                />
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
