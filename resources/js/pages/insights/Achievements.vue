<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Trophy } from '@lucide/vue';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Achievements', href: '/achievements' }] },
});

interface Achievement { key: string; icon: string; title: string; description: string; earned: boolean }

defineProps<{ achievements: Achievement[]; earned: number; total: number }>();
</script>

<template>
    <Head title="Achievements" />

    <div class="flex flex-1 flex-col gap-5 p-4 sm:p-6">
        <div class="flex items-center gap-3 rounded-2xl p-6 text-white" style="background: linear-gradient(135deg, #CC1D79 0%, #06B7AD 100%)">
            <Trophy :size="32" />
            <div>
                <h1 class="text-xl font-extrabold">Your achievements</h1>
                <p class="text-sm text-white/85">{{ earned }} of {{ total }} unlocked — keep going!</p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div
                v-for="a in achievements"
                :key="a.key"
                class="rounded-2xl border p-5 text-center transition-all"
                :class="a.earned ? 'border-[#CC1D79]/30 bg-card' : 'border-dashed border-border bg-card opacity-60'"
            >
                <div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl text-3xl" :class="a.earned ? '' : 'grayscale'">
                    {{ a.icon }}
                </div>
                <div class="mt-3 font-bold">{{ a.title }}</div>
                <div class="mt-1 text-xs text-muted-foreground">{{ a.description }}</div>
                <div
                    class="mt-3 inline-block rounded-full px-2.5 py-0.5 text-[11px] font-bold"
                    :class="a.earned ? 'bg-[#06B7AD]/15 text-[#06B7AD]' : 'bg-muted text-muted-foreground'"
                >
                    {{ a.earned ? 'Unlocked ✓' : 'Locked' }}
                </div>
            </div>
        </div>
    </div>
</template>
