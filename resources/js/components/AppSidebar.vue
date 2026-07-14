<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    BarChart3,
    Bell,
    BookOpen,
    CalendarDays,
    CreditCard,
    FileText,
    Flag,
    FolderGit2,
    Landmark,
    LayoutGrid,
    Receipt,
    ScrollText,
    Search,
    Settings2,
    ShieldAlert,
    ShieldCheck,
    ShieldPlus,
    Tags,
    Target,
    Trophy,
    Users,
    Wallet,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
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

const page = usePage();

const isAdmin = computed(() => page.props.auth.user?.is_admin === true);

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Transactions',
        href: '/transactions',
        icon: ArrowLeftRight,
    },
    {
        title: 'Debts',
        href: '/debts',
        icon: Wallet,
    },
    {
        title: 'Net Worth',
        href: '/net-worth',
        icon: Landmark,
    },
    {
        title: 'Budgets & Goals',
        href: '/planning',
        icon: Target,
    },
    {
        title: 'Reminders',
        href: '/reminders',
        icon: Receipt,
    },
    {
        title: 'Family',
        href: '/family',
        icon: Users,
    },
    {
        title: 'Documents Vault',
        href: '/vault',
        icon: ShieldPlus,
    },
];

const insightNavItems: NavItem[] = [
    { title: 'Reports', href: '/reports', icon: BarChart3 },
    { title: 'Calendar', href: '/calendar', icon: CalendarDays },
    { title: 'Challenges', href: '/challenges', icon: Flag },
    { title: 'Achievements', href: '/achievements', icon: Trophy },
    { title: 'Notifications', href: '/notifications', icon: Bell },
    { title: 'Search', href: '/search', icon: Search },
];

const adminNavItems: NavItem[] = [
    {
        title: 'Overview',
        href: '/admin',
        icon: ShieldCheck,
    },
    {
        title: 'Users',
        href: '/admin/users',
        icon: Users,
    },
    {
        title: 'Categories',
        href: '/admin/categories',
        icon: Tags,
    },
    {
        title: 'Plans',
        href: '/admin/plans',
        icon: CreditCard,
    },
    {
        title: 'Subscriptions',
        href: '/admin/subscriptions',
        icon: Receipt,
    },
    {
        title: 'Content',
        href: '/admin/content',
        icon: FileText,
    },
    {
        title: 'Settings',
        href: '/admin/settings',
        icon: Settings2,
    },
    {
        title: 'Audit log',
        href: '/admin/audit',
        icon: ScrollText,
    },
    {
        title: 'Compliance',
        href: '/admin/compliance',
        icon: ShieldAlert,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
            <NavMain :items="insightNavItems" label="Insights" />
            <NavMain
                v-if="isAdmin"
                :items="adminNavItems"
                label="Administration"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
