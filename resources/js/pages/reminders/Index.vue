<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { AlertTriangle, CalendarClock, Check, Pencil, Plus, Repeat, Trash2 } from '@lucide/vue';
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
    layout: { breadcrumbs: [{ title: 'Reminders', href: '/reminders' }] },
});

interface Reminder {
    id: number;
    name: string;
    kind: string;
    category: string | null;
    amount: number;
    due_date: string;
    due_on: string;
    repeat: string;
    remind_days_before: number;
    days: number;
    status: string;
}

defineProps<{
    overdue: Reminder[];
    upcoming: Reminder[];
    subscriptions: Reminder[];
    subscription_monthly: number;
}>();

const { fmt } = useCurrency();
const today = new Date().toISOString().slice(0, 10);
const when = (r: Reminder) =>
    r.days < 0 ? `${Math.abs(r.days)} days overdue`
    : r.days === 0 ? 'Due today'
    : r.days === 1 ? 'Due tomorrow'
    : `${r.due_date} · in ${r.days} days`;

const open = ref(false);
const editingId = ref<number | null>(null);
const form = useForm({ name: '', kind: 'bill', category: '', amount: '', due_date: today, repeat: 'monthly', remind_days_before: 3 });

const openAdd = () => {
    editingId.value = null;
    form.clearErrors();
    form.defaults({ name: '', kind: 'bill', category: '', amount: '', due_date: today, repeat: 'monthly', remind_days_before: 3 });
    form.reset();
    open.value = true;
};
const openEdit = (r: Reminder) => {
    editingId.value = r.id;
    form.clearErrors();
    form.defaults({
        name: r.name, kind: r.kind, category: r.category ?? '', amount: String(r.amount),
        due_date: r.due_on, repeat: r.repeat, remind_days_before: r.remind_days_before,
    });
    form.reset();
    open.value = true;
};
const submit = () => {
    const opts = { preserveScroll: true, onSuccess: () => (open.value = false) };
    if (editingId.value) form.put(`/bills/${editingId.value}`, opts);
    else form.post('/bills', opts);
};

const paidForm = useForm({});
const markPaid = (r: Reminder) => paidForm.post(`/bills/${r.id}/paid`, { preserveScroll: true });

const deleteTarget = ref<Reminder | null>(null);
const del = useForm({});
const confirmDelete = () => {
    if (!deleteTarget.value) return;
    del.delete(`/bills/${deleteTarget.value.id}`, { preserveScroll: true, onSuccess: () => (deleteTarget.value = null) });
};
</script>

<template>
    <Head title="Reminders" />

    <div class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-lg font-extrabold">Reminders</h1>
            <button class="flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-sm font-semibold text-white" style="background: linear-gradient(135deg, #CC1D79 0%, #06B7AD 100%)" @click="openAdd">
                <Plus :size="16" /> Add reminder
            </button>
        </div>

        <!-- Overdue -->
        <div v-for="r in overdue" :key="'o' + r.id" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-[#CC1D79]/25 p-5" style="background: rgba(204, 29, 121, 0.06)">
            <div class="flex items-center gap-3">
                <AlertTriangle class="text-[#CC1D79]" :size="20" />
                <div>
                    <div class="font-bold text-[#CC1D79]">{{ r.name }} · overdue</div>
                    <div class="text-sm text-muted-foreground">{{ when(r) }} · {{ fmt(r.amount) }}</div>
                </div>
            </div>
            <div class="flex gap-2">
                <button class="flex items-center gap-1.5 rounded-lg bg-[#06B7AD] px-3 py-1.5 text-sm font-semibold text-white" @click="markPaid(r)"><Check :size="14" /> Mark paid</button>
                <button class="grid h-8 w-8 place-items-center rounded-lg text-muted-foreground hover:bg-muted" @click="openEdit(r)"><Pencil :size="14" /></button>
            </div>
        </div>

        <!-- Upcoming -->
        <div>
            <div class="mb-3 flex items-center gap-2"><CalendarClock class="text-muted-foreground" :size="18" /><h2 class="font-bold">Upcoming bills &amp; EMIs</h2></div>
            <div class="divide-y divide-border overflow-hidden rounded-2xl border border-border bg-card">
                <div v-for="r in upcoming" :key="r.id" class="group flex items-center justify-between px-5 py-4">
                    <div>
                        <div class="font-semibold">{{ r.name }}</div>
                        <div class="text-xs text-muted-foreground">{{ when(r) }} · {{ r.repeat }}</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="font-bold tabular-nums">{{ fmt(r.amount) }}</div>
                        <button class="flex items-center gap-1 rounded-lg bg-[#06B7AD]/10 px-2.5 py-1 text-xs font-semibold text-[#06B7AD]" @click="markPaid(r)"><Check :size="13" /> Paid</button>
                        <button class="grid h-7 w-7 place-items-center rounded-lg text-muted-foreground opacity-0 group-hover:opacity-100 hover:bg-muted" @click="openEdit(r)"><Pencil :size="13" /></button>
                        <button class="grid h-7 w-7 place-items-center rounded-lg text-muted-foreground opacity-0 group-hover:opacity-100 hover:bg-[#CC1D79]/10 hover:text-[#CC1D79]" @click="deleteTarget = r"><Trash2 :size="13" /></button>
                    </div>
                </div>
                <div v-if="!upcoming.length" class="px-5 py-8 text-center text-sm text-muted-foreground">Nothing upcoming. 🎉</div>
            </div>
        </div>

        <!-- Subscriptions -->
        <div v-if="subscriptions.length">
            <div class="mb-3 flex items-center justify-between">
                <div class="flex items-center gap-2"><Repeat class="text-muted-foreground" :size="18" /><h2 class="font-bold">Subscriptions</h2></div>
                <div class="text-sm text-muted-foreground">Total <strong class="text-foreground">{{ fmt(subscription_monthly) }}</strong>/mo</div>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="r in subscriptions" :key="r.id" class="group flex items-center justify-between rounded-2xl border border-border bg-card p-4">
                    <div>
                        <div class="font-semibold">{{ r.name }}</div>
                        <div class="text-xs text-muted-foreground">Renews {{ r.due_date }}</div>
                    </div>
                    <div class="flex items-center gap-1">
                        <div class="mr-1 font-bold tabular-nums">{{ fmt(r.amount) }}</div>
                        <button class="grid h-7 w-7 place-items-center rounded-lg text-muted-foreground opacity-0 group-hover:opacity-100 hover:bg-muted" @click="openEdit(r)"><Pencil :size="13" /></button>
                        <button class="grid h-7 w-7 place-items-center rounded-lg text-muted-foreground opacity-0 group-hover:opacity-100 hover:bg-[#CC1D79]/10 hover:text-[#CC1D79]" @click="deleteTarget = r"><Trash2 :size="13" /></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add / edit -->
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ editingId ? 'Edit reminder' : 'Add reminder' }}</DialogTitle>
                <DialogDescription>Bills, EMIs and subscriptions you don't want to miss.</DialogDescription>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Name</label>
                        <input v-model="form.name" type="text" placeholder="e.g. Electricity" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                        <InputError :message="form.errors.name" class="mt-1" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Type</label>
                        <select v-model="form.kind" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]">
                            <option value="bill">Bill</option>
                            <option value="emi">EMI</option>
                            <option value="subscription">Subscription</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Amount (₹)</label>
                        <input v-model="form.amount" type="number" step="0.01" min="0" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                        <InputError :message="form.errors.amount" class="mt-1" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Due date</label>
                        <input v-model="form.due_date" type="date" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                        <InputError :message="form.errors.due_date" class="mt-1" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Repeat</label>
                        <select v-model="form.repeat" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]">
                            <option value="none">One-time</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Remind (days before)</label>
                        <input v-model.number="form.remind_days_before" type="number" min="0" max="30" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                    </div>
                </div>
                <DialogFooter>
                    <button type="submit" :disabled="form.processing" class="w-full rounded-xl py-2.5 font-semibold text-white disabled:opacity-60" style="background: linear-gradient(135deg, #CC1D79 0%, #06B7AD 100%)">
                        {{ form.processing ? 'Saving…' : editingId ? 'Save changes' : 'Add reminder' }}
                    </button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Delete -->
    <Dialog :open="deleteTarget !== null" @update:open="(v) => { if (!v) deleteTarget = null; }">
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle>Delete reminder?</DialogTitle>
                <DialogDescription>“{{ deleteTarget?.name }}” will be permanently removed.</DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <button class="flex-1 rounded-xl border border-border py-2.5 font-semibold hover:bg-muted" @click="deleteTarget = null">Cancel</button>
                <button class="flex-1 rounded-xl bg-[#CC1D79] py-2.5 font-semibold text-white disabled:opacity-60" :disabled="del.processing" @click="confirmDelete">Delete</button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
