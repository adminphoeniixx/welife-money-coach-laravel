<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { useCurrency } from '@/composables/useCurrency';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Calendar', href: '/calendar' }] },
});

interface DayItem { id: number; name: string; kind: string; amount: number; status: string }
interface Day { date: string; day: number; in_month: boolean; today: boolean; items: DayItem[] }

defineProps<{
    month: string;
    label: string;
    prev: string;
    next: string;
    weekdays: string[];
    days: Day[];
}>();

const { fmtc } = useCurrency();
</script>

<template>
    <Head title="Calendar" />

    <div class="flex flex-1 flex-col gap-4 p-4 sm:p-6">
        <div class="flex items-center justify-between">
            <h1 class="text-lg font-extrabold">{{ label }}</h1>
            <div class="flex gap-2">
                <Link :href="`/calendar?month=${prev}`" class="grid h-9 w-9 place-items-center rounded-xl border border-border hover:bg-muted"><ChevronLeft :size="18" /></Link>
                <Link href="/calendar" class="rounded-xl border border-border px-3 py-2 text-sm font-semibold hover:bg-muted">Today</Link>
                <Link :href="`/calendar?month=${next}`" class="grid h-9 w-9 place-items-center rounded-xl border border-border hover:bg-muted"><ChevronRight :size="18" /></Link>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-border bg-card">
            <div class="grid grid-cols-7 border-b border-border text-center text-xs font-semibold text-muted-foreground">
                <div v-for="w in weekdays" :key="w" class="py-2">{{ w }}</div>
            </div>
            <div class="grid grid-cols-7">
                <div
                    v-for="d in days"
                    :key="d.date"
                    class="min-h-[92px] border-b border-r border-border p-1.5"
                    :class="d.in_month ? '' : 'bg-muted/30'"
                >
                    <div
                        class="mb-1 inline-grid h-6 w-6 place-items-center rounded-full text-xs font-semibold"
                        :class="d.today ? 'bg-[#CC1D79] text-white' : d.in_month ? 'text-foreground' : 'text-muted-foreground'"
                    >
                        {{ d.day }}
                    </div>
                    <div class="space-y-1">
                        <div
                            v-for="it in d.items"
                            :key="it.id"
                            class="truncate rounded px-1.5 py-0.5 text-[10px] font-medium"
                            :class="it.status === 'overdue' ? 'bg-[#CC1D79]/15 text-[#CC1D79]' : 'bg-[#06B7AD]/15 text-[#06B7AD]'"
                            :title="`${it.name} · ${fmtc(it.amount)}`"
                        >
                            {{ it.name }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <p class="text-xs text-muted-foreground">Dots show bills, EMIs and subscription renewals due each day.</p>
    </div>
</template>
