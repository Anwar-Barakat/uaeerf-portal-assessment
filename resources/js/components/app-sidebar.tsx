import { Link } from '@inertiajs/react';
import { LayoutGrid, UserPlus, RefreshCw, Trophy, Home } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
        iconColor: 'bg-violet-500 text-white',
    },
];

const riderNavItems: NavItem[] = [
    {
        title: 'Rider Registrations',
        href: '/history/registrations',
        icon: UserPlus,
        iconColor: 'bg-emerald-500 text-white',
    },
    {
        title: 'Rider Renewals',
        href: '/history/renewals',
        icon: RefreshCw,
        iconColor: 'bg-blue-500 text-white',
    },
];

const competitionNavItems: NavItem[] = [
    {
        title: 'Competition Entries',
        href: '/history/entries',
        icon: Trophy,
        iconColor: 'bg-amber-500 text-white',
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} label="Platform" />
                <NavMain items={riderNavItems} label="Rider Services" />
                <NavMain items={competitionNavItems} label="Competitions" />
            </SidebarContent>

            <SidebarFooter>
                <SidebarMenu className="mt-auto gap-1">
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            asChild
                            tooltip={{ children: 'UAEERF Website' }}
                            className="group/nav h-9 gap-3 rounded-lg font-medium transition-colors group-data-[collapsible=icon]:size-9! group-data-[collapsible=icon]:p-1! hover:bg-primary/5 hover:text-foreground"
                        >
                            <Link href="/">
                                <span className="flex size-6 shrink-0 items-center justify-center rounded-md bg-teal-500 text-white shadow-sm transition-transform group-hover/nav:scale-105">
                                    <Home className="size-3.5" />
                                </span>
                                <span>UAEERF Website</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
