<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, ShieldCheck, Target, Trash2, TrendingUp } from '@lucide/vue';
import { ref } from 'vue';
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

defineOptions({
    layout: { breadcrumbs: [{ title: 'Budgets & Goals', href: '/planning' }] },
});

interface BudgetRow { id: number; category: string; limit: number; spent: number; percent: number; exceeded: boolean }
interface GoalRow { id: number; name: string; type: string; target: number; saved: number; progress: number; target_date: string | null }

defineProps<{ budgets: BudgetRow[]; goals: GoalRow[] }>();

const { fmt } = useCurrency();

// ---- Budgets ----
const budgetOpen = ref(false);
const budgetId = ref<number | null>(null);
const budgetForm = useForm({ category: '', limit: '' });
const openBudget = (b?: BudgetRow) => {
    budgetId.value = b?.id ?? null;
    budgetForm.clearErrors();
    budgetForm.defaults({ category: b?.category ?? '', limit: b ? String(b.limit) : '' });
    budgetForm.reset();
    budgetOpen.value = true;
};
const submitBudget = () => {
    const opts = { preserveScroll: true, onSuccess: () => (budgetOpen.value = false) };

    if (budgetId.value) {
budgetForm.put(`/budgets/${budgetId.value}`, opts);
} else {
budgetForm.post('/budgets', opts);
}
};
const delBudget = useForm({});
const removeBudget = (b: BudgetRow) => delBudget.delete(`/budgets/${b.id}`, { preserveScroll: true });

// ---- Goals ----
const goalOpen = ref(false);
const goalId = ref<number | null>(null);
const goalForm = useForm({ name: '', type: 'savings', target: '', saved: '', target_date: '' });
const openGoal = (g?: GoalRow) => {
    goalId.value = g?.id ?? null;
    goalForm.clearErrors();
    goalForm.defaults({
        name: g?.name ?? '',
        type: g?.type ?? 'savings',
        target: g ? String(g.target) : '',
        saved: g ? String(g.saved) : '',
        target_date: g?.target_date ?? '',
    });
    goalForm.reset();
    goalOpen.value = true;
};
const submitGoal = () => {
    const opts = { preserveScroll: true, onSuccess: () => (goalOpen.value = false) };

    if (goalId.value) {
goalForm.put(`/goals/${goalId.value}`, opts);
} else {
goalForm.post('/goals', opts);
}
};
const delGoal = useForm({});
const removeGoal = (g: GoalRow) => delGoal.delete(`/goals/${g.id}`, { preserveScroll: true });

// contribute
const contribTarget = ref<GoalRow | null>(null);
const contribForm = useForm({ amount: '' });
const submitContrib = () => {
    if (!contribTarget.value) {
return;
}

    contribForm.post(`/goals/${contribTarget.value.id}/contribute`, {
        preserveScroll: true,
        onSuccess: () => {
 contribTarget.value = null; contribForm.reset(); 
},
    });
};
</script>

<template>
    <Head title="Budgets & Goals" />

    <div class="flex flex-1 flex-col gap-8 p-4 sm:p-6">
        <!-- Budgets -->
        <section>
            <div class="mb-3 flex items-center justify-between">
                <h2 class="flex items-center gap-2 font-bold"><TrendingUp :size="18" class="text-[#CC1D79]" /> Monthly budgets</h2>
                <button class="flex items-center gap-1.5 rounded-xl border border-border px-3 py-1.5 text-sm font-semibold hover:bg-muted" @click="openBudget()"><Plus :size="15" /> Budget</button>
            </div>
            <div v-if="budgets.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="b in budgets" :key="b.id" class="group rounded-2xl border border-border bg-card p-5">
                    <div class="flex items-center justify-between">
                        <span class="font-semibold">{{ b.category }}</span>
                        <div class="flex gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                            <button class="grid h-7 w-7 place-items-center rounded-lg text-muted-foreground hover:bg-muted" @click="openBudget(b)"><Pencil :size="13" /></button>
                            <button class="grid h-7 w-7 place-items-center rounded-lg text-muted-foreground hover:bg-[#CC1D79]/10 hover:text-[#CC1D79]" @click="removeBudget(b)"><Trash2 :size="13" /></button>
                        </div>
                    </div>
                    <div class="mt-2 text-sm tabular-nums" :class="b.exceeded ? 'font-bold text-[#CC1D79]' : 'text-muted-foreground'">{{ fmt(b.spent) }} / {{ fmt(b.limit) }}</div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-muted">
                        <div class="h-full rounded-full" :style="{ width: Math.min(100, b.percent) + '%', background: b.exceeded ? '#CC1D79' : b.percent > 80 ? '#F5A524' : '#06B7AD' }" />
                    </div>
                    <div class="mt-1 text-xs" :class="b.exceeded ? 'font-semibold text-[#CC1D79]' : 'text-muted-foreground'">{{ b.exceeded ? 'Budget exceeded' : b.percent + '% used' }}</div>
                </div>
            </div>
            <div v-else class="rounded-2xl border border-dashed border-border bg-card p-8 text-center text-sm text-muted-foreground">No budgets yet. Set spending limits per category.</div>
        </section>

        <!-- Goals -->
        <section>
            <div class="mb-3 flex items-center justify-between">
                <h2 class="flex items-center gap-2 font-bold"><Target :size="18" class="text-[#7B2FF7]" /> Savings goals &amp; emergency fund</h2>
                <button class="flex items-center gap-1.5 rounded-xl px-3.5 py-1.5 text-sm font-semibold text-white" style="background: linear-gradient(135deg, #CC1D79 0%, #06B7AD 100%)" @click="openGoal()"><Plus :size="15" /> Goal</button>
            </div>
            <div v-if="goals.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="g in goals" :key="g.id" class="group rounded-2xl border border-border bg-card p-5">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <ShieldCheck v-if="g.type === 'emergency_fund'" :size="16" class="text-[#06B7AD]" />
                            <Target v-else :size="16" class="text-[#7B2FF7]" />
                            <span class="font-semibold">{{ g.name }}</span>
                        </div>
                        <div class="flex gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                            <button class="grid h-7 w-7 place-items-center rounded-lg text-muted-foreground hover:bg-muted" @click="openGoal(g)"><Pencil :size="13" /></button>
                            <button class="grid h-7 w-7 place-items-center rounded-lg text-muted-foreground hover:bg-[#CC1D79]/10 hover:text-[#CC1D79]" @click="removeGoal(g)"><Trash2 :size="13" /></button>
                        </div>
                    </div>
                    <div class="mt-3 flex items-end justify-between">
                        <div class="text-xl font-extrabold">{{ fmt(g.saved) }}</div>
                        <div class="text-sm text-muted-foreground">of {{ fmt(g.target) }}</div>
                    </div>
                    <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-muted">
                        <div class="h-full rounded-full" :style="{ width: g.progress + '%', background: g.type === 'emergency_fund' ? 'linear-gradient(90deg,#CC1D79,#06B7AD)' : 'linear-gradient(90deg,#7B2FF7,#3B82F6)' }" />
                    </div>
                    <div class="mt-3 flex items-center justify-between">
                        <span class="text-xs font-semibold" :class="g.type === 'emergency_fund' ? 'text-[#06B7AD]' : 'text-[#7B2FF7]'">{{ g.progress }}% funded</span>
                        <button class="rounded-lg bg-[#06B7AD]/10 px-2.5 py-1 text-xs font-semibold text-[#06B7AD]" @click="contribTarget = g">+ Add money</button>
                    </div>
                </div>
            </div>
            <div v-else class="rounded-2xl border border-dashed border-border bg-card p-8 text-center text-sm text-muted-foreground">No goals yet. Start an emergency fund or a savings goal.</div>
        </section>
    </div>

    <!-- Budget dialog -->
    <Dialog v-model:open="budgetOpen">
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle>{{ budgetId ? 'Edit budget' : 'New budget' }}</DialogTitle>
                <DialogDescription>Set a monthly limit for a category.</DialogDescription>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="submitBudget">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Category</label>
                    <input v-model="budgetForm.category" type="text" placeholder="e.g. Food" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                    <InputError :message="budgetForm.errors.category" class="mt-1" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Monthly limit (₹)</label>
                    <input v-model="budgetForm.limit" type="number" step="0.01" min="0" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                    <InputError :message="budgetForm.errors.limit" class="mt-1" />
                </div>
                <DialogFooter>
                    <button type="submit" :disabled="budgetForm.processing" class="w-full rounded-xl py-2.5 font-semibold text-white disabled:opacity-60" style="background: linear-gradient(135deg, #CC1D79 0%, #06B7AD 100%)">Save</button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Goal dialog -->
    <Dialog v-model:open="goalOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ goalId ? 'Edit goal' : 'New goal' }}</DialogTitle>
                <DialogDescription>Set a target and track your progress.</DialogDescription>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="submitGoal">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Name</label>
                    <input v-model="goalForm.name" type="text" placeholder="e.g. Emergency Fund" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                    <InputError :message="goalForm.errors.name" class="mt-1" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Type</label>
                        <select v-model="goalForm.type" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]">
                            <option value="savings">Savings goal</option>
                            <option value="emergency_fund">Emergency fund</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Target date</label>
                        <input v-model="goalForm.target_date" type="date" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Target (₹)</label>
                        <input v-model="goalForm.target" type="number" step="0.01" min="1" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                        <InputError :message="goalForm.errors.target" class="mt-1" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Saved so far (₹)</label>
                        <input v-model="goalForm.saved" type="number" step="0.01" min="0" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                    </div>
                </div>
                <DialogFooter>
                    <button type="submit" :disabled="goalForm.processing" class="w-full rounded-xl py-2.5 font-semibold text-white disabled:opacity-60" style="background: linear-gradient(135deg, #CC1D79 0%, #06B7AD 100%)">Save</button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Contribute -->
    <Dialog :open="contribTarget !== null" @update:open="(v) => { if (!v) contribTarget = null; }">
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle>Add money</DialogTitle>
                <DialogDescription>Contribute toward {{ contribTarget?.name }}.</DialogDescription>
            </DialogHeader>
            <form @submit.prevent="submitContrib">
                <label class="mb-1.5 block text-sm font-medium">Amount (₹)</label>
                <input v-model="contribForm.amount" type="number" step="0.01" min="0" autofocus class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                <InputError :message="contribForm.errors.amount" class="mt-1" />
                <DialogFooter class="mt-4 gap-2">
                    <button type="button" class="flex-1 rounded-xl border border-border py-2.5 font-semibold hover:bg-muted" @click="contribTarget = null">Cancel</button>
                    <button type="submit" :disabled="contribForm.processing" class="flex-1 rounded-xl py-2.5 font-semibold text-white disabled:opacity-60" style="background: linear-gradient(135deg, #CC1D79 0%, #06B7AD 100%)">Add</button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
