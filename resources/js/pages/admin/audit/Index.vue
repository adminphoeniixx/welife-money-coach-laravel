<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

interface LogRow {
    id: number;
    actor: string;
    action: string;
    description: string | null;
    subject: string | null;
    ip_address: string | null;
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
    logs: Paginated<LogRow>;
    filters: { search: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'Audit log', href: '/admin/audit' },
        ],
    },
});

const search = ref(props.filters.search);
let debounce: ReturnType<typeof setTimeout> | undefined;
watch(search, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get('/admin/audit', { search: search.value }, { preserveState: true, replace: true, preserveScroll: true });
    }, 350);
});

// Turn "admin.users.toggle-suspend" into "Users · toggle suspend".
function pretty(action: string): string {
    return action
        .replace(/^admin\./, '')
        .replace(/\./g, ' · ')
        .replace(/-/g, ' ');
}
function formatDate(v: string): string {
    return new Date(v).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head title="Audit log" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            title="Audit log"
            description="Every state-changing admin action, with who did it and when."
        />

        <div class="relative w-full sm:max-w-xs">
            <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input v-model="search" placeholder="Search action, admin or path" class="pl-9" />
        </div>

        <Card>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">When</th>
                                <th class="px-4 py-3 font-medium">Admin</th>
                                <th class="px-4 py-3 font-medium">Action</th>
                                <th class="px-4 py-3 font-medium">Subject</th>
                                <th class="px-4 py-3 font-medium">IP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="log in logs.data" :key="log.id" class="hover:bg-muted/40">
                                <td class="px-4 py-3 whitespace-nowrap text-muted-foreground">{{ formatDate(log.created_at) }}</td>
                                <td class="px-4 py-3 font-medium">{{ log.actor }}</td>
                                <td class="px-4 py-3">
                                    <Badge variant="secondary" class="font-normal capitalize">{{ pretty(log.action) }}</Badge>
                                    <span class="ml-2 text-xs text-muted-foreground">{{ log.description }}</span>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ log.subject ?? '—' }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ log.ip_address ?? '—' }}</td>
                            </tr>
                            <tr v-if="logs.data.length === 0">
                                <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">
                                    No admin activity recorded yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <div v-if="logs.links.length > 3" class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-muted-foreground">Showing {{ logs.from ?? 0 }}–{{ logs.to ?? 0 }} of {{ logs.total }}</p>
            <div class="flex flex-wrap gap-1">
                <template v-for="(link, i) in logs.links" :key="i">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        preserve-scroll
                        preserve-state
                        class="rounded-md border px-3 py-1.5 text-sm"
                        :class="link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
                        v-html="link.label"
                    />
                    <span v-else class="rounded-md border px-3 py-1.5 text-sm text-muted-foreground opacity-50" v-html="link.label" />
                </template>
            </div>
        </div>
    </div>
</template>
