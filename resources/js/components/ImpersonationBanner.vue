<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { UserCog } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';

const page = usePage();
const impersonating = computed(() => page.props.impersonating);

function stop() {
    router.post('/stop-impersonating');
}
</script>

<template>
    <div
        v-if="impersonating"
        class="flex flex-wrap items-center justify-center gap-2 bg-amber-500 px-4 py-2 text-center text-sm font-medium text-amber-950"
    >
        <UserCog class="size-4" />
        <span>
            You are viewing the app as
            <strong>{{ impersonating.name }}</strong>.
        </span>
        <Button
            size="sm"
            variant="outline"
            class="h-7 border-amber-950/30 bg-white/70 text-amber-950 hover:bg-white"
            @click="stop"
        >
            Stop impersonating
        </Button>
    </div>
</template>
