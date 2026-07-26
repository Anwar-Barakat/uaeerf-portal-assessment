import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

interface DetailCardProps {
    title: string;
    description?: string;
    icon: LucideIcon;
    /** Stagger for the entrance animation, in milliseconds. */
    delay?: number;
    /** DetailRow elements; they are wrapped in the definition list. */
    children: ReactNode;
}

/** One titled panel of label/value rows on a record detail page. */
export default function DetailCard({
    title,
    description,
    icon: Icon,
    delay = 0,
    children,
}: DetailCardProps) {
    return (
        <Card
            className="animate-fade-up"
            style={{ animationDelay: `${delay}ms` }}
        >
            <CardHeader className="flex flex-row items-start gap-3 space-y-0 border-b pb-4">
                <span className="flex size-9 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                    <Icon className="size-4.5" />
                </span>
                <div className="space-y-0.5">
                    <CardTitle className="text-base">{title}</CardTitle>
                    {description && (
                        <CardDescription>{description}</CardDescription>
                    )}
                </div>
            </CardHeader>
            <CardContent>
                <dl className="divide-y">{children}</dl>
            </CardContent>
        </Card>
    );
}
