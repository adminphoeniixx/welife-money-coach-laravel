<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Fingerprint, KeyRound, ScanFace, ShieldCheck } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Documents Vault', href: '/vault' }],
    },
});

const props = defineProps<{ mode: 'setup' | 'unlock' }>();

const isSetup = computed(() => props.mode === 'setup');

const form = useForm({
    pin: '',
    pin_confirmation: '',
    current_pin: '',
});

const submit = () => {
    if (isSetup.value) {
        form.post('/vault/pin', { preserveScroll: true });
    } else {
        form.transform((data) => ({ pin: data.pin })).post('/vault/unlock', {
            preserveScroll: true,
            onFinish: () => form.reset('pin'),
        });
    }
};
</script>

<template>
    <Head title="Documents Vault" />

    <div class="flex flex-1 items-center justify-center p-6">
        <div class="w-full max-w-md rounded-2xl border border-border bg-card p-8 shadow-sm">
            <div class="flex flex-col items-center text-center">
                <div
                    class="grid h-16 w-16 place-items-center rounded-2xl text-white"
                    style="background: linear-gradient(135deg, #CC1D79 0%, #06B7AD 100%)"
                >
                    <ShieldCheck :size="30" />
                </div>
                <h1 class="mt-4 text-xl font-extrabold">
                    {{ isSetup ? 'Secure your Documents Vault' : 'Vault locked' }}
                </h1>
                <p class="mt-1 max-w-xs text-sm text-muted-foreground">
                    {{
                        isSetup
                            ? 'Create a PIN to protect your personal documents. You will need it every time you open the vault.'
                            : 'Enter your PIN to unlock and view your documents.'
                    }}
                </p>
            </div>

            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <div v-if="isSetup" class="space-y-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Create a PIN</label>
                        <input
                            v-model="form.pin"
                            type="password"
                            inputmode="numeric"
                            autocomplete="new-password"
                            maxlength="6"
                            placeholder="4–6 digits"
                            class="w-full rounded-xl border border-input bg-background px-4 py-2.5 text-center text-lg tracking-[0.4em] outline-none focus:border-[#CC1D79]"
                        />
                        <InputError :message="form.errors.pin" class="mt-1" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Confirm PIN</label>
                        <input
                            v-model="form.pin_confirmation"
                            type="password"
                            inputmode="numeric"
                            autocomplete="new-password"
                            maxlength="6"
                            placeholder="Re-enter PIN"
                            class="w-full rounded-xl border border-input bg-background px-4 py-2.5 text-center text-lg tracking-[0.4em] outline-none focus:border-[#CC1D79]"
                        />
                    </div>
                </div>

                <div v-else>
                    <label class="mb-1.5 block text-sm font-medium">Vault PIN</label>
                    <input
                        v-model="form.pin"
                        type="password"
                        inputmode="numeric"
                        autocomplete="off"
                        maxlength="6"
                        autofocus
                        placeholder="Enter PIN"
                        class="w-full rounded-xl border border-input bg-background px-4 py-2.5 text-center text-lg tracking-[0.4em] outline-none focus:border-[#CC1D79]"
                    />
                    <InputError :message="form.errors.pin" class="mt-1" />
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="flex w-full items-center justify-center gap-2 rounded-xl py-2.5 font-semibold text-white transition-opacity disabled:opacity-60"
                    style="background: linear-gradient(135deg, #CC1D79 0%, #06B7AD 100%)"
                >
                    <KeyRound :size="17" />
                    {{ isSetup ? 'Set PIN & open vault' : 'Unlock vault' }}
                </button>
            </form>

            <div class="mt-6 flex items-center justify-center gap-4 border-t border-border pt-4 text-xs text-muted-foreground">
                <span class="flex items-center gap-1.5 opacity-70">
                    <Fingerprint :size="15" /> Fingerprint
                </span>
                <span class="flex items-center gap-1.5 opacity-70">
                    <ScanFace :size="15" /> Face ID
                </span>
                <span class="rounded-full bg-muted px-2 py-0.5 font-medium">soon</span>
            </div>
        </div>
    </div>
</template>
