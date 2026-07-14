<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { CreditCard, HandCoins, Pencil, Plus, Scale, Sparkles, Trash2 } from '@lucide/vue';
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
    layout: { breadcrumbs: [{ title: 'Debts', href: '/debts' }] },
});

interface DebtRow {
    id: number;
    name: string;
    institution: string | null;
    kind: string;
    category: string | null;
    balance: number;
    principal: number;
    interest_rate: number;
    emi: number;
    limit: number | null;
    min_due: number | null;
    due_day: number | null;
    utilisation: number | null;
    paid_percent: number | null;
    total_emis: number | null;
    emis_paid: number;
    remaining_emis: number | null;
    amount_paid: number;
    remaining_amount: number;
    repayment_progress: number;
}

const props = defineProps<{
    loan_categories: string[];
    summary: { total: number; monthly: number; avg_apr: number; count: number };
    loans: DebtRow[];
    cards: DebtRow[];
    payoff_order: DebtRow[];
}>();

const { fmt } = useCurrency();
const PALETTE = ['#CC1D79', '#7B2FF7', '#06B7AD', '#F5A524', '#3B82F6', '#10B981'];

const open = ref(false);
const editingId = ref<number | null>(null);
const form = useForm({
    kind: 'loan',
    name: '',
    institution: '',
    category: 'personal',
    interest_rate: '',
    balance: '',
    principal: '',
    emi: '',
    credit_limit: '',
    min_due: '',
    due_day: '',
    total_emis: '',
    emis_paid: '',
});

const blank = { kind: 'loan', name: '', institution: '', category: 'personal', interest_rate: '', balance: '', principal: '', emi: '', credit_limit: '', min_due: '', due_day: '', total_emis: '', emis_paid: '' };

const openAdd = (kind: string) => {
    editingId.value = null;
    form.clearErrors();
    form.defaults({ ...blank, kind });
    form.reset();
    open.value = true;
};
const openEdit = (d: DebtRow) => {
    editingId.value = d.id;
    form.clearErrors();
    form.defaults({
        kind: d.kind,
        name: d.name,
        institution: d.institution ?? '',
        category: d.category ?? 'personal',
        interest_rate: String(d.interest_rate),
        balance: String(d.balance),
        principal: String(d.principal),
        emi: String(d.emi),
        credit_limit: d.limit != null ? String(d.limit) : '',
        min_due: d.min_due != null ? String(d.min_due) : '',
        due_day: d.due_day != null ? String(d.due_day) : '',
        total_emis: d.total_emis != null ? String(d.total_emis) : '',
        emis_paid: d.emis_paid ? String(d.emis_paid) : '',
    });
    form.reset();
    open.value = true;
};
const submit = () => {
    const opts = { preserveScroll: true, onSuccess: () => (open.value = false) };
    if (editingId.value) form.put(`/debts/${editingId.value}`, opts);
    else form.post('/debts', opts);
};

// record payment
const payTarget = ref<DebtRow | null>(null);
const payForm = useForm({ amount: '' });
const submitPayment = () => {
    if (!payTarget.value) return;
    payForm.post(`/debts/${payTarget.value.id}/payment`, {
        preserveScroll: true,
        onSuccess: () => { payTarget.value = null; payForm.reset(); },
    });
};

// delete
const deleteTarget = ref<DebtRow | null>(null);
const del = useForm({});
const confirmDelete = () => {
    if (!deleteTarget.value) return;
    del.delete(`/debts/${deleteTarget.value.id}`, { preserveScroll: true, onSuccess: () => (deleteTarget.value = null) });
};
</script>

<template>
    <Head title="Debts" />

    <div class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-lg font-extrabold">Debts</h1>
            <div class="flex gap-2">
                <Link href="/coach" class="flex items-center gap-1.5 rounded-xl border border-border px-3 py-2 text-sm font-semibold hover:bg-muted">
                    <Sparkles :size="15" /> Debt Coach
                </Link>
                <button class="flex items-center gap-1.5 rounded-xl border border-border px-3 py-2 text-sm font-semibold hover:bg-muted" @click="openAdd('loan')">
                    <Plus :size="15" /> Loan
                </button>
                <button class="flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-sm font-semibold text-white" style="background: linear-gradient(135deg, #CC1D79 0%, #06B7AD 100%)" @click="openAdd('credit_card')">
                    <Plus :size="16" /> Card
                </button>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-border bg-card p-5">
                <div class="text-xs font-semibold text-muted-foreground">Total outstanding</div>
                <div class="mt-2 text-2xl font-extrabold text-[#CC1D79]">{{ fmt(summary.total) }}</div>
                <div class="mt-1 text-xs text-muted-foreground">{{ summary.count }} accounts</div>
            </div>
            <div class="rounded-2xl border border-border bg-card p-5">
                <div class="text-xs font-semibold text-muted-foreground">Monthly payment</div>
                <div class="mt-2 text-2xl font-extrabold">{{ fmt(summary.monthly) }}</div>
            </div>
            <div class="rounded-2xl border border-border bg-card p-5">
                <div class="text-xs font-semibold text-muted-foreground">Avg interest</div>
                <div class="mt-2 text-2xl font-extrabold">{{ summary.avg_apr }}%</div>
                <div class="mt-1 text-xs text-muted-foreground">weighted APR</div>
            </div>
        </div>

        <div v-if="payoff_order.length" class="rounded-2xl border border-border bg-card p-5">
            <div class="flex items-center justify-between">
                <h2 class="font-bold">Pay in this order <span class="text-sm font-normal text-muted-foreground">· highest interest first</span></h2>
                <Link href="/coach" class="text-sm font-semibold text-[#CC1D79]">Open coach ›</Link>
            </div>
            <div class="mt-4 divide-y divide-border">
                <div v-for="(d, i) in payoff_order" :key="d.id" class="flex items-center gap-3 py-3">
                    <span class="grid h-8 w-8 flex-none place-items-center rounded-lg text-sm font-bold text-white" :style="{ background: PALETTE[i % PALETTE.length] }">{{ i + 1 }}</span>
                    <div class="flex-1">
                        <span class="font-semibold">{{ d.name }}</span>
                        <span v-if="i === 0" class="ml-2 rounded-full bg-[#CC1D79]/12 px-2 py-0.5 text-xs font-bold text-[#CC1D79]">Focus ⚡</span>
                    </div>
                    <span class="text-sm text-muted-foreground">{{ d.interest_rate }}% APR</span>
                    <span class="w-28 text-right font-bold tabular-nums text-[#CC1D79]">{{ fmt(d.balance) }}</span>
                </div>
            </div>
        </div>

        <div v-if="loans.length">
            <h2 class="mb-3 font-bold">Loans</h2>
            <div class="grid gap-4 md:grid-cols-2">
                <div v-for="(d, i) in loans" :key="d.id" class="group rounded-2xl border border-border bg-card p-5">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="grid h-10 w-10 place-items-center rounded-xl text-white" :style="{ background: PALETTE[i % PALETTE.length] }"><Scale :size="18" /></span>
                            <div>
                                <div class="font-semibold">{{ d.name }}<span v-if="d.institution"> · {{ d.institution }}</span></div>
                                <div class="text-xs text-muted-foreground">{{ d.interest_rate }}% · EMI {{ fmt(d.emi) }}</div>
                            </div>
                        </div>
                        <div class="text-right font-bold text-[#CC1D79]">{{ fmt(d.balance) }}</div>
                    </div>
                    <!-- Repayment tracking (auto-updates on each recorded payment) -->
                    <div class="mt-4 rounded-xl bg-muted/40 p-3">
                        <div v-if="d.total_emis" class="mb-2.5 grid grid-cols-3 divide-x divide-border text-center">
                            <div><div class="text-base font-extrabold">{{ d.total_emis }}</div><div class="text-[10px] font-medium text-muted-foreground">Total EMIs</div></div>
                            <div><div class="text-base font-extrabold text-[#06B7AD]">{{ d.emis_paid }}</div><div class="text-[10px] font-medium text-muted-foreground">Paid</div></div>
                            <div><div class="text-base font-extrabold text-[#CC1D79]">{{ d.remaining_emis }}</div><div class="text-[10px] font-medium text-muted-foreground">Remaining</div></div>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-muted-foreground">Paid till date · <b class="text-foreground">{{ fmt(d.amount_paid) }}</b></span>
                            <span class="text-muted-foreground">Left · <b class="text-[#CC1D79]">{{ fmt(d.remaining_amount) }}</b></span>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-muted">
                            <div class="h-full rounded-full transition-all" :style="{ width: d.repayment_progress + '%', background: 'linear-gradient(90deg,#CC1D79,#06B7AD)' }" />
                        </div>
                        <div class="mt-1 text-[11px] font-semibold text-[#06B7AD]">{{ d.repayment_progress }}% repaid</div>
                    </div>
                    <div class="mt-3 flex items-center justify-end gap-1">
                        <button class="flex items-center gap-1 rounded-lg bg-[#06B7AD]/10 px-2.5 py-1 text-xs font-semibold text-[#06B7AD]" @click="payTarget = d"><HandCoins :size="13" /> Record EMI</button>
                        <button class="grid h-7 w-7 place-items-center rounded-lg text-muted-foreground hover:bg-muted" @click="openEdit(d)"><Pencil :size="13" /></button>
                        <button class="grid h-7 w-7 place-items-center rounded-lg text-muted-foreground hover:bg-[#CC1D79]/10 hover:text-[#CC1D79]" @click="deleteTarget = d"><Trash2 :size="13" /></button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="cards.length">
            <h2 class="mb-3 font-bold">Credit cards</h2>
            <div class="grid gap-4 md:grid-cols-2">
                <div v-for="(d, i) in cards" :key="d.id" class="rounded-2xl border border-border bg-card p-5">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="grid h-10 w-10 place-items-center rounded-xl text-white" :style="{ background: PALETTE[i % PALETTE.length] }"><CreditCard :size="18" /></span>
                            <div>
                                <div class="font-semibold">{{ d.name }}</div>
                                <div class="text-xs text-muted-foreground">{{ d.interest_rate }}% APR · min {{ fmt(d.min_due ?? 0) }}</div>
                            </div>
                        </div>
                        <span class="rounded-full px-2.5 py-1 text-xs font-bold" :style="{ background: (d.utilisation ?? 0) > 80 ? '#CC1D79' + '1f' : '#06B7AD' + '1f', color: (d.utilisation ?? 0) > 80 ? '#CC1D79' : '#06B7AD' }">{{ d.utilisation }}% used</span>
                    </div>
                    <div class="mt-4 text-sm text-muted-foreground">{{ fmt(d.balance) }} of {{ fmt(d.limit ?? 0) }}</div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-muted">
                        <div class="h-full rounded-full" :style="{ width: Math.min(100, d.utilisation ?? 0) + '%', background: (d.utilisation ?? 0) > 80 ? '#CC1D79' : '#06B7AD' }" />
                    </div>
                    <div class="mt-3 flex justify-end gap-1">
                        <button class="flex items-center gap-1 rounded-lg bg-[#06B7AD]/10 px-2.5 py-1 text-xs font-semibold text-[#06B7AD]" @click="payTarget = d"><HandCoins :size="13" /> Pay</button>
                        <button class="grid h-7 w-7 place-items-center rounded-lg text-muted-foreground hover:bg-muted" @click="openEdit(d)"><Pencil :size="13" /></button>
                        <button class="grid h-7 w-7 place-items-center rounded-lg text-muted-foreground hover:bg-[#CC1D79]/10 hover:text-[#CC1D79]" @click="deleteTarget = d"><Trash2 :size="13" /></button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="!loans.length && !cards.length" class="rounded-2xl border border-dashed border-border bg-card p-10 text-center text-sm text-muted-foreground">
            No debts yet. Add a loan or credit card to start your payoff plan.
        </div>
    </div>

    <!-- Add / edit debt -->
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ editingId ? 'Edit' : 'Add' }} {{ form.kind === 'credit_card' ? 'credit card' : 'loan' }}</DialogTitle>
                <DialogDescription>Track the balance, interest and monthly payment.</DialogDescription>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="submit">
                <div class="inline-flex w-full gap-1 rounded-xl bg-muted p-1">
                    <button type="button" class="flex-1 rounded-lg py-1.5 text-sm font-semibold" :class="form.kind === 'loan' ? 'bg-card shadow-sm' : 'text-muted-foreground'" @click="form.kind = 'loan'">Loan</button>
                    <button type="button" class="flex-1 rounded-lg py-1.5 text-sm font-semibold" :class="form.kind === 'credit_card' ? 'bg-card shadow-sm' : 'text-muted-foreground'" @click="form.kind = 'credit_card'">Credit card</button>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Name</label>
                        <input v-model="form.name" type="text" placeholder="e.g. Home Loan" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                        <InputError :message="form.errors.name" class="mt-1" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Bank / lender</label>
                        <input v-model="form.institution" type="text" placeholder="e.g. HDFC" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Outstanding (₹)</label>
                        <input v-model="form.balance" type="number" step="0.01" min="0" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                        <InputError :message="form.errors.balance" class="mt-1" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Interest rate (% APR)</label>
                        <input v-model="form.interest_rate" type="number" step="0.01" min="0" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                        <InputError :message="form.errors.interest_rate" class="mt-1" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div v-if="form.kind === 'loan'">
                        <label class="mb-1.5 block text-sm font-medium">Original amount (₹)</label>
                        <input v-model="form.principal" type="number" step="0.01" min="0" placeholder="optional" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ form.kind === 'credit_card' ? 'Min due (₹)' : 'EMI (₹)' }}</label>
                        <input v-model="form.emi" type="number" step="0.01" min="0" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                    </div>
                    <div v-if="form.kind === 'credit_card'">
                        <label class="mb-1.5 block text-sm font-medium">Credit limit (₹)</label>
                        <input v-model="form.credit_limit" type="number" step="0.01" min="0" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                    </div>
                    <div v-if="form.kind === 'loan'">
                        <label class="mb-1.5 block text-sm font-medium">Type</label>
                        <select v-model="form.category" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm capitalize outline-none focus:border-[#CC1D79]">
                            <option v-for="c in loan_categories" :key="c" :value="c" class="capitalize">{{ c }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Due day (1–31)</label>
                        <input v-model="form.due_day" type="number" min="1" max="31" placeholder="optional" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                    </div>
                </div>
                <div v-if="form.kind === 'loan'" class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Total EMIs (tenure)</label>
                        <input v-model="form.total_emis" type="number" min="1" max="1000" placeholder="e.g. 60" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                        <InputError :message="form.errors.total_emis" class="mt-1" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">EMIs already paid</label>
                        <input v-model="form.emis_paid" type="number" min="0" max="1000" placeholder="0" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                        <InputError :message="form.errors.emis_paid" class="mt-1" />
                    </div>
                </div>
                <DialogFooter>
                    <button type="submit" :disabled="form.processing" class="w-full rounded-xl py-2.5 font-semibold text-white disabled:opacity-60" style="background: linear-gradient(135deg, #CC1D79 0%, #06B7AD 100%)">
                        {{ form.processing ? 'Saving…' : editingId ? 'Save changes' : 'Add' }}
                    </button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Record payment -->
    <Dialog :open="payTarget !== null" @update:open="(v) => { if (!v) payTarget = null; }">
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle>Record a payment</DialogTitle>
                <DialogDescription>Reduce the balance on {{ payTarget?.name }} ({{ fmt(payTarget?.balance ?? 0) }} left).</DialogDescription>
            </DialogHeader>
            <form @submit.prevent="submitPayment">
                <label class="mb-1.5 block text-sm font-medium">Amount (₹)</label>
                <input v-model="payForm.amount" type="number" step="0.01" min="0" autofocus class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                <InputError :message="payForm.errors.amount" class="mt-1" />
                <DialogFooter class="mt-4 gap-2">
                    <button type="button" class="flex-1 rounded-xl border border-border py-2.5 font-semibold hover:bg-muted" @click="payTarget = null">Cancel</button>
                    <button type="submit" :disabled="payForm.processing" class="flex-1 rounded-xl py-2.5 font-semibold text-white disabled:opacity-60" style="background: linear-gradient(135deg, #CC1D79 0%, #06B7AD 100%)">Record</button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Delete -->
    <Dialog :open="deleteTarget !== null" @update:open="(v) => { if (!v) deleteTarget = null; }">
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle>Remove debt?</DialogTitle>
                <DialogDescription>“{{ deleteTarget?.name }}” will be permanently removed.</DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <button class="flex-1 rounded-xl border border-border py-2.5 font-semibold hover:bg-muted" @click="deleteTarget = null">Cancel</button>
                <button class="flex-1 rounded-xl bg-[#CC1D79] py-2.5 font-semibold text-white disabled:opacity-60" :disabled="del.processing" @click="confirmDelete">Delete</button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
