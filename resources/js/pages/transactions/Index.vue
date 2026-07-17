<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Mic, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useCurrency } from '@/composables/useCurrency';
import { parseSpokenTransaction, useVoice } from '@/composables/useVoice';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Transactions', href: '/transactions' }] },
});

interface Item {
    id: number;
    type: string;
    category: string | null;
    description: string | null;
    payee: string | null;
    method: string | null;
    amount: number;
    occurred_on: string;
}
interface Group {
    date: string;
    items: Item[];
}

const props = defineProps<{
    filter: string;
    categories: { income: string[]; expense: string[] };
    totals: { income: number; expense: number; net: number };
    groups: Group[];
}>();

const { fmt } = useCurrency();
const today = new Date().toISOString().slice(0, 10);
const tabs = [
    { key: 'all', label: 'All' },
    { key: 'income', label: 'Income' },
    { key: 'expense', label: 'Expenses' },
];
const setFilter = (key: string) =>
    router.get('/transactions', { type: key }, { preserveState: true, preserveScroll: true });

const hasRows = computed(() => props.groups.length > 0);

const open = ref(false);
const editingId = ref<number | null>(null);
const form = useForm({
    type: 'expense',
    amount: '',
    category: '',
    description: '',
    payee: '',
    method: '',
    occurred_on: today,
});
const categoryOptions = computed(() => props.categories[form.type as 'income' | 'expense'] ?? []);

const openAdd = (type = 'expense') => {
    editingId.value = null;
    form.clearErrors();
    form.defaults({ type, amount: '', category: '', description: '', payee: '', method: '', occurred_on: today });
    form.reset();
    open.value = true;
};
const openEdit = (i: Item) => {
    editingId.value = i.id;
    form.clearErrors();
    form.defaults({
        type: i.type,
        amount: String(i.amount),
        category: i.category ?? '',
        description: i.description ?? '',
        payee: i.payee ?? '',
        method: i.method ?? '',
        occurred_on: i.occurred_on,
    });
    form.reset();
    open.value = true;
};
const submit = () => {
    const opts = { preserveScroll: true, onSuccess: () => (open.value = false) };

    if (editingId.value) {
form.put(`/entries/${editingId.value}`, opts);
} else {
form.post('/entries', opts);
}
};

// Voice input: "spent 500 on fuel" → prefilled add dialog.
const voice = useVoice();
const heardText = ref('');
const startVoice = () => {
    openAdd('expense');
    heardText.value = '';
    voice.start((text) => {
        heardText.value = text;
        const p = parseSpokenTransaction(text);
        form.type = p.type;

        if (p.amount) {
form.amount = p.amount;
}

        if (p.category) {
form.category = p.category;
}

        if (p.description) {
form.description = p.description;
}
    });
};

const deleteTarget = ref<Item | null>(null);
const del = useForm({});
const confirmDelete = () => {
    if (!deleteTarget.value) {
return;
}

    del.delete(`/entries/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onSuccess: () => (deleteTarget.value = null),
    });
};
</script>

<template>
    <Head title="Transactions" />

    <div class="flex flex-1 flex-col gap-5 p-4 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-lg font-extrabold">Transactions</h1>
            <div class="flex gap-2">
                <button
                    v-if="voice.supported"
                    class="flex items-center gap-1.5 rounded-xl border px-3 py-2 text-sm font-semibold transition-colors"
                    :class="voice.listening.value ? 'border-[#CC1D79] bg-[#CC1D79]/10 text-[#CC1D79]' : 'border-border hover:bg-muted'"
                    title="Add by voice — e.g. “spent 500 on fuel”"
                    @click="startVoice"
                >
                    <Mic :size="15" /> {{ voice.listening.value ? 'Listening…' : 'Voice' }}
                </button>
                <button
                    class="flex items-center gap-1.5 rounded-xl border border-border px-3 py-2 text-sm font-semibold text-[#10B981] hover:bg-muted"
                    @click="openAdd('income')"
                >
                    <Plus :size="15" /> Income
                </button>
                <button
                    class="flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-sm font-semibold text-white"
                    style="background: linear-gradient(135deg, #CC1D79 0%, #06B7AD 100%)"
                    @click="openAdd('expense')"
                >
                    <Plus :size="16" /> Add expense
                </button>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-border bg-card p-5">
                <div class="text-xs font-semibold text-muted-foreground">Income</div>
                <div class="mt-2 text-2xl font-extrabold text-[#10B981]">{{ fmt(totals.income) }}</div>
            </div>
            <div class="rounded-2xl border border-border bg-card p-5">
                <div class="text-xs font-semibold text-muted-foreground">Expenses</div>
                <div class="mt-2 text-2xl font-extrabold text-[#CC1D79]">{{ fmt(totals.expense) }}</div>
            </div>
            <div class="rounded-2xl border border-border bg-card p-5">
                <div class="text-xs font-semibold text-muted-foreground">Net this month</div>
                <div class="mt-2 text-2xl font-extrabold">{{ fmt(totals.net) }}</div>
            </div>
        </div>

        <div class="inline-flex w-fit gap-1 rounded-xl bg-muted p-1">
            <button
                v-for="t in tabs"
                :key="t.key"
                class="rounded-lg px-4 py-1.5 text-sm font-semibold transition-colors"
                :class="filter === t.key ? 'bg-card shadow-sm' : 'text-muted-foreground'"
                @click="setFilter(t.key)"
            >
                {{ t.label }}
            </button>
        </div>

        <div class="space-y-5">
            <div v-for="g in groups" :key="g.date">
                <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ g.date }}</div>
                <div class="divide-y divide-border overflow-hidden rounded-2xl border border-border bg-card">
                    <div v-for="item in g.items" :key="item.id" class="group flex items-center justify-between px-5 py-3.5">
                        <div class="min-w-0">
                            <div class="truncate text-sm font-semibold">{{ item.description ?? item.payee ?? item.category }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ item.category }}<span v-if="item.method"> · {{ item.method }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <div
                                class="mr-1 font-bold tabular-nums"
                                :class="item.type === 'income' ? 'text-[#10B981]' : 'text-[#CC1D79]'"
                            >
                                {{ item.type === 'income' ? '+' : '−' }}{{ fmt(item.amount) }}
                            </div>
                            <button class="grid h-8 w-8 place-items-center rounded-lg text-muted-foreground opacity-0 group-hover:opacity-100 hover:bg-muted" @click="openEdit(item)">
                                <Pencil :size="14" />
                            </button>
                            <button class="grid h-8 w-8 place-items-center rounded-lg text-muted-foreground opacity-0 group-hover:opacity-100 hover:bg-[#CC1D79]/10 hover:text-[#CC1D79]" @click="deleteTarget = item">
                                <Trash2 :size="14" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="!hasRows" class="rounded-2xl border border-dashed border-border bg-card p-10 text-center text-sm text-muted-foreground">
                No transactions this month yet. Add your first one above.
            </div>
        </div>
    </div>

    <!-- Add / edit -->
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ editingId ? 'Edit transaction' : 'Add transaction' }}</DialogTitle>
                <DialogDescription>
                    <span v-if="heardText" class="text-[#CC1D79]">Heard: “{{ heardText }}” — check the details below.</span>
                    <span v-else>Record income or an expense.</span>
                </DialogDescription>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="submit">
                <div class="inline-flex w-full gap-1 rounded-xl bg-muted p-1">
                    <button type="button" class="flex-1 rounded-lg py-1.5 text-sm font-semibold" :class="form.type === 'expense' ? 'bg-card shadow-sm' : 'text-muted-foreground'" @click="form.type = 'expense'">Expense</button>
                    <button type="button" class="flex-1 rounded-lg py-1.5 text-sm font-semibold" :class="form.type === 'income' ? 'bg-card shadow-sm' : 'text-muted-foreground'" @click="form.type = 'income'">Income</button>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Amount (₹)</label>
                        <input v-model="form.amount" type="number" step="0.01" min="0" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                        <InputError :message="form.errors.amount" class="mt-1" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Date</label>
                        <input v-model="form.occurred_on" type="date" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                        <InputError :message="form.errors.occurred_on" class="mt-1" />
                    </div>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Category</label>
                    <input v-model="form.category" list="tx-cats" placeholder="e.g. Food" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                    <datalist id="tx-cats">
                        <option v-for="c in categoryOptions" :key="c" :value="c" />
                    </datalist>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Description</label>
                    <input v-model="form.description" type="text" placeholder="e.g. Swiggy order" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ form.type === 'income' ? 'From' : 'Paid to' }}</label>
                        <input v-model="form.payee" type="text" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Method</label>
                        <input v-model="form.method" list="tx-methods" placeholder="UPI, Card…" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                        <datalist id="tx-methods">
                            <option value="UPI" /><option value="Debit Card" /><option value="Credit Card" /><option value="Cash" /><option value="Bank transfer" /><option value="Auto-debit" />
                        </datalist>
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

    <!-- Delete confirm -->
    <Dialog :open="deleteTarget !== null" @update:open="(v) => { if (!v) deleteTarget = null; }">
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle>Delete transaction?</DialogTitle>
                <DialogDescription>This transaction will be permanently removed.</DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <button class="flex-1 rounded-xl border border-border py-2.5 font-semibold hover:bg-muted" @click="deleteTarget = null">Cancel</button>
                <button class="flex-1 rounded-xl bg-[#CC1D79] py-2.5 font-semibold text-white disabled:opacity-60" :disabled="del.processing" @click="confirmDelete">Delete</button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
