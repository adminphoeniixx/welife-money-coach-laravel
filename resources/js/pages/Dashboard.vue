<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowDownRight,
    ArrowUpRight,
    CalendarClock,
    CreditCard,
    Flame,
    PiggyBank,
    Scale,
    Sparkles,
    Target,
    TrendingDown,
    TrendingUp,
    Wallet,
} from '@lucide/vue';
import { computed } from 'vue';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

interface Factor {
    label: string;
    points: number;
    max: number;
}
interface Health {
    score: number;
    status: string;
    tone: string;
    factors: Factor[];
}
interface Kpis {
    net_worth: number;
    assets: number;
    liabilities: number;
    income: number;
    expense: number;
    savings: number;
    savings_rate: number;
    total_debt: number;
    monthly_emi: number;
    emi_to_income: number;
}
interface Priority {
    name: string;
    institution: string | null;
    kind: string;
    interest_rate: number;
    balance: number;
    monthly_interest: number;
    due_in_days: number | null;
    headline: string;
    reason: string;
}
interface DebtFree {
    months: number;
    label: string;
    date: string | null;
    interest_left: number;
    progress: number;
}
interface Fund {
    name: string;
    target: number;
    saved: number;
    progress: number;
}
interface BudgetRow {
    category: string;
    spent: number;
    limit: number;
    percent: number;
    exceeded: boolean;
}
interface Upcoming {
    name: string;
    kind: string;
    category: string | null;
    amount: number;
    due_date: string;
    days: number;
    when: string;
    overdue: boolean;
}
interface Slice {
    category: string;
    amount: number;
    percent: number;
}
interface TrendRow {
    label: string;
    income: number;
    expense: number;
}
interface Tip {
    tone: string;
    icon: string;
    text: string;
}
interface DebtRow {
    id: number;
    name: string;
    institution: string | null;
    kind: string;
    balance: number;
    interest_rate: number;
    emi: number;
    utilisation: number | null;
    limit: number | null;
}

const props = defineProps<{
    user: { name: string };
    health: Health;
    kpis: Kpis;
    priority: Priority | null;
    debt_free: DebtFree;
    emergency_fund: Fund | null;
    goals: Fund[];
    budgets: BudgetRow[];
    upcoming: Upcoming[];
    spending: { total: number; slices: Slice[] };
    trend: TrendRow[];
    tips: Tip[];
    debts: DebtRow[];
}>();

const currency = new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
    maximumFractionDigits: 0,
});
const fmt = (n: number) => currency.format(n);
const compact = new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
    notation: 'compact',
    maximumFractionDigits: 1,
});
const fmtc = (n: number) => compact.format(n);

const TONES: Record<string, string> = {
    green: '#10B981',
    teal: '#06B7AD',
    amber: '#F5A524',
    red: '#CC1D79',
};
const toneColor = (t: string) => TONES[t] ?? '#7a8496';

const PALETTE = ['#CC1D79', '#7B2FF7', '#06B7AD', '#F5A524', '#3B82F6', '#10B981', '#EC4899', '#94A3B8'];

const firstName = computed(() => props.user.name.split(' ')[0]);
const greeting = computed(() => {
    const h = new Date().getHours();
    if (h < 12) return 'Good morning';
    if (h < 17) return 'Good afternoon';
    return 'Good evening';
});

// Health gauge geometry.
const R = 54;
const CIRC = 2 * Math.PI * R;
const dash = computed(() => (props.health.score / 100) * CIRC);

// Spending donut as a conic gradient.
const donut = computed(() => {
    let acc = 0;
    const stops = props.spending.slices.map((s, i) => {
        const from = acc;
        acc += s.percent;
        return `${PALETTE[i % PALETTE.length]} ${from}% ${acc}%`;
    });
    return `conic-gradient(${stops.join(', ')})`;
});

// Trend chart scaling.
const trendMax = computed(() =>
    Math.max(1, ...props.trend.map((m) => Math.max(m.income, m.expense))),
);

const tipIcon = (name: string) => {
    const map: Record<string, unknown> = {
        alert: AlertTriangle,
        'credit-card': CreditCard,
        'trending-up': TrendingUp,
        'trending-down': TrendingDown,
        'piggy-bank': PiggyBank,
        scale: Scale,
    };
    return map[name] ?? Sparkles;
};
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-1 flex-col gap-5 p-4 sm:p-6">
        <!-- Greeting + health hero -->
        <section
            class="grid gap-5 lg:grid-cols-[1.4fr_1fr]"
        >
            <div
                class="relative flex flex-col justify-between overflow-hidden rounded-2xl p-6 text-white"
                style="background: linear-gradient(135deg, #CC1D79 0%, #06B7AD 100%)"
            >
                <div
                    class="pointer-events-none absolute -right-16 -bottom-20 h-64 w-64 rounded-full bg-white/10"
                />
                <div class="relative">
                    <p class="text-sm font-medium text-white/80">{{ greeting }},</p>
                    <h1 class="mt-1 text-2xl font-extrabold tracking-tight">{{ firstName }} 👋</h1>
                    <p class="mt-2 max-w-md text-sm text-white/85">
                        Here's your money at a glance. Your coach found
                        <strong>{{ tips.length }}</strong> things worth your attention today.
                    </p>
                </div>
                <div class="relative mt-6 flex flex-wrap gap-6">
                    <div>
                        <div class="text-xs font-medium text-white/70">Net worth</div>
                        <div class="text-2xl font-extrabold">{{ fmt(kpis.net_worth) }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-white/70">Saved this month</div>
                        <div class="text-2xl font-extrabold">{{ fmt(kpis.savings) }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-white/70">Debt-free in</div>
                        <div class="text-2xl font-extrabold">{{ debt_free.label }}</div>
                    </div>
                </div>
            </div>

            <!-- Financial Health Score -->
            <div class="flex items-center gap-5 rounded-2xl border border-border bg-card p-6">
                <div class="relative flex-none">
                    <svg width="128" height="128" viewBox="0 0 128 128" class="-rotate-90">
                        <circle cx="64" cy="64" :r="R" fill="none" stroke="var(--muted)" stroke-width="12" />
                        <circle
                            cx="64" cy="64" :r="R" fill="none"
                            :stroke="toneColor(health.tone)" stroke-width="12" stroke-linecap="round"
                            :stroke-dasharray="`${dash} ${CIRC}`"
                        />
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-3xl font-extrabold leading-none">{{ health.score }}</span>
                        <span class="text-[11px] font-medium text-muted-foreground">/ 100</span>
                    </div>
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        Financial health
                    </div>
                    <div
                        class="mt-1 inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-sm font-bold"
                        :style="{ background: toneColor(health.tone) + '22', color: toneColor(health.tone) }"
                    >
                        <span
                            class="h-2 w-2 rounded-full"
                            :style="{ background: toneColor(health.tone) }"
                        />
                        {{ health.status }}
                    </div>
                    <div class="mt-3 space-y-1.5">
                        <div
                            v-for="f in health.factors"
                            :key="f.label"
                            class="flex items-center gap-2"
                        >
                            <span class="w-28 flex-none truncate text-xs text-muted-foreground">{{ f.label }}</span>
                            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
                                <div
                                    class="h-full rounded-full"
                                    :style="{ width: (f.points / f.max) * 100 + '%', background: toneColor(health.tone) }"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- KPI row -->
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-border bg-card p-5">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-muted-foreground">Total assets</span>
                    <span class="grid h-9 w-9 place-items-center rounded-xl bg-[#06B7AD]/15 text-[#06B7AD]">
                        <Wallet class="h-4.5 w-4.5" :size="18" />
                    </span>
                </div>
                <div class="mt-3 text-2xl font-extrabold tracking-tight">{{ fmt(kpis.assets) }}</div>
                <div class="mt-1 text-xs text-muted-foreground">Liabilities {{ fmt(kpis.liabilities) }}</div>
            </div>

            <div class="rounded-2xl border border-border bg-card p-5">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-muted-foreground">Income · this month</span>
                    <span class="grid h-9 w-9 place-items-center rounded-xl bg-[#10B981]/15 text-[#10B981]">
                        <ArrowUpRight :size="18" />
                    </span>
                </div>
                <div class="mt-3 text-2xl font-extrabold tracking-tight">{{ fmt(kpis.income) }}</div>
                <div class="mt-1 text-xs text-muted-foreground">Saving {{ kpis.savings_rate }}% of it</div>
            </div>

            <div class="rounded-2xl border border-border bg-card p-5">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-muted-foreground">Expenses · this month</span>
                    <span class="grid h-9 w-9 place-items-center rounded-xl bg-[#CC1D79]/15 text-[#CC1D79]">
                        <ArrowDownRight :size="18" />
                    </span>
                </div>
                <div class="mt-3 text-2xl font-extrabold tracking-tight">{{ fmt(kpis.expense) }}</div>
                <div class="mt-1 text-xs text-muted-foreground">{{ spending.slices.length }} categories</div>
            </div>

            <div class="rounded-2xl border border-border bg-card p-5">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-muted-foreground">Total debt</span>
                    <span class="grid h-9 w-9 place-items-center rounded-xl bg-[#7B2FF7]/15 text-[#7B2FF7]">
                        <Scale :size="18" />
                    </span>
                </div>
                <div class="mt-3 text-2xl font-extrabold tracking-tight text-[#CC1D79]">{{ fmt(kpis.total_debt) }}</div>
                <div class="mt-1 text-xs text-muted-foreground">
                    EMIs {{ fmt(kpis.monthly_emi) }}/mo · {{ kpis.emi_to_income }}% of income
                </div>
            </div>
        </section>

        <!-- Priority payment + upcoming due -->
        <section class="grid gap-5 lg:grid-cols-[1fr_1.2fr]">
            <div
                v-if="priority"
                class="relative flex flex-col overflow-hidden rounded-2xl p-6 text-white"
                style="background: linear-gradient(135deg, #CC1D79 0%, #a81563 100%)"
            >
                <div class="flex items-center justify-between">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-2.5 py-1 text-xs font-bold">
                        <Flame :size="13" /> Priority payment
                    </span>
                    <span v-if="priority.due_in_days !== null" class="text-xs font-medium text-white/80">
                        {{ priority.due_in_days <= 0 ? 'Due now' : 'Due in ' + priority.due_in_days + ' days' }}
                    </span>
                </div>
                <div class="mt-4 text-xl font-extrabold leading-snug">{{ priority.headline }}</div>
                <p class="mt-2 text-sm text-white/85">{{ priority.reason }}</p>
                <div class="mt-4 flex gap-3">
                    <div class="rounded-xl bg-white/15 px-3 py-2">
                        <div class="text-[11px] text-white/75">Balance</div>
                        <div class="font-bold">{{ fmt(priority.balance) }}</div>
                    </div>
                    <div class="rounded-xl bg-white/15 px-3 py-2">
                        <div class="text-[11px] text-white/75">Interest / month</div>
                        <div class="font-bold">{{ fmt(priority.monthly_interest) }}</div>
                    </div>
                </div>
            </div>
            <div v-else class="grid place-items-center rounded-2xl border border-border bg-card p-6 text-center">
                <div>
                    <div class="text-lg font-bold text-[#06B7AD]">You're debt-free 🎉</div>
                    <p class="mt-1 text-sm text-muted-foreground">No priority payments right now.</p>
                </div>
            </div>

            <div class="rounded-2xl border border-border bg-card">
                <div class="flex items-center justify-between px-5 pt-5">
                    <h2 class="font-bold">Upcoming due</h2>
                    <CalendarClock class="text-muted-foreground" :size="18" />
                </div>
                <ul class="mt-2 divide-y divide-border">
                    <li
                        v-for="(b, i) in upcoming"
                        :key="i"
                        class="flex items-center justify-between px-5 py-3"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                class="grid h-9 w-9 flex-none place-items-center rounded-xl text-white"
                                :style="{ background: PALETTE[i % PALETTE.length] }"
                            >
                                <CreditCard :size="16" />
                            </span>
                            <div class="min-w-0">
                                <div class="truncate text-sm font-semibold">{{ b.name }}</div>
                                <div
                                    class="text-xs"
                                    :class="b.overdue ? 'font-semibold text-[#CC1D79]' : 'text-muted-foreground'"
                                >
                                    {{ b.when }}
                                </div>
                            </div>
                        </div>
                        <div class="font-bold tabular-nums" :class="b.overdue ? 'text-[#CC1D79]' : ''">
                            {{ fmt(b.amount) }}
                        </div>
                    </li>
                    <li v-if="!upcoming.length" class="px-5 py-6 text-center text-sm text-muted-foreground">
                        Nothing due soon. 🎉
                    </li>
                </ul>
            </div>
        </section>

        <!-- AI coaching tips -->
        <section v-if="tips.length" class="rounded-2xl border border-border bg-card p-5">
            <div class="flex items-center gap-2">
                <span class="grid h-7 w-7 place-items-center rounded-lg bg-[#CC1D79]/12 text-[#CC1D79]">
                    <Sparkles :size="15" />
                </span>
                <h2 class="font-bold">Coach insights</h2>
            </div>
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                <div
                    v-for="(t, i) in tips"
                    :key="i"
                    class="flex gap-3 rounded-xl border p-3.5"
                    :style="{ borderColor: toneColor(t.tone) + '33', background: toneColor(t.tone) + '0d' }"
                >
                    <span
                        class="grid h-8 w-8 flex-none place-items-center rounded-lg"
                        :style="{ background: toneColor(t.tone) + '22', color: toneColor(t.tone) }"
                    >
                        <component :is="tipIcon(t.icon)" :size="16" />
                    </span>
                    <p class="text-sm leading-snug">{{ t.text }}</p>
                </div>
            </div>
        </section>

        <!-- Emergency fund + goals + debt-free -->
        <section class="grid gap-5 lg:grid-cols-3">
            <div v-if="emergency_fund" class="rounded-2xl border border-border bg-card p-5">
                <div class="flex items-center gap-2">
                    <PiggyBank class="text-[#06B7AD]" :size="18" />
                    <h2 class="font-bold">{{ emergency_fund.name }}</h2>
                </div>
                <div class="mt-4 flex items-end justify-between">
                    <div class="text-2xl font-extrabold">{{ fmt(emergency_fund.saved) }}</div>
                    <div class="text-sm text-muted-foreground">of {{ fmt(emergency_fund.target) }}</div>
                </div>
                <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-muted">
                    <div
                        class="h-full rounded-full"
                        :style="{ width: emergency_fund.progress + '%', background: 'linear-gradient(90deg,#CC1D79,#06B7AD)' }"
                    />
                </div>
                <div class="mt-1.5 text-xs font-semibold text-[#06B7AD]">{{ emergency_fund.progress }}% funded</div>
            </div>

            <div v-for="g in goals" :key="g.name" class="rounded-2xl border border-border bg-card p-5">
                <div class="flex items-center gap-2">
                    <Target class="text-[#7B2FF7]" :size="18" />
                    <h2 class="font-bold">{{ g.name }}</h2>
                </div>
                <div class="mt-4 flex items-end justify-between">
                    <div class="text-2xl font-extrabold">{{ fmt(g.saved) }}</div>
                    <div class="text-sm text-muted-foreground">of {{ fmt(g.target) }}</div>
                </div>
                <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-muted">
                    <div
                        class="h-full rounded-full"
                        :style="{ width: g.progress + '%', background: 'linear-gradient(90deg,#7B2FF7,#3B82F6)' }"
                    />
                </div>
                <div class="mt-1.5 text-xs font-semibold text-[#7B2FF7]">{{ g.progress }}% saved</div>
            </div>

            <!-- Debt-free countdown -->
            <div
                class="flex flex-col justify-between rounded-2xl border border-border bg-card p-5"
            >
                <div class="flex items-center gap-2">
                    <TrendingDown class="text-[#CC1D79]" :size="18" />
                    <h2 class="font-bold">Debt-free countdown</h2>
                </div>
                <div class="mt-3">
                    <div class="text-3xl font-extrabold tracking-tight">{{ debt_free.label }}</div>
                    <div class="text-sm text-muted-foreground">
                        On track for <strong>{{ debt_free.date ?? '—' }}</strong>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="mb-1 flex justify-between text-xs text-muted-foreground">
                        <span>{{ debt_free.progress }}% paid off</span>
                        <span>Interest left {{ fmtc(debt_free.interest_left) }}</span>
                    </div>
                    <div class="h-2.5 overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full rounded-full"
                            :style="{ width: debt_free.progress + '%', background: 'linear-gradient(90deg,#CC1D79,#06B7AD)' }"
                        />
                    </div>
                </div>
            </div>
        </section>

        <!-- Budgets -->
        <section v-if="budgets.length" class="rounded-2xl border border-border bg-card p-5">
            <h2 class="font-bold">Budget this month</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <div v-for="b in budgets" :key="b.category">
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-semibold">{{ b.category }}</span>
                        <span
                            class="tabular-nums"
                            :class="b.exceeded ? 'font-bold text-[#CC1D79]' : 'text-muted-foreground'"
                        >
                            {{ fmt(b.spent) }} / {{ fmt(b.limit) }}
                        </span>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full rounded-full transition-all"
                            :style="{
                                width: Math.min(100, b.percent) + '%',
                                background: b.exceeded ? '#CC1D79' : b.percent > 80 ? '#F5A524' : '#06B7AD',
                            }"
                        />
                    </div>
                    <div class="mt-1 text-xs" :class="b.exceeded ? 'font-semibold text-[#CC1D79]' : 'text-muted-foreground'">
                        {{ b.exceeded ? 'Budget exceeded' : b.percent + '% used' }}
                    </div>
                </div>
            </div>
        </section>

        <!-- Spending breakdown + trend -->
        <section class="grid gap-5 lg:grid-cols-2">
            <div class="rounded-2xl border border-border bg-card p-5">
                <h2 class="font-bold">Spending breakdown</h2>
                <div class="mt-4 flex items-center gap-6">
                    <div class="relative grid h-36 w-36 flex-none place-items-center rounded-full" :style="{ background: donut }">
                        <div class="grid h-24 w-24 place-items-center rounded-full bg-card text-center">
                            <div>
                                <div class="text-base font-extrabold">{{ fmtc(spending.total) }}</div>
                                <div class="text-[11px] text-muted-foreground">this month</div>
                            </div>
                        </div>
                    </div>
                    <ul class="flex-1 space-y-2">
                        <li
                            v-for="(s, i) in spending.slices.slice(0, 6)"
                            :key="s.category"
                            class="flex items-center gap-2 text-sm"
                        >
                            <span class="h-2.5 w-2.5 flex-none rounded-sm" :style="{ background: PALETTE[i % PALETTE.length] }" />
                            <span class="truncate">{{ s.category }}</span>
                            <span class="ml-auto font-semibold tabular-nums">{{ s.percent }}%</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="rounded-2xl border border-border bg-card p-5">
                <div class="flex items-center justify-between">
                    <h2 class="font-bold">Income vs expenses</h2>
                    <div class="flex gap-3 text-xs">
                        <span class="flex items-center gap-1.5">
                            <span class="h-2.5 w-2.5 rounded-sm bg-[#06B7AD]" /> Income
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="h-2.5 w-2.5 rounded-sm bg-[#CC1D79]" /> Expense
                        </span>
                    </div>
                </div>
                <div class="mt-6 flex h-44 items-end gap-3">
                    <div v-for="m in trend" :key="m.label" class="flex flex-1 flex-col items-center gap-2">
                        <div class="flex h-full w-full items-end justify-center gap-1">
                            <div
                                class="w-1/2 rounded-t bg-[#06B7AD] transition-all"
                                :style="{ height: (m.income / trendMax) * 100 + '%' }"
                                :title="fmt(m.income)"
                            />
                            <div
                                class="w-1/2 rounded-t bg-[#CC1D79] transition-all"
                                :style="{ height: (m.expense / trendMax) * 100 + '%' }"
                                :title="fmt(m.expense)"
                            />
                        </div>
                        <span class="text-xs text-muted-foreground">{{ m.label }}</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Debt list -->
        <section v-if="debts.length" class="rounded-2xl border border-border bg-card p-5">
            <h2 class="font-bold">Your debts · highest interest first</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div
                    v-for="(d, i) in debts"
                    :key="d.id"
                    class="rounded-xl border border-border p-4"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span
                                class="grid h-10 w-10 flex-none place-items-center rounded-xl text-white"
                                :style="{ background: PALETTE[i % PALETTE.length] }"
                            >
                                <CreditCard v-if="d.kind === 'credit_card'" :size="18" />
                                <Scale v-else :size="18" />
                            </span>
                            <div>
                                <div class="font-semibold">{{ d.name }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ d.interest_rate }}% APR · EMI {{ fmt(d.emi) }}
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="font-bold text-[#CC1D79]">{{ fmt(d.balance) }}</div>
                            <div v-if="d.utilisation !== null" class="text-xs text-muted-foreground">
                                {{ d.utilisation }}% used
                            </div>
                        </div>
                    </div>
                    <div v-if="d.utilisation !== null" class="mt-3 h-2 overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full rounded-full"
                            :style="{ width: Math.min(100, d.utilisation) + '%', background: d.utilisation > 80 ? '#CC1D79' : '#06B7AD' }"
                        />
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
