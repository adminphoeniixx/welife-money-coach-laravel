<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Check, Copy, GraduationCap, Plus, Trash2, UserPlus, Users, X } from '@lucide/vue';
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
    layout: { breadcrumbs: [{ title: 'Family', href: '/family' }] },
});

interface Member { id: number; name: string; email: string; role: string; is_you: boolean }
interface Invite { id: number; email: string; role: string; link: string }
interface Expense { id: number; category: string | null; description: string | null; amount: number; by: string | null; mine: boolean; date: string }
interface FamilyBudget { id: number; category: string; limit: number; spent: number; percent: number; exceeded: boolean }

const props = defineProps<{
    categories: string[];
    can_manage?: boolean;
    my_role?: string;
    household: { id: number; name: string; members: Member[]; invitations: Invite[] } | null;
    summary?: { income: number; expense: number; net: number; education: number };
    expenses?: Expense[];
    budgets?: FamilyBudget[];
}>();

const { fmt } = useCurrency();
const today = new Date().toISOString().slice(0, 10);
const roleLabel = (r: string) => ({ owner: 'Owner', partner: 'Partner', member: 'Member' }[r] ?? r);

// Create family
const createForm = useForm({ name: '' });
const createFamily = () => createForm.post('/family', { preserveScroll: true });

// Invite
const inviteForm = useForm({ email: '', role: 'partner' });
const invite = () => inviteForm.post('/family/invite', { preserveScroll: true, onSuccess: () => inviteForm.reset() });

const copied = ref<number | null>(null);
const copyLink = (inv: Invite) => {
    navigator.clipboard?.writeText(inv.link);
    copied.value = inv.id;
    setTimeout(() => (copied.value = null), 1500);
};

const cancelInvite = useForm({});
const removeInvite = (id: number) => cancelInvite.delete(`/family/invitations/${id}`, { preserveScroll: true });
const removeMemberForm = useForm({});
const removeMember = (id: number) => removeMemberForm.delete(`/family/members/${id}`, { preserveScroll: true });

const leaveForm = useForm({});
const leave = () => leaveForm.post('/family/leave');
const destroyForm = useForm({});
const deleteFamily = () => destroyForm.delete('/family');

// Shared expense
const expenseOpen = ref(false);
const expenseForm = useForm({ category: 'Groceries', amount: '', description: '', occurred_on: today });
const openExpense = () => {
    expenseForm.clearErrors();
    expenseForm.defaults({ category: 'Groceries', amount: '', description: '', occurred_on: today });
    expenseForm.reset();
    expenseOpen.value = true;
};
const submitExpense = () => expenseForm.post('/family/expenses', { preserveScroll: true, onSuccess: () => (expenseOpen.value = false) });
const delExpense = useForm({});
const removeExpense = (id: number) => delExpense.delete(`/family/expenses/${id}`, { preserveScroll: true });

// Family budget
const budgetOpen = ref(false);
const budgetForm = useForm({ category: '', limit: '' });
const openBudget = () => {
    budgetForm.clearErrors();
    budgetForm.defaults({ category: '', limit: '' });
    budgetForm.reset();
    budgetOpen.value = true;
};
const submitBudget = () => budgetForm.post('/family/budgets', { preserveScroll: true, onSuccess: () => (budgetOpen.value = false) });
const delBudget = useForm({});
const removeBudget = (id: number) => delBudget.delete(`/family/budgets/${id}`, { preserveScroll: true });
</script>

<template>
    <Head title="Family" />

    <!-- Empty state: create a family -->
    <div v-if="!household" class="flex flex-1 items-center justify-center p-6">
        <div class="w-full max-w-md rounded-2xl border border-border bg-card p-8 text-center">
            <div class="mx-auto grid h-16 w-16 place-items-center rounded-2xl text-white" style="background: linear-gradient(135deg, #CC1D79 0%, #06B7AD 100%)">
                <Users :size="30" />
            </div>
            <h1 class="mt-4 text-xl font-extrabold">Manage money as a family</h1>
            <p class="mx-auto mt-1 max-w-xs text-sm text-muted-foreground">
                Create a shared space for you and your partner — shared expenses, a family budget and children's education costs, all in one place.
            </p>
            <form class="mt-6 space-y-3 text-left" @submit.prevent="createFamily">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Family name</label>
                    <input v-model="createForm.name" type="text" placeholder="e.g. The Sharmas" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                    <InputError :message="createForm.errors.name" class="mt-1" />
                </div>
                <button type="submit" :disabled="createForm.processing" class="w-full rounded-xl py-2.5 font-semibold text-white disabled:opacity-60" style="background: linear-gradient(135deg, #CC1D79 0%, #06B7AD 100%)">
                    Create family
                </button>
            </form>
        </div>
    </div>

    <!-- Family dashboard -->
    <div v-else class="flex flex-1 flex-col gap-5 p-4 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="grid h-11 w-11 place-items-center rounded-xl text-white" style="background: linear-gradient(135deg, #CC1D79 0%, #06B7AD 100%)"><Users :size="22" /></div>
                <div>
                    <h1 class="text-lg font-extrabold leading-tight">{{ household.name }}</h1>
                    <p class="text-xs text-muted-foreground">You're the {{ roleLabel(my_role ?? 'member').toLowerCase() }} · {{ household.members.length }} members</p>
                </div>
            </div>
            <button v-if="!can_manage" class="rounded-xl border border-border px-3 py-2 text-sm font-semibold hover:bg-muted" @click="leave">Leave family</button>
            <button v-else class="rounded-xl border border-[#CC1D79]/30 px-3 py-2 text-sm font-semibold text-[#CC1D79] hover:bg-[#CC1D79]/10" @click="deleteFamily">Delete family</button>
        </div>

        <!-- Summary -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-border bg-card p-5"><div class="text-xs font-semibold text-muted-foreground">Family income</div><div class="mt-2 text-xl font-extrabold text-[#10B981]">{{ fmt(summary!.income) }}</div></div>
            <div class="rounded-2xl border border-border bg-card p-5"><div class="text-xs font-semibold text-muted-foreground">Shared expenses</div><div class="mt-2 text-xl font-extrabold text-[#CC1D79]">{{ fmt(summary!.expense) }}</div></div>
            <div class="rounded-2xl border border-border bg-card p-5"><div class="text-xs font-semibold text-muted-foreground">Net this month</div><div class="mt-2 text-xl font-extrabold">{{ fmt(summary!.net) }}</div></div>
            <div class="rounded-2xl border border-border bg-card p-5">
                <div class="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground"><GraduationCap :size="14" /> Children's education</div>
                <div class="mt-2 text-xl font-extrabold text-[#7B2FF7]">{{ fmt(summary!.education) }}</div>
            </div>
        </div>

        <div class="grid gap-5 lg:grid-cols-[1fr_1.3fr]">
            <!-- Members & invites -->
            <div class="rounded-2xl border border-border bg-card p-5">
                <h2 class="font-bold">Members</h2>
                <div class="mt-3 space-y-2">
                    <div v-for="m in household.members" :key="m.id" class="flex items-center justify-between rounded-xl border border-border p-3">
                        <div class="flex items-center gap-2.5">
                            <span class="grid h-9 w-9 place-items-center rounded-full bg-[#CC1D79]/12 text-sm font-bold text-[#CC1D79]">{{ m.name.charAt(0) }}</span>
                            <div>
                                <div class="text-sm font-semibold">{{ m.name }} <span v-if="m.is_you" class="text-xs text-muted-foreground">(you)</span></div>
                                <div class="text-xs text-muted-foreground">{{ m.email }}</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-muted px-2 py-0.5 text-[11px] font-bold uppercase text-muted-foreground">{{ roleLabel(m.role) }}</span>
                            <button v-if="can_manage && m.role !== 'owner'" class="grid h-7 w-7 place-items-center rounded-lg text-muted-foreground hover:bg-[#CC1D79]/10 hover:text-[#CC1D79]" @click="removeMember(m.id)"><X :size="14" /></button>
                        </div>
                    </div>
                </div>

                <!-- Pending invites -->
                <div v-if="household.invitations.length" class="mt-4">
                    <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Pending invites</div>
                    <div v-for="inv in household.invitations" :key="inv.id" class="mb-2 flex items-center justify-between rounded-xl border border-dashed border-border p-3">
                        <div class="min-w-0">
                            <div class="truncate text-sm font-semibold">{{ inv.email }}</div>
                            <div class="text-xs text-muted-foreground">{{ roleLabel(inv.role) }} · invite pending</div>
                        </div>
                        <div class="flex items-center gap-1">
                            <button class="flex items-center gap-1 rounded-lg bg-[#06B7AD]/10 px-2.5 py-1 text-xs font-semibold text-[#06B7AD]" @click="copyLink(inv)">
                                <component :is="copied === inv.id ? Check : Copy" :size="13" /> {{ copied === inv.id ? 'Copied' : 'Copy link' }}
                            </button>
                            <button v-if="can_manage" class="grid h-7 w-7 place-items-center rounded-lg text-muted-foreground hover:bg-muted" @click="removeInvite(inv.id)"><Trash2 :size="13" /></button>
                        </div>
                    </div>
                </div>

                <!-- Invite form -->
                <form v-if="can_manage" class="mt-4 border-t border-border pt-4" @submit.prevent="invite">
                    <div class="mb-2 flex items-center gap-2 text-sm font-semibold"><UserPlus :size="15" /> Invite someone</div>
                    <div class="flex gap-2">
                        <input v-model="inviteForm.email" type="email" placeholder="their@email.com" class="flex-1 rounded-xl border border-input bg-background px-3 py-2 text-sm outline-none focus:border-[#CC1D79]" />
                        <select v-model="inviteForm.role" class="rounded-xl border border-input bg-background px-2 py-2 text-sm outline-none focus:border-[#CC1D79]">
                            <option value="partner">Partner</option>
                            <option value="member">Member</option>
                        </select>
                        <button type="submit" :disabled="inviteForm.processing" class="rounded-xl px-3 py-2 text-sm font-semibold text-white disabled:opacity-60" style="background: linear-gradient(135deg, #CC1D79 0%, #06B7AD 100%)">Invite</button>
                    </div>
                    <InputError :message="inviteForm.errors.email" class="mt-1" />
                    <p class="mt-1.5 text-xs text-muted-foreground">We generate a private link — share it with them via WhatsApp or message.</p>
                </form>
            </div>

            <!-- Shared expenses -->
            <div class="rounded-2xl border border-border bg-card p-5">
                <div class="flex items-center justify-between">
                    <h2 class="font-bold">Shared expenses</h2>
                    <button class="flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-sm font-semibold text-white" style="background: linear-gradient(135deg, #CC1D79 0%, #06B7AD 100%)" @click="openExpense"><Plus :size="15" /> Add</button>
                </div>
                <div class="mt-3 divide-y divide-border">
                    <div v-for="e in expenses" :key="e.id" class="group flex items-center justify-between py-3">
                        <div>
                            <div class="text-sm font-semibold">{{ e.description ?? e.category }}</div>
                            <div class="text-xs text-muted-foreground">{{ e.category }} · by {{ e.by }} · {{ e.date }}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="font-bold tabular-nums text-[#CC1D79]">{{ fmt(e.amount) }}</span>
                            <button v-if="e.mine || can_manage" class="grid h-7 w-7 place-items-center rounded-lg text-muted-foreground opacity-0 group-hover:opacity-100 hover:bg-[#CC1D79]/10 hover:text-[#CC1D79]" @click="removeExpense(e.id)"><Trash2 :size="13" /></button>
                        </div>
                    </div>
                    <div v-if="!expenses || !expenses.length" class="py-8 text-center text-sm text-muted-foreground">No shared expenses yet this month.</div>
                </div>
            </div>
        </div>

        <!-- Family budgets -->
        <div class="rounded-2xl border border-border bg-card p-5">
            <div class="flex items-center justify-between">
                <h2 class="font-bold">Family budget</h2>
                <button v-if="can_manage" class="flex items-center gap-1.5 rounded-xl border border-border px-3 py-1.5 text-sm font-semibold hover:bg-muted" @click="openBudget"><Plus :size="15" /> Budget</button>
            </div>
            <div v-if="budgets && budgets.length" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="b in budgets" :key="b.id" class="group">
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-semibold">{{ b.category }}</span>
                        <div class="flex items-center gap-2">
                            <span :class="b.exceeded ? 'font-bold text-[#CC1D79]' : 'text-muted-foreground'">{{ fmt(b.spent) }} / {{ fmt(b.limit) }}</span>
                            <button v-if="can_manage" class="text-muted-foreground opacity-0 group-hover:opacity-100 hover:text-[#CC1D79]" @click="removeBudget(b.id)"><Trash2 :size="13" /></button>
                        </div>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-muted">
                        <div class="h-full rounded-full" :style="{ width: Math.min(100, b.percent) + '%', background: b.exceeded ? '#CC1D79' : b.percent > 80 ? '#F5A524' : '#06B7AD' }" />
                    </div>
                </div>
            </div>
            <p v-else class="mt-3 text-sm text-muted-foreground">No family budgets yet.</p>
        </div>
    </div>

    <!-- Shared expense dialog -->
    <Dialog v-model:open="expenseOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Add shared expense</DialogTitle>
                <DialogDescription>Visible to everyone in your family.</DialogDescription>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="submitExpense">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Category</label>
                        <input v-model="expenseForm.category" list="fam-cats" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                        <datalist id="fam-cats"><option v-for="c in categories" :key="c" :value="c" /></datalist>
                        <InputError :message="expenseForm.errors.category" class="mt-1" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Amount (₹)</label>
                        <input v-model="expenseForm.amount" type="number" step="0.01" min="0" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                        <InputError :message="expenseForm.errors.amount" class="mt-1" />
                    </div>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Description</label>
                    <input v-model="expenseForm.description" type="text" placeholder="e.g. School fees" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Date</label>
                    <input v-model="expenseForm.occurred_on" type="date" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                </div>
                <DialogFooter>
                    <button type="submit" :disabled="expenseForm.processing" class="w-full rounded-xl py-2.5 font-semibold text-white disabled:opacity-60" style="background: linear-gradient(135deg, #CC1D79 0%, #06B7AD 100%)">Add expense</button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Family budget dialog -->
    <Dialog v-model:open="budgetOpen">
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle>Family budget</DialogTitle>
                <DialogDescription>A shared monthly limit for a category.</DialogDescription>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="submitBudget">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Category</label>
                    <input v-model="budgetForm.category" list="fam-cats" placeholder="e.g. Education" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                    <InputError :message="budgetForm.errors.category" class="mt-1" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Monthly limit (₹)</label>
                    <input v-model="budgetForm.limit" type="number" step="0.01" min="0" class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                    <InputError :message="budgetForm.errors.limit" class="mt-1" />
                </div>
                <DialogFooter>
                    <button type="submit" :disabled="budgetForm.processing" class="w-full rounded-xl py-2.5 font-semibold text-white disabled:opacity-60" style="background: linear-gradient(135deg, #CC1D79 0%, #06B7AD 100%)">Set budget</button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
