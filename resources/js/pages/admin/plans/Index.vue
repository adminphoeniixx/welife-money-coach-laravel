<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Check, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Plan {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    price_cents: number;
    currency: string;
    interval: 'month' | 'year' | 'lifetime';
    features: string[] | null;
    is_active: boolean;
    sort_order: number;
}

const props = defineProps<{ plans: Plan[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'Plans', href: '/admin/plans' },
        ],
    },
});

const dialogOpen = ref(false);
const editing = ref<Plan | null>(null);

const form = useForm({
    name: '',
    description: '',
    price: 0,
    currency: 'USD',
    interval: 'month' as Plan['interval'],
    featuresText: '',
    is_active: true,
    sort_order: 0,
});

function priceLabel(plan: Plan): string {
    const amount = (plan.price_cents / 100).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    return `${plan.currency} ${amount}`;
}

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    dialogOpen.value = true;
}

function openEdit(plan: Plan) {
    editing.value = plan;
    form.clearErrors();
    form.name = plan.name;
    form.description = plan.description ?? '';
    form.price = plan.price_cents / 100;
    form.currency = plan.currency;
    form.interval = plan.interval;
    form.featuresText = (plan.features ?? []).join('\n');
    form.is_active = plan.is_active;
    form.sort_order = plan.sort_order;
    dialogOpen.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (dialogOpen.value = false),
    };

    form.transform((data) => ({
        ...data,
        features: data.featuresText
            .split('\n')
            .map((f) => f.trim())
            .filter((f) => f !== ''),
    }));

    if (editing.value) {
        form.patch(`/admin/plans/${editing.value.id}`, options);
    } else {
        form.post('/admin/plans', options);
    }
}

function destroy(plan: Plan) {
    if (!window.confirm(`Delete plan "${plan.name}"?`)) {
        return;
    }

    router.delete(`/admin/plans/${plan.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Subscription plans" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                title="Subscription plans"
                description="Manage the free and premium tiers shown to users."
            />
            <Button @click="openCreate">
                <Plus class="size-4" />
                Add plan
            </Button>
        </div>

        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <Card
                v-for="plan in plans"
                :key="plan.id"
                :class="!plan.is_active ? 'opacity-60' : ''"
            >
                <CardHeader class="flex flex-row items-start justify-between">
                    <div>
                        <CardTitle class="flex items-center gap-2">
                            {{ plan.name }}
                            <Badge v-if="!plan.is_active" variant="secondary">
                                Inactive
                            </Badge>
                        </CardTitle>
                        <p class="mt-1 text-2xl font-semibold">
                            {{ priceLabel(plan) }}
                            <span class="text-sm font-normal text-muted-foreground">
                                / {{ plan.interval }}
                            </span>
                        </p>
                    </div>
                    <div class="flex gap-1">
                        <Button variant="ghost" size="sm" @click="openEdit(plan)">
                            <Pencil class="size-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="text-destructive hover:text-destructive"
                            @click="destroy(plan)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <p
                        v-if="plan.description"
                        class="mb-3 text-sm text-muted-foreground"
                    >
                        {{ plan.description }}
                    </p>
                    <ul class="flex flex-col gap-1.5">
                        <li
                            v-for="feature in plan.features ?? []"
                            :key="feature"
                            class="flex items-start gap-2 text-sm"
                        >
                            <Check
                                class="mt-0.5 size-4 shrink-0 text-primary"
                            />
                            <span>{{ feature }}</span>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <p
                v-if="plans.length === 0"
                class="col-span-full rounded-lg border border-dashed py-10 text-center text-sm text-muted-foreground"
            >
                No plans yet. Add your first subscription tier.
            </p>
        </div>

        <Dialog v-model:open="dialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {{ editing ? 'Edit plan' : 'Add plan' }}
                    </DialogTitle>
                </DialogHeader>

                <form class="flex flex-col gap-4" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="plan-name">Name</Label>
                        <Input id="plan-name" v-model="form.name" required />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="plan-desc">Description</Label>
                        <textarea
                            id="plan-desc"
                            v-model="form.description"
                            rows="2"
                            class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        />
                        <InputError :message="form.errors.description" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-2">
                            <Label for="plan-price">Price</Label>
                            <Input
                                id="plan-price"
                                v-model.number="form.price"
                                type="number"
                                step="0.01"
                                min="0"
                            />
                            <InputError :message="form.errors.price" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="plan-currency">Currency</Label>
                            <Input
                                id="plan-currency"
                                v-model="form.currency"
                                maxlength="3"
                            />
                            <InputError :message="form.errors.currency" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="plan-interval">Billing interval</Label>
                        <select
                            id="plan-interval"
                            v-model="form.interval"
                            class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        >
                            <option value="month">Monthly</option>
                            <option value="year">Yearly</option>
                            <option value="lifetime">Lifetime</option>
                        </select>
                        <InputError :message="form.errors.interval" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="plan-features">
                            Features (one per line)
                        </Label>
                        <textarea
                            id="plan-features"
                            v-model="form.featuresText"
                            rows="4"
                            class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        />
                    </div>
                    <div class="flex items-center gap-2">
                        <Checkbox id="plan-active" v-model="form.is_active" />
                        <Label for="plan-active">Active</Label>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="dialogOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ editing ? 'Save changes' : 'Add plan' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
