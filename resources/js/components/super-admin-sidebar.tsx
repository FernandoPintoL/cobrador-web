import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { Building2, CreditCard, Settings, BarChart3, Users, FileText } from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/super-admin/dashboard',
        icon: BarChart3,
    },
    {
        title: 'Empresas',
        href: '/super-admin/tenants',
        icon: Building2,
    },
    {
        title: 'Facturación',
        href: '/super-admin/subscriptions',
        icon: CreditCard,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Volver al sistema',
        href: '/dashboard',
        icon: Users,
    },
];

export function SuperAdminSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/super-admin/dashboard" prefetch>
                                <AppLogo />
                                <div className="flex flex-col gap-0.5 leading-none">
                                    <span className="font-semibold">Super Admin</span>
                                    <span className="text-xs text-muted-foreground">Panel de Control</span>
                                </div>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
