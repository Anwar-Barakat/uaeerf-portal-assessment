import { router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { PaginatedData } from '@/types';

interface PaginationNavProps {
    /** Any paginator payload; only the meta fields are read. */
    page: Pick<
        PaginatedData<unknown>,
        'current_page' | 'last_page' | 'per_page' | 'total' | 'links'
    >;
    /** Rows rendered on this page, used for the "showing x–y" range. */
    shown: number;
    label: string;
}

export default function PaginationNav({
    page,
    shown,
    label,
}: PaginationNavProps) {
    if (page.last_page <= 1) {
        return null;
    }

    const from = (page.current_page - 1) * page.per_page + 1;
    const to = from + shown - 1;

    // Laravel's first and last links are the arrows; the rest are page numbers.
    const numbered = page.links.slice(1, -1);
    const previous = page.links[0];
    const next = page.links[page.links.length - 1];

    const visit = (url: string | null) => {
        if (url) {
            router.visit(url, { preserveScroll: true });
        }
    };

    return (
        <div className="flex flex-col gap-3 border-t px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-sm text-muted-foreground">
                Showing{' '}
                <span className="font-medium text-foreground tabular-nums">
                    {from}–{to}
                </span>{' '}
                of{' '}
                <span className="font-medium text-foreground tabular-nums">
                    {page.total}
                </span>{' '}
                {label}
            </p>
            <div className="flex items-center gap-1">
                <Button
                    variant="outline"
                    size="icon"
                    className="size-8"
                    aria-label="Previous page"
                    onClick={() => visit(previous.url)}
                    disabled={!previous.url}
                >
                    <ChevronLeft className="size-4" />
                </Button>
                {numbered.map((link, index) => (
                    <Button
                        key={`${link.label}-${index}`}
                        variant={link.active ? 'default' : 'ghost'}
                        size="sm"
                        className="size-8 p-0 tabular-nums"
                        onClick={() => visit(link.url)}
                        disabled={!link.url}
                    >
                        {link.label}
                    </Button>
                ))}
                <Button
                    variant="outline"
                    size="icon"
                    className="size-8"
                    aria-label="Next page"
                    onClick={() => visit(next.url)}
                    disabled={!next.url}
                >
                    <ChevronRight className="size-4" />
                </Button>
            </div>
        </div>
    );
}
