<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CalendarCheck, PiggyBank, TrendingDown } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useDebounceFn } from '@vueuse/core';
import { useCurrency } from '@/composables/useCurrency';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Debts', href: '/debts' }, { title: 'Debt Coach', href: '/coach' }] },
});

interface Stat { months: number; label: string; date: string | null; interest: number }
interface OrderRow { position: number; name: string; kind: string; balance: number; interest_rate: number; emi: number; focus: boolean }

const props = defineProps<{
    plan: {
        strategy: string;
        extra: number;
        summary: { total: number; monthly_emi: number; progress: number; avg_apr: number };
        base: Stat;
        projected: Stat;
        interest_saved: number;
        months_saved: number;
        order: OrderRow[];
    };
}>();

const { fmt, fmtc } = useCurrency();
const PALETTE = ['#CC1D79', '#7B2FF7', '#06B7AD', '#F5A524', '#3B82F6', '#10B981'];

const strategy = ref(props.plan.strategy);
const extra = ref(props.plan.extra);

const reload = useDebounceFn(() => {
    router.get('/coach', { strategy: strategy.value, extra: extra.value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}, 250);

watch(strategy, () => reload());
const onSlide = () => reload();

const strategyBlurb = computed(() =>
    strategy.value === 'avalanche'
        ? 'Avalanche pays the highest-interest debt first — mathematically the cheapest way out.'
        : 'Snowball clears the smallest balance first — quick wins to keep you motivated.',
);
</script>

<template>
    <Head title="Debt Coach" />

    <div class="flex flex-1 flex-col gap-5 p-4 sm:p-6">
        <div>
            <h1 class="text-lg font-extrabold">Debt Coach</h1>
            <p class="text-sm text-muted-foreground">Compare strategies and see how extra payments get you debt-free sooner.</p>
        </div>

        <div class="grid gap-5 lg:grid-cols-[1fr_1.3fr]">
            <!-- Strategy + progress -->
            <div class="rounded-2xl border border-border bg-card p-6 text-center">
                <div class="inline-flex w-full gap-1 rounded-xl bg-muted p-1">
                    <button class="flex-1 rounded-lg py-1.5 text-sm font-semibold" :class="strategy === 'avalanche' ? 'bg-card shadow-sm' : 'text-muted-foreground'" @click="strategy = 'avalanche'">Avalanche</button>
                    <button class="flex-1 rounded-lg py-1.5 text-sm font-semibold" :class="strategy === 'snowball' ? 'bg-card shadow-sm' : 'text-muted-foreground'" @click="strategy = 'snowball'">Snowball</button>
                </div>

                <div class="relative mx-auto mt-6 grid h-36 w-36 place-items-center">
                    <svg width="144" height="144" viewBox="0 0 144 144" class="-rotate-90">
                        <circle cx="72" cy="72" r="60" fill="none" stroke="var(--muted)" stroke-width="14" />
                        <circle cx="72" cy="72" r="60" fill="none" stroke="#06B7AD" stroke-width="14" stroke-linecap="round"
                            :stroke-dasharray="`${(plan.summary.progress / 100) * 2 * Math.PI * 60} ${2 * Math.PI * 60}`" />
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-2xl font-extrabold">{{ plan.summary.progress }}%</span>
                        <span class="text-[11px] text-muted-foreground">paid off</span>
                    </div>
                </div>

                <div class="mt-4 text-lg font-extrabold">Debt-free by {{ plan.base.date ?? '—' }}</div>
                <p class="mx-auto mt-1 max-w-xs text-sm text-muted-foreground">{{ strategyBlurb }}</p>

                <div class="mt-5 grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-muted/60 p-3 text-left">
                        <div class="text-xs text-muted-foreground">Total debt</div>
                        <div class="font-bold text-[#CC1D79]">{{ fmt(plan.summary.total) }}</div>
                    </div>
                    <div class="rounded-xl bg-muted/60 p-3 text-left">
                        <div class="text-xs text-muted-foreground">Avg APR</div>
                        <div class="font-bold">{{ plan.summary.avg_apr }}%</div>
                    </div>
                </div>
            </div>

            <!-- Payoff order + simulator -->
            <div class="rounded-2xl border border-border bg-card">
                <div class="px-5 pt-5 font-bold">Pay in this order</div>
                <div class="mt-2 divide-y divide-border px-5">
                    <div v-for="(d, i) in plan.order" :key="d.position" class="flex items-center gap-3 py-2.5">
                        <span class="grid h-7 w-7 flex-none place-items-center rounded-lg text-xs font-bold text-white" :style="{ background: PALETTE[i % PALETTE.length] }">{{ d.position }}</span>
                        <span class="flex-1 font-semibold">{{ d.name }}
                            <span v-if="d.focus" class="ml-1 rounded-full bg-[#CC1D79]/12 px-1.5 py-0.5 text-[10px] font-bold text-[#CC1D79]">Focus ⚡</span>
                        </span>
                        <span class="text-sm text-muted-foreground">{{ d.interest_rate }}%</span>
                        <span class="w-24 text-right font-bold tabular-nums">{{ fmt(d.balance) }}</span>
                    </div>
                </div>

                <div class="mt-3 border-t border-border p-5">
                    <div class="flex items-center justify-between">
                        <span class="font-bold">Extra payment simulator</span>
                        <span class="font-extrabold text-[#CC1D79]">+{{ fmt(extra) }}/mo</span>
                    </div>
                    <input v-model.number="extra" type="range" min="0" max="20000" step="500" class="mt-3 w-full accent-[#CC1D79]" @input="onSlide" />
                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div class="rounded-xl bg-[#06B7AD]/10 p-3">
                            <div class="flex items-center gap-1.5 text-xs text-muted-foreground"><CalendarCheck :size="13" /> New debt-free date</div>
                            <div class="mt-0.5 font-extrabold text-[#06B7AD]">{{ plan.projected.date ?? '—' }}</div>
                            <div class="text-xs text-muted-foreground">{{ plan.projected.label }}</div>
                        </div>
                        <div class="rounded-xl bg-[#06B7AD]/10 p-3">
                            <div class="flex items-center gap-1.5 text-xs text-muted-foreground"><PiggyBank :size="13" /> Interest saved</div>
                            <div class="mt-0.5 font-extrabold text-[#06B7AD]">{{ fmtc(plan.interest_saved) }}</div>
                            <div v-if="plan.months_saved > 0" class="text-xs text-muted-foreground">{{ plan.months_saved }} months sooner</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Base vs projected -->
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-border bg-card p-5">
                <div class="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground"><TrendingDown :size="14" /> Paying minimums</div>
                <div class="mt-2 text-xl font-extrabold">{{ plan.base.label }}</div>
                <div class="text-sm text-muted-foreground">Interest {{ fmt(plan.base.interest) }}</div>
            </div>
            <div class="rounded-2xl border border-border bg-card p-5">
                <div class="text-xs font-semibold text-muted-foreground">With +{{ fmt(extra) }}/mo</div>
                <div class="mt-2 text-xl font-extrabold text-[#06B7AD]">{{ plan.projected.label }}</div>
                <div class="text-sm text-muted-foreground">Interest {{ fmt(plan.projected.interest) }}</div>
            </div>
            <div class="rounded-2xl border border-border bg-card p-5" style="background: linear-gradient(135deg, rgba(204,29,121,.08), rgba(6,183,173,.08))">
                <div class="text-xs font-semibold text-muted-foreground">You save</div>
                <div class="mt-2 text-xl font-extrabold text-[#CC1D79]">{{ fmt(plan.interest_saved) }}</div>
                <div class="text-sm text-muted-foreground">{{ plan.months_saved }} months earlier</div>
            </div>
        </div>
    </div>
</template>
