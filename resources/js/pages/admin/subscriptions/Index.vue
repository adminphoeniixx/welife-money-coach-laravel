<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Ban,
    CircleDollarSign,
    Plus,
    RefreshCw,
    Repeat,
    Users as UsersIcon,
} from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

interface Sub {
    id: number;
    user: { id: number; name: string; email: string } | null;
    plan: string | null;
    status: string;
    price_cents: number;
    currency: string;
    interval: string;
    started_at: string | null;
    ends_at: string | null;
}
interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
    from: number | null;
    to: number | null;
}

const props = defineProps<{
    stats: { active: number; cancelled: number; mrrCents: number; revenueCents: number };
    filters: { status: string };
    subscriptions: Paginated<Sub>;
    recentTransactions: {
        id: number;
        user: string | null;
        amount_cents: number;
        currency: string;
        status: string;
        reference: string | null;
        paid_at: string | null;
    }[];
    plans: { id: number; name: string; price_cents: number; currency: string; interval: string }[];
    users: { id: number; name: string; email: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'Subscriptions', href: '/admin/subscriptions' },
        ],
    },
});

const statusTabs = ['all', 'active', 'cancelled', 'expired'];
const assignOpen = ref(false);
const form = useForm({ user_id: '', plan_id: '' });

function money(cents: number, currency = 'USD') {
    return `${currency} ${(cents / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}
function setStatus(s: string) {
    router.get('/admin/subscriptions', { status: s }, { preserveState: true, replace: true, preserveScroll: true });
}
function submitAssign() {
    form.post('/admin/subscriptions', {
        preserveScroll: true,
        onSuccess: () => {
            assignOpen.value = false;
            form.reset();
        },
    });
}
function cancel(sub: Sub) {
    if (!window.confirm(`Cancel ${sub.user?.name}'s ${sub.plan} subscription?`)) {
return;
}

    router.patch(`/admin/subscriptions/${sub.id}/cancel`, {}, { preserveScroll: true });
}
function reactivate(sub: Sub) {
    router.patch(`/admin/subscriptions/${sub.id}/reactivate`, {}, { preserveScroll: true });
}
function statusVariant(s: string) {
    if (s === 'active' || s === 'trialing') {
return 'default';
}

    if (s === 'cancelled') {
return 'destructive';
}

    return 'secondary';
}
function formatDate(v: string | null) {
    return v ? new Date(v).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : '—';
}

const cards = [
    { label: 'Active subscribers', key: 'active', icon: UsersIcon, money: false },
    { label: 'MRR', key: 'mrrCents', icon: Repeat, money: true },
    { label: 'Total revenue', key: 'revenueCents', icon: CircleDollarSign, money: true },
    { label: 'Cancelled', key: 'cancelled', icon: Ban, money: false },
] as const;
</script>

<template>
    <Head title="Subscriptions" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                title="Subscriptions"
                description="Revenue, active subscribers and every user's plan."
            />
            <Button @click="assignOpen = true">
                <Plus class="size-4" /> Assign plan
            </Button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card v-for="c in cards" :key="c.label">
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">{{ c.label }}</CardTitle>
                    <component :is="c.icon" class="size-5 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-semibold">
                        {{ c.money ? money(stats[c.key]) : stats[c.key] }}
                    </div>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <!-- Subscriptions table -->
            <Card class="lg:col-span-2">
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Subscriptions</CardTitle>
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
                </CardHeader>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-b text-left text-muted-foreground">
                                <tr>
                                    <th class="px-4 py-3 font-medium">User</th>
                                    <th class="px-4 py-3 font-medium">Plan</th>
                                    <th class="px-4 py-3 font-medium">Price</th>
                                    <th class="px-4 py-3 font-medium">Status</th>
                                    <th class="px-4 py-3 font-medium">Renews</th>
                                    <th class="px-4 py-3 text-right font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="sub in subscriptions.data" :key="sub.id" class="hover:bg-muted/40">
                                    <td class="px-4 py-3">
                                        <Link v-if="sub.user" :href="`/admin/users/${sub.user.id}`" class="font-medium hover:underline">
                                            {{ sub.user.name }}
                                        </Link>
                                        <span v-else class="text-muted-foreground">—</span>
                                    </td>
                                    <td class="px-4 py-3">{{ sub.plan ?? '—' }}</td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ money(sub.price_cents, sub.currency) }}<span class="text-xs">/{{ sub.interval }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <Badge :variant="statusVariant(sub.status)" class="capitalize">{{ sub.status }}</Badge>
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">{{ formatDate(sub.ends_at) }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-1">
                                            <Button
                                                v-if="['active', 'trialing'].includes(sub.status)"
                                                variant="ghost"
                                                size="sm"
                                                class="text-destructive hover:text-destructive"
                                                title="Cancel"
                                                @click="cancel(sub)"
                                            >
                                                <Ban class="size-4" />
                                            </Button>
                                            <Button
                                                v-else
                                                variant="ghost"
                                                size="sm"
                                                title="Reactivate"
                                                @click="reactivate(sub)"
                                            >
                                                <RefreshCw class="size-4" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="subscriptions.data.length === 0">
                                    <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">
                                        No subscriptions yet. Use “Assign plan” to create one.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- Recent transactions -->
            <Card>
                <CardHeader><CardTitle>Recent transactions</CardTitle></CardHeader>
                <CardContent class="p-0">
                    <ul class="divide-y">
                        <li v-for="t in recentTransactions" :key="t.id" class="flex items-center justify-between gap-2 px-6 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">{{ t.user ?? '—' }}</p>
                                <p class="truncate text-xs text-muted-foreground">{{ t.reference }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-medium">{{ money(t.amount_cents, t.currency) }}</p>
                                <Badge :variant="t.status === 'paid' ? 'outline' : 'secondary'" class="capitalize">{{ t.status }}</Badge>
                            </div>
                        </li>
                        <li v-if="recentTransactions.length === 0" class="px-6 py-6 text-center text-sm text-muted-foreground">
                            No transactions yet.
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>

        <Dialog v-model:open="assignOpen">
            <DialogContent>
                <DialogHeader><DialogTitle>Assign a plan to a user</DialogTitle></DialogHeader>
                <form class="flex flex-col gap-4" @submit.prevent="submitAssign">
                    <div class="grid gap-2">
                        <Label for="assign-user">User</Label>
                        <select
                            id="assign-user"
                            v-model="form.user_id"
                            required
                            class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        >
                            <option value="" disabled>Select a user…</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} ({{ u.email }})</option>
                        </select>
                        <InputError :message="form.errors.user_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="assign-plan">Plan</Label>
                        <select
                            id="assign-plan"
                            v-model="form.plan_id"
                            required
                            class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        >
                            <option value="" disabled>Select a plan…</option>
                            <option v-for="p in plans" :key="p.id" :value="p.id">
                                {{ p.name }} — {{ money(p.price_cents, p.currency) }}/{{ p.interval }}
                            </option>
                        </select>
                        <InputError :message="form.errors.plan_id" />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="assignOpen = false">Cancel</Button>
                        <Button type="submit" :disabled="form.processing">Create subscription</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
