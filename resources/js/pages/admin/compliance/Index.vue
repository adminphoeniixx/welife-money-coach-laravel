<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Check, Clock, Download, Trash2, X } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

interface DataReq {
    id: number;
    user: string | null;
    user_email: string;
    type: string;
    status: string;
    note: string | null;
    created_at: string;
    resolved_at: string | null;
}
interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
    from: number | null;
    to: number | null;
}

const props = defineProps<{
    requests: Paginated<DataReq>;
    filters: { status: string };
    stats: { pending: number; export: number; deletion: number };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'Compliance', href: '/admin/compliance' },
        ],
    },
});

const statusTabs = ['all', 'pending', 'completed', 'rejected'];

function setStatus(s: string) {
    router.get('/admin/compliance', { status: s }, { preserveState: true, replace: true, preserveScroll: true });
}
function complete(r: DataReq) {
    const msg = r.type === 'deletion'
        ? `Complete deletion for ${r.user_email}? This permanently removes the account.`
        : `Mark the export request for ${r.user_email} as completed?`;

    if (!window.confirm(msg)) {
return;
}

    router.patch(`/admin/compliance/${r.id}/complete`, {}, { preserveScroll: true });
}
function reject(r: DataReq) {
    if (!window.confirm(`Reject the ${r.type} request for ${r.user_email}?`)) {
return;
}

    router.patch(`/admin/compliance/${r.id}/reject`, {}, { preserveScroll: true });
}
function statusVariant(s: string) {
    if (s === 'completed') {
return 'default';
}

    if (s === 'rejected') {
return 'destructive';
}

    return 'secondary';
}
function formatDate(v: string | null) {
    return v ? new Date(v).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : '—';
}
</script>

<template>
    <Head title="Compliance" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            title="Compliance"
            description="Handle GDPR-style data export and account deletion requests."
        />

        <div class="grid gap-4 sm:grid-cols-3">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">Pending</CardTitle>
                    <Clock class="size-5 text-muted-foreground" />
                </CardHeader>
                <CardContent><div class="text-2xl font-semibold">{{ stats.pending }}</div></CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">Export requests</CardTitle>
                    <Download class="size-5 text-muted-foreground" />
                </CardHeader>
                <CardContent><div class="text-2xl font-semibold">{{ stats.export }}</div></CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">Deletion requests</CardTitle>
                    <Trash2 class="size-5 text-muted-foreground" />
                </CardHeader>
                <CardContent><div class="text-2xl font-semibold">{{ stats.deletion }}</div></CardContent>
            </Card>
        </div>

        <div class="flex flex-wrap gap-1">
            <Button
                v-for="s in statusTabs"
                :key="s"
                size="sm"
                :variant="filters.status === s ? 'default' : 'outline'"
                class="capitalize"
                @click="setStatus(s)"
            >
                {{ s }}
            </Button>
        </div>

        <Card>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">User</th>
                                <th class="px-4 py-3 font-medium">Type</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 font-medium">Requested</th>
                                <th class="px-4 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="r in requests.data" :key="r.id" class="hover:bg-muted/40">
                                <td class="px-4 py-3">
                                    <p class="font-medium">{{ r.user ?? '—' }}</p>
                                    <p class="text-xs text-muted-foreground">{{ r.user_email }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <Badge :variant="r.type === 'deletion' ? 'destructive' : 'outline'" class="capitalize">
                                        {{ r.type }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3">
                                    <Badge :variant="statusVariant(r.status)" class="capitalize">{{ r.status }}</Badge>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ formatDate(r.created_at) }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1">
                                        <template v-if="r.status === 'pending'">
                                            <Button variant="ghost" size="sm" title="Complete" @click="complete(r)">
                                                <Check class="size-4" />
                                            </Button>
                                            <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive" title="Reject" @click="reject(r)">
                                                <X class="size-4" />
                                            </Button>
                                        </template>
                                        <span v-else class="text-xs text-muted-foreground">Resolved {{ formatDate(r.resolved_at) }}</span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="requests.data.length === 0">
                                <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">
                                    No data requests.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
