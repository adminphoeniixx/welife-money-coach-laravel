<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Search, ShieldCheck, ShieldOff, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

interface AdminUser {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    suspended_at: string | null;
    email_verified_at: string | null;
    created_at: string;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
    from: number | null;
    to: number | null;
}

const props = defineProps<{
    users: Paginated<AdminUser>;
    filters: { search: string; filter: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'Users', href: '/admin/users' },
        ],
    },
});

const search = ref(props.filters.search);
const activeFilter = ref(props.filters.filter || 'all');

const tabs = [
    { key: 'all', label: 'All' },
    { key: 'admins', label: 'Admins' },
    { key: 'verified', label: 'Verified' },
    { key: 'unverified', label: 'Unverified' },
    { key: 'suspended', label: 'Suspended' },
];

let debounce: ReturnType<typeof setTimeout> | undefined;

function reload() {
    router.get(
        '/admin/users',
        { search: search.value, filter: activeFilter.value },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}

watch(search, () => {
    clearTimeout(debounce);
    debounce = setTimeout(reload, 350);
});

function setFilter(key: string) {
    activeFilter.value = key;
    reload();
}

function toggleAdmin(user: AdminUser) {
    router.patch(`/admin/users/${user.id}/admin`, {}, { preserveScroll: true });
}

function destroy(user: AdminUser) {
    if (
        !window.confirm(
            `Delete ${user.name}? This permanently removes the account.`,
        )
    ) {
        return;
    }

    router.delete(`/admin/users/${user.id}`, { preserveScroll: true });
}

function formatDate(value: string | null): string {
    return value
        ? new Date(value).toLocaleDateString(undefined, {
              year: 'numeric',
              month: 'short',
              day: 'numeric',
          })
        : '—';
}
</script>

<template>
    <Head title="Users" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            title="Users"
            description="Search, review and manage every account on the platform."
        />

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="relative w-full sm:max-w-xs">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    placeholder="Search by name or email"
                    class="pl-9"
                />
            </div>
            <div class="flex flex-wrap gap-1">
                <Button
                    v-for="tab in tabs"
                    :key="tab.key"
                    :variant="activeFilter === tab.key ? 'default' : 'outline'"
                    size="sm"
                    @click="setFilter(tab.key)"
                >
                    {{ tab.label }}
                </Button>
            </div>
        </div>

        <Card>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">Name</th>
                                <th class="px-4 py-3 font-medium">Email</th>
                                <th class="px-4 py-3 font-medium">Role</th>
                                <th class="px-4 py-3 font-medium">Verified</th>
                                <th class="px-4 py-3 font-medium">Joined</th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="user in users.data"
                                :key="user.id"
                                class="hover:bg-muted/40"
                            >
                                <td class="px-4 py-3 font-medium">
                                    <Link
                                        :href="`/admin/users/${user.id}`"
                                        class="flex items-center gap-2 hover:underline"
                                    >
                                        {{ user.name }}
                                        <Badge
                                            v-if="user.suspended_at"
                                            variant="destructive"
                                            >Suspended</Badge
                                        >
                                    </Link>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ user.email }}
                                </td>
                                <td class="px-4 py-3">
                                    <Badge
                                        :variant="
                                            user.is_admin
                                                ? 'default'
                                                : 'secondary'
                                        "
                                    >
                                        {{ user.is_admin ? 'Admin' : 'User' }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3">
                                    <Badge
                                        :variant="
                                            user.email_verified_at
                                                ? 'outline'
                                                : 'destructive'
                                        "
                                    >
                                        {{
                                            user.email_verified_at
                                                ? 'Verified'
                                                : 'Pending'
                                        }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ formatDate(user.created_at) }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            :title="
                                                user.is_admin
                                                    ? 'Revoke admin'
                                                    : 'Make admin'
                                            "
                                            @click="toggleAdmin(user)"
                                        >
                                            <ShieldOff
                                                v-if="user.is_admin"
                                                class="size-4"
                                            />
                                            <ShieldCheck
                                                v-else
                                                class="size-4"
                                            />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="text-destructive hover:text-destructive"
                                            title="Delete user"
                                            @click="destroy(user)"
                                        >
                                            <Trash2 class="size-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="users.data.length === 0">
                                <td
                                    colspan="6"
                                    class="px-4 py-10 text-center text-muted-foreground"
                                >
                                    No users match your filters.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <div
            v-if="users.links.length > 3"
            class="flex flex-wrap items-center justify-between gap-3"
        >
            <p class="text-sm text-muted-foreground">
                Showing {{ users.from ?? 0 }}–{{ users.to ?? 0 }} of
                {{ users.total }}
            </p>
            <div class="flex flex-wrap gap-1">
                <template v-for="(link, i) in users.links" :key="i">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        preserve-scroll
                        preserve-state
                        class="rounded-md border px-3 py-1.5 text-sm"
                        :class="
                            link.active
                                ? 'bg-primary text-primary-foreground'
                                : 'hover:bg-muted'
                        "
                        v-html="link.label"
                    />
                    <span
                        v-else
                        class="rounded-md border px-3 py-1.5 text-sm text-muted-foreground opacity-50"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </div>
</template>
