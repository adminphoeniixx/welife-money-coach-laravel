<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { AlertTriangle, Bell, Check, Clock, CreditCard, TrendingUp } from '@lucide/vue';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Notifications', href: '/notifications' }] },
});

interface Note { tone: string; icon: string; title: string; text: string }

defineProps<{ notifications: Note[] }>();

const TONES: Record<string, string> = { red: '#CC1D79', amber: '#F5A524', teal: '#06B7AD' };
const ICONS: Record<string, unknown> = { alert: AlertTriangle, clock: Clock, 'credit-card': CreditCard, 'trending-up': TrendingUp, check: Check };
</script>

<template>
    <Head title="Notifications" />

    <div class="flex flex-1 flex-col gap-4 p-4 sm:p-6">
        <h1 class="flex items-center gap-2 text-lg font-extrabold"><Bell :size="20" /> Notifications</h1>

        <div v-if="notifications.length" class="space-y-3">
            <div
                v-for="(n, i) in notifications"
                :key="i"
                class="flex gap-3 rounded-2xl border p-4"
                :style="{ borderColor: (TONES[n.tone] ?? '#999') + '33', background: (TONES[n.tone] ?? '#999') + '0d' }"
            >
                <span class="grid h-9 w-9 flex-none place-items-center rounded-xl" :style="{ background: (TONES[n.tone] ?? '#999') + '22', color: TONES[n.tone] ?? '#999' }">
                    <component :is="ICONS[n.icon] ?? Bell" :size="17" />
                </span>
                <div>
                    <div class="font-semibold">{{ n.title }}</div>
                    <div class="text-sm text-muted-foreground">{{ n.text }}</div>
                </div>
            </div>
        </div>
        <div v-else class="grid place-items-center rounded-2xl border border-dashed border-border bg-card p-12 text-center">
            <div>
                <Bell class="mx-auto text-muted-foreground" :size="28" />
                <p class="mt-2 text-sm text-muted-foreground">You're all caught up. No alerts right now. 🎉</p>
            </div>
        </div>
    </div>
</template>
