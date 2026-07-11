<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    CreditCard,
    ShieldCheck,
    Tags,
    UserCheck,
    UserPlus,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

interface Stats {
    totalUsers: number;
    admins: number;
    newThisMonth: number;
    verifiedUsers: number;
    activePlans: number;
    categoryTemplates: number;
}

interface RecentUser {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    created_at: string;
}

const props = defineProps<{
    stats: Stats;
    signupTrend: { label: string; count: number }[];
    recentUsers: RecentUser[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Admin', href: '/admin' }],
    },
});

const cards = computed(() => [
    { label: 'Total users', value: props.stats.totalUsers, icon: Users },
    { label: 'New this month', value: props.stats.newThisMonth, icon: UserPlus },
    { label: 'Verified users', value: props.stats.verifiedUsers, icon: UserCheck },
    { label: 'Administrators', value: props.stats.admins, icon: ShieldCheck },
    { label: 'Active plans', value: props.stats.activePlans, icon: CreditCard },
    { label: 'Category templates', value: props.stats.categoryTemplates, icon: Tags },
]);

const maxTrend = computed(() =>
    Math.max(1, ...props.signupTrend.map((t) => t.count)),
);

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}
</script>

<template>
    <Head title="Admin overview" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            title="Admin overview"
            description="Monitor platform activity, users, categories and subscriptions."
        />

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Card v-for="card in cards" :key="card.label">
                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0 pb-2"
                >
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        {{ card.label }}
                    </CardTitle>
                    <component
                        :is="card.icon"
                        class="size-5 text-muted-foreground"
                    />
                </CardHeader>
                <CardContent>
                    <div class="text-3xl font-semibold">{{ card.value }}</div>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>New signups (last 6 months)</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex h-48 items-end gap-3">
                        <div
                            v-for="point in signupTrend"
                            :key="point.label"
                            class="flex flex-1 flex-col items-center gap-2"
                        >
                            <span class="text-xs font-medium text-muted-foreground">
                                {{ point.count }}
                            </span>
                            <div
                                class="w-full rounded-t-md bg-primary/80"
                                :style="{
                                    height: `${Math.max(4, (point.count / maxTrend) * 150)}px`,
                                }"
                            />
                            <span class="text-xs text-muted-foreground">
                                {{ point.label }}
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Recent signups</CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <ul class="divide-y">
                        <li
                            v-for="user in recentUsers"
                            :key="user.id"
                            class="flex items-center justify-between gap-2 px-6 py-3"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">
                                    {{ user.name }}
                                </p>
                                <p class="truncate text-xs text-muted-foreground">
                                    {{ user.email }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <Badge v-if="user.is_admin" variant="secondary">
                                    Admin
                                </Badge>
                                <span class="text-xs text-muted-foreground">
                                    {{ formatDate(user.created_at) }}
                                </span>
                            </div>
                        </li>
                        <li
                            v-if="recentUsers.length === 0"
                            class="px-6 py-6 text-center text-sm text-muted-foreground"
                        >
                            No users yet.
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
