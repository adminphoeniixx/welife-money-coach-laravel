<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    CreditCard,
    FileText,
    FolderGit2,
    LayoutGrid,
    Receipt,
    ScrollText,
    Settings2,
    ShieldAlert,
    ShieldCheck,
    Tags,
    Users,
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
