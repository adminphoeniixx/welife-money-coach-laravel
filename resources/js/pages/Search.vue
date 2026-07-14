<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { ArrowLeftRight, CreditCard, Receipt, Search as SearchIcon, Wallet } from '@lucide/vue';
import { ref } from 'vue';
import { useCurrency } from '@/composables/useCurrency';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Search', href: '/search' }] },
});

interface Hit { id: number; title: string; subtitle: string; amount: number; type?: string; href: string }

const props = defineProps<{
    query: string;
    count: number;
    results: { transactions: Hit[]; debts: Hit[]; bills: Hit[]; assets: Hit[] };
}>();

const { fmt } = useCurrency();
const q = ref(props.query);

const run = useDebounceFn(() => {
    router.get('/search', { q: q.value }, { preserveState: true, preserveScroll: true, replace: true });
}, 300);

const groups = [
    { key: 'transactions', label: 'Transactions', icon: ArrowLeftRight },
    { key: 'debts', label: 'Loans & cards', icon: CreditCard },
    { key: 'bills', label: 'Bills & reminders', icon: Receipt },
    { key: 'assets', label: 'Assets', icon: Wallet },
] as const;
</script>

<template>
    <Head title="Search" />

    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-5 p-4 sm:p-6">
        <div class="relative">
            <SearchIcon class="absolute top-1/2 left-4 -translate-y-1/2 text-muted-foreground" :size="18" />
            <input
                v-model="q"
                type="search"
                autofocus
                placeholder="Search transactions, loans, cards, bills, assets…"
                class="w-full rounded-2xl border border-border bg-card py-3.5 pr-4 pl-11 text-sm outline-none focus:border-[#CC1D79]"
                @input="run"
            />
        </div>

        <template v-if="query">
            <p class="text-sm text-muted-foreground">{{ count }} result{{ count === 1 ? '' : 's' }} for “{{ query }}”</p>

            <div v-for="g in groups" :key="g.key">
                <div v-if="results[g.key].length" class="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                    <component :is="g.icon" :size="14" /> {{ g.label }}
                </div>
                <div v-if="results[g.key].length" class="divide-y divide-border overflow-hidden rounded-2xl border border-border bg-card">
                    <Link v-for="h in results[g.key]" :key="g.key + h.id" :href="h.href" class="flex items-center justify-between px-4 py-3 hover:bg-muted">
                        <div>
                            <div class="text-sm font-semibold">{{ h.title }}</div>
                            <div class="text-xs text-muted-foreground">{{ h.subtitle }}</div>
                        </div>
                        <div class="font-bold tabular-nums" :class="h.type === 'income' ? 'text-[#10B981]' : ''">{{ fmt(h.amount) }}</div>
                    </Link>
                </div>
            </div>

            <div v-if="count === 0" class="rounded-2xl border border-dashed border-border bg-card p-10 text-center text-sm text-muted-foreground">
                Nothing matched “{{ query }}”.
            </div>
        </template>
        <div v-else class="rounded-2xl border border-dashed border-border bg-card p-10 text-center text-sm text-muted-foreground">
            Start typing to search everything in your account.
        </div>
    </div>
</template>
