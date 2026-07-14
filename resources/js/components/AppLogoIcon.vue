<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <img
        src="/moneycoach-logo.png"
        alt="MoneyCoach"
        :class="className"
        v-bind="$attrs"
    />
</template>
