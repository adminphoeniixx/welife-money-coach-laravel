<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Flag, Plus, Trophy, X } from '@lucide/vue';
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
    layout: { breadcrumbs: [{ title: 'Challenges', href: '/challenges' }] },
});

interface Active { id: number; title: string; description: string | null; target: number; progress: number; percent: number; status: string; days_left: number }
interface Preset { key: string; title: string; description: string; target: number }

defineProps<{ active: Active[]; presets: Preset[] }>();

const { fmt } = useCurrency();

const join = useForm({ key: '' });
const joinChallenge = (key: string) => {
    join.key = key;
    join.post('/challenges', { preserveScroll: true });
};

const leave = useForm({});
const leaveChallenge = (id: number) => leave.delete(`/challenges/${id}`, { preserveScroll: true });

const logTarget = ref<Active | null>(null);
const logForm = useForm({ amount: '' });
const submitLog = () => {
    if (!logTarget.value) return;
    logForm.post(`/challenges/${logTarget.value.id}/progress`, {
        preserveScroll: true,
        onSuccess: () => { logTarget.value = null; logForm.reset(); },
    });
};
</script>

<template>
    <Head title="Challenges" />

    <div class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
        <div class="flex items-center gap-3 rounded-2xl p-6 text-white" style="background: linear-gradient(135deg, #CC1D79 0%, #06B7AD 100%)">
            <Trophy :size="30" />
            <div>
                <h1 class="text-xl font-extrabold">Savings challenges</h1>
                <p class="text-sm text-white/85">Small monthly goals to build better money habits.</p>
            </div>
        </div>

        <!-- Active -->
        <div v-if="active.length">
            <h2 class="mb-3 font-bold">Your challenges</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="c in active" :key="c.id" class="rounded-2xl border border-border bg-card p-5">
                    <div class="flex items-start justify-between">
                        <Flag :size="18" class="text-[#CC1D79]" />
                        <button class="grid h-7 w-7 place-items-center rounded-lg text-muted-foreground hover:bg-muted" @click="leaveChallenge(c.id)"><X :size="14" /></button>
                    </div>
                    <div class="mt-2 font-bold">{{ c.title }}</div>
                    <div class="text-xs text-muted-foreground">{{ c.description }}</div>
                    <div class="mt-3 flex items-end justify-between">
                        <div class="text-lg font-extrabold">{{ fmt(c.progress) }}</div>
                        <div class="text-sm text-muted-foreground">of {{ fmt(c.target) }}</div>
                    </div>
                    <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-muted">
                        <div class="h-full rounded-full" :style="{ width: c.percent + '%', background: 'linear-gradient(90deg,#CC1D79,#06B7AD)' }" />
                    </div>
                    <div class="mt-3 flex items-center justify-between">
                        <span v-if="c.status === 'completed'" class="text-xs font-bold text-[#06B7AD]">Completed 🏆</span>
                        <span v-else class="text-xs text-muted-foreground">{{ c.days_left }} days left</span>
                        <button v-if="c.status !== 'completed'" class="rounded-lg bg-[#06B7AD]/10 px-2.5 py-1 text-xs font-semibold text-[#06B7AD]" @click="logTarget = c">+ Log progress</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Presets -->
        <div v-if="presets.length">
            <h2 class="mb-3 font-bold">Join a challenge</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="p in presets" :key="p.key" class="flex flex-col rounded-2xl border border-dashed border-border bg-card p-5">
                    <div class="font-bold">{{ p.title }}</div>
                    <div class="mt-1 flex-1 text-xs text-muted-foreground">{{ p.description }}</div>
                    <button
                        class="mt-4 flex items-center justify-center gap-1.5 rounded-xl py-2 text-sm font-semibold text-white disabled:opacity-60"
                        style="background: linear-gradient(135deg, #CC1D79 0%, #06B7AD 100%)"
                        :disabled="join.processing"
                        @click="joinChallenge(p.key)"
                    >
                        <Plus :size="15" /> Accept challenge
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Log progress -->
    <Dialog :open="logTarget !== null" @update:open="(v) => { if (!v) logTarget = null; }">
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle>Log progress</DialogTitle>
                <DialogDescription>Add toward “{{ logTarget?.title }}”.</DialogDescription>
            </DialogHeader>
            <form @submit.prevent="submitLog">
                <label class="mb-1.5 block text-sm font-medium">Amount (₹)</label>
                <input v-model="logForm.amount" type="number" step="0.01" min="0" autofocus class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]" />
                <InputError :message="logForm.errors.amount" class="mt-1" />
                <DialogFooter class="mt-4 gap-2">
                    <button type="button" class="flex-1 rounded-xl border border-border py-2.5 font-semibold hover:bg-muted" @click="logTarget = null">Cancel</button>
                    <button type="submit" :disabled="logForm.processing" class="flex-1 rounded-xl py-2.5 font-semibold text-white disabled:opacity-60" style="background: linear-gradient(135deg, #CC1D79 0%, #06B7AD 100%)">Log</button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
