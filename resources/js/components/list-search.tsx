import { Loader2, Search, X } from 'lucide-react';
import { Input } from '@/components/ui/input';

interface ListSearchProps {
    value: string;
    onChange: (value: string) => void;
    placeholder: string;
    isSearching?: boolean;
}

/** Search box for the history listings: icon, spinner while fetching, clear. */
export default function ListSearch({
    value,
    onChange,
    placeholder,
    isSearching = false,
}: ListSearchProps) {
    return (
        <div className="relative w-full sm:w-72">
            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
                type="search"
                placeholder={placeholder}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="pr-9 pl-9 [&::-webkit-search-cancel-button]:hidden"
            />
            {isSearching ? (
                <Loader2 className="absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin text-muted-foreground" />
            ) : (
                value && (
                    <button
                        type="button"
                        onClick={() => onChange('')}
                        aria-label="Clear search"
                        className="absolute top-1/2 right-2.5 -translate-y-1/2 rounded-sm p-0.5 text-muted-foreground transition-colors hover:text-foreground"
                    >
                        <X className="size-4" />
                    </button>
                )
            )}
        </div>
    );
}
