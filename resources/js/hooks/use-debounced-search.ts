import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

/**
 * Keeps a search box in sync with a paginated Inertia listing: typing updates
 * local state immediately and re-requests the page once typing pauses, without
 * firing on the initial render.
 */
export function useDebouncedSearch(
    url: string,
    initial: string | null,
    delay = 300,
) {
    const [search, setSearch] = useState(initial ?? '');
    const [isSearching, setIsSearching] = useState(false);
    const isFirstRender = useRef(true);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        const timer = setTimeout(() => {
            router.get(
                url,
                { search: search || undefined },
                {
                    preserveState: true,
                    preserveScroll: true,
                    onStart: () => setIsSearching(true),
                    onFinish: () => setIsSearching(false),
                },
            );
        }, delay);

        return () => clearTimeout(timer);
    }, [search, url, delay]);

    return { search, setSearch, isSearching };
}

export default useDebouncedSearch;
