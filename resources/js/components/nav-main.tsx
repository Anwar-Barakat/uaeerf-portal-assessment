import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

export function NavMain({
    items = [],
    label,
}: {
    items: NavItem[];
    label?: string;
}) {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <SidebarGroup className="px-2 py-0">
            {label && (
                <SidebarGroupLabel className="text-[0.7rem] font-semibold tracking-wider text-muted-foreground/70 uppercase">
                    {label}
                </SidebarGroupLabel>
            )}
            <SidebarMenu className="gap-1">
                {items.map((item) => {
                    const active = isCurrentUrl(item.href);

                    return (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={active}
                                tooltip={{ children: item.title }}
                                className={
                                    'group/nav h-9 gap-3 rounded-lg font-medium transition-colors ' +
                                    'hover:bg-primary/5 hover:text-foreground' +
                                    'group-data-[collapsible=icon]:size-9! group-data-[collapsible=icon]:p-1!' +
                                    'data-[active=true]:bg-primary/10 data-[active=true]:font-semibold data-[active=true]:text-primary'
                                }
                            >
                                <Link href={item.href} prefetch>
                                    {item.icon && (
                                        <span
                                            className={
                                                'flex size-6 shrink-0 items-center justify-center rounded-md shadow-sm transition-transform group-hover/nav:scale-105 ' +
                                                (item.iconColor ??
                                                    'bg-primary text-primary-foreground')
                                            }
                                        >
                                            <item.icon className="size-3.5" />
                                        </span>
                                    )}
                                    <span>{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}
