<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2, Wallet } from '@lucide/vue';
import { ref } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import InputError from '@/components/InputError.vue';
import { useCurrency } from '@/composables/useCurrency';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Net Worth', href: '/net-worth' }] },
});

interface Account { id: number; name: string; type: string; type_label: string; balance: number; note: string | null }

defineProps<{
    types: { key: string; label: string }[];
    summary: { assets: number; liabilities: number; net_worth: number };
    breakdown: { type: string; label: string; total: number; percent: number }[];
    accounts: Account[];
}>();

const { fmt } = useCurrency();
const PALETTE = ['#CC1D79', '#7B2FF7', '#06B7AD', '#F5A524', '#3B82F6', '#10B981', '#EC4899', '#94A3B8'];

const open = ref(false);
const editingId = ref<number | null>(null);
const form = useForm({ name: '', type: 'bank', balance: '', note: '' });

const openAdd = () => {
    editingId.value = null;
    form.clearErrors();
    form.defaults({ name: '', type: 'bank', balance: '', note: '' });
    form.reset();
    open.value = true;
};
const openEdit = (a: Account) => {
    editingId.value = a.id;
    form.clearErrors();
    form.defaults({ name: a.name, type: a.type, balance: String(a.balance), note: a.note ?? '' });
    form.reset();
    open.value = true;
};
const submit = () => {
    const opts = { preserveScroll: true, onSuccess: () => (open.value = false) };
    if (editingId.value) form.put(`/assets/${editingId.value}`, opts);
    else form.post('/assets', opts);
};

const deleteTarget = ref<Account | null>(null);
const del = useForm({});
const confirmDelete = () => {
    if (!deleteTarget.value) return;
    del.delete(`/assets/${deleteTarget.value.id}`, { preserveScroll: true, onSuccess: () => (deleteTarget.value = null) });
};
</script>

<template>
    <Head title="Net Worth" />

    <div class="flex flex-1 flex-col gap-5 p-4 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-lg font-extrabold">Assets &amp; Net Worth</h1>
            <button class="flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-sm font-semibold text-white" style="background: linear-gradient(135deg, #CC1D79 0%, #06B7AD 100%)" @click="openAdd">
                <Plus :size="16" /> Add asset
            </button>
        </div>

        <!-- Net worth hero -->
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl p-6 text-white sm:col-span-1" style="background: linear-gradient(135deg, #CC1D79 0%, #06B7AD 100%)">
                <div class="text-xs font-medium text-white/80">Net worth</div>
                <div class="mt-1 text-3xl font-extrabold">{{ fmt(summary.net_worth) }}</div>
                <div class="mt-2 text-xs text-white/85">Assets − Liabilities</div>
            </div>
            <div class="rounded-2xl border border-border bg-card p-6">
                <div class="text-xs font-semibold text-muted-foreground">Total assets</div>
                <div class="mt-1 text-2xl font-extrabold text-[#06B7AD]">{{ fmt(summary.assets) }}</div>
            </div>
            <div class="rounded-2xl border border-border bg-card p-6">
                <div class="text-xs font-semibold text-muted-foreground">Total liabilities</div>
                <div class="mt-1 text-2xl font-extrabold text-[#CC1D79]">{{ fmt(summary.liabilities) }}</div>
            </div>
        </div>

        <!-- Breakdown -->
        <div v-if="breakdown.length" class="rounded-2xl border border-border bg-card p-5">
            <h2 class="font-bold">Asset allocation</h2>
            <div class="mt-4 space-y-3">
                <div v-for="(b, i) in breakdown" :key="b.type">
                    <div class="mb-1 flex justify-between text-sm">
                        <span class="font-semibold">{{ b.label }}</span>
                        <span class="text-muted-foreground">{{ fmt(b.total) }} · {{ b.percent }}%</span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-muted">
                        <div class="h-full rounded-full" :style="{ width: b.percent + '%', background: PALETTE[i % PALETTE.length] }" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Accounts -->
        <div v-if="accounts.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="(a, i) in accounts" :key="a.id" class="group rounded-2xl border border-border bg-card p-5">
                <div class="flex items-start justify-between">
                    <span class="grid h-10 w-10 place-items-center rounded-xl text-white" :style="{ background: PALETTE[i % PALETTE.length] }"><Wallet :size="18" /></span>
                    <div class="flex gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                        <button class="grid h-7 w-7 place-items-center rounded-lg text-muted-foreground hover:bg-muted" @click="openEdit(a)"><Pencil :size="13" /></button>
                        <button class="grid h-7 w-7 place-items-center rounded-lg text-muted-foreground hover:bg-[#CC1D79]/10 hover:text-[#CC1D79]" @click="deleteTarget = a"><Trash2 :size="13" /></button>
                    </div>
                </div>
                <div class="mt-3 font-semibold">{{ a.name }}</div>
                <div class="text-xs text-muted-foreground">{{ a.type_label }}</div>
                <div class="mt-2 text-xl font-extrabold">{{ fmt(a.balance) }}</div>
                <div v-if="a.note" class="mt-1 truncate text-xs text-muted-foreground">{{ a.note }}</div>
            </div>
        </div>
        <div v-else class="rounded-2xl border border-dashed border-border bg-card p-10 text-center text-sm text-muted-foreground">
            Add your bank balances, gold, FDs, mutual funds, stocks and property to see your true net worth.
        </div>
    </div>

    <!-- Add / edit asset -->
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ editingId ? 'Edit asset' : 'Add asset' }}</DialogTitle>
                <DialogDescription>Track anything you own that has value.</DialogDescription>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Name</label>
                    <input v-model="form.name" type="text" placeholder="e.g. HDFC Savings" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Type</label>
                        <select v-model="form.type" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]">
                            <option v-for="t in types" :key="t.key" :value="t.key">{{ t.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Value (₹)</label>
                        <input v-model="form.balance" type="number" step="0.01" min="0" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                        <InputError :message="form.errors.balance" class="mt-1" />
                    </div>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Note (optional)</label>
                    <input v-model="form.note" type="text" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                </div>
                <DialogFooter>
                    <button type="submit" :disabled="form.processing" class="w-full rounded-xl py-2.5 font-semibold text-white disabled:opacity-60" style="background: linear-gradient(135deg, #CC1D79 0%, #06B7AD 100%)">
                        {{ form.processing ? 'Saving…' : editingId ? 'Save changes' : 'Add asset' }}
                    </button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Delete -->
    <Dialog :open="deleteTarget !== null" @update:open="(v) => { if (!v) deleteTarget = null; }">
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle>Remove asset?</DialogTitle>
                <DialogDescription>“{{ deleteTarget?.name }}” will be permanently removed.</DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <button class="flex-1 rounded-xl border border-border py-2.5 font-semibold hover:bg-muted" @click="deleteTarget = null">Cancel</button>
                <button class="flex-1 rounded-xl bg-[#CC1D79] py-2.5 font-semibold text-white disabled:opacity-60" :disabled="del.processing" @click="confirmDelete">Delete</button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
