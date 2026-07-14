<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Users } from '@lucide/vue';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Family', href: '/family' }, { title: 'Join', href: '#' }] },
});

const props = defineProps<{
    token: string;
    household: string;
    email: string;
    email_matches: boolean;
    already_in_family: boolean;
}>();

const form = useForm({});
const accept = () => form.post(`/family/join/${props.token}`);
</script>

<template>
    <Head title="Join family" />

    <div class="flex flex-1 items-center justify-center p-6">
        <div class="w-full max-w-md rounded-2xl border border-border bg-card p-8 text-center">
            <div class="mx-auto grid h-16 w-16 place-items-center rounded-2xl text-white" style="background: linear-gradient(135deg, #CC1D79 0%, #06B7AD 100%)">
                <Users :size="30" />
            </div>
            <h1 class="mt-4 text-xl font-extrabold">Join “{{ household }}”</h1>
            <p class="mx-auto mt-1 max-w-xs text-sm text-muted-foreground">
                You've been invited to manage money together as a family.
            </p>

            <div v-if="already_in_family" class="mt-6 rounded-xl bg-muted p-4 text-sm text-muted-foreground">
                You already belong to a family. Leave it first to join a new one.
            </div>
            <div v-else-if="!email_matches" class="mt-6 rounded-xl bg-[#CC1D79]/8 p-4 text-sm text-[#CC1D79]">
                This invite was sent to <strong>{{ email }}</strong>. Sign in with that email to accept it.
            </div>
            <button
                v-else
                :disabled="form.processing"
                class="mt-6 w-full rounded-xl py-2.5 font-semibold text-white disabled:opacity-60"
                style="background: linear-gradient(135deg, #CC1D79 0%, #06B7AD 100%)"
                @click="accept"
            >
                Accept &amp; join
            </button>

            <Link href="/family" class="mt-3 inline-block text-sm font-semibold text-muted-foreground hover:text-foreground">Not now</Link>
        </div>
    </div>
</template>
