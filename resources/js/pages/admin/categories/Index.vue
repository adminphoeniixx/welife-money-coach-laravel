<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Category {
    id: number;
    type: 'income' | 'expense';
    group: string | null;
    name: string;
    slug: string;
    icon: string | null;
    is_active: boolean;
    sort_order: number;
}

const props = defineProps<{
    type: 'income' | 'expense';
    categories: Category[];
    counts: { income: number; expense: number };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'Categories', href: '/admin/categories' },
        ],
    },
});

const dialogOpen = ref(false);
const editing = ref<Category | null>(null);

const form = useForm({
    type: props.type,
    group: '',
    name: '',
    icon: '',
    is_active: true,
    sort_order: 0,
});

function switchType(type: 'income' | 'expense') {
    router.get(
        '/admin/categories',
        { type },
        { preserveScroll: true, preserveState: false },
    );
}

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    form.type = props.type;
    dialogOpen.value = true;
}

function openEdit(category: Category) {
    editing.value = category;
    form.clearErrors();
    form.type = category.type;
    form.group = category.group ?? '';
    form.name = category.name;
    form.icon = category.icon ?? '';
    form.is_active = category.is_active;
    form.sort_order = category.sort_order;
    dialogOpen.value = true;
}

function submit() {
    if (editing.value) {
        form.patch(`/admin/categories/${editing.value.id}`, {
            preserveScroll: true,
            onSuccess: () => (dialogOpen.value = false),
        });
    } else {
        form.post('/admin/categories', {
            preserveScroll: true,
            onSuccess: () => (dialogOpen.value = false),
        });
    }
}

function destroy(category: Category) {
    if (!window.confirm(`Delete category "${category.name}"?`)) {
        return;
    }

    router.delete(`/admin/categories/${category.id}`, { preserveScroll: true });
}

const grouped = computed(() => {
    const map = new Map<string, Category[]>();

    for (const c of props.categories) {
        const key = c.group ?? 'Ungrouped';

        if (!map.has(key)) {
map.set(key, []);
}

        map.get(key)!.push(c);
    }

    return Array.from(map.entries());
});
</script>

<template>
    <Head title="Category templates" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                title="Category templates"
                description="Default income and expense categories offered to every user."
            />
            <Button @click="openCreate">
                <Plus class="size-4" />
                Add category
            </Button>
        </div>

        <div class="flex gap-1">
            <Button
                :variant="type === 'income' ? 'default' : 'outline'"
                size="sm"
                @click="switchType('income')"
            >
                Income ({{ counts.income }})
            </Button>
            <Button
                :variant="type === 'expense' ? 'default' : 'outline'"
                size="sm"
                @click="switchType('expense')"
            >
                Expense ({{ counts.expense }})
            </Button>
        </div>

        <div class="flex flex-col gap-4">
            <Card v-for="[group, items] in grouped" :key="group">
                <CardContent class="p-0">
                    <div
                        class="border-b px-4 py-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        {{ group }}
                    </div>
                    <ul class="divide-y">
                        <li
                            v-for="cat in items"
                            :key="cat.id"
                            class="flex items-center justify-between gap-2 px-4 py-3"
                        >
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium">{{
                                    cat.name
                                }}</span>
                                <Badge
                                    v-if="!cat.is_active"
                                    variant="secondary"
                                    >Inactive</Badge
                                >
                            </div>
                            <div class="flex gap-1">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    @click="openEdit(cat)"
                                >
                                    <Pencil class="size-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="text-destructive hover:text-destructive"
                                    @click="destroy(cat)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <p
                v-if="categories.length === 0"
                class="rounded-lg border border-dashed py-10 text-center text-sm text-muted-foreground"
            >
                No {{ type }} categories yet. Add your first one.
            </p>
        </div>

        <Dialog v-model:open="dialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {{ editing ? 'Edit category' : 'Add category' }}
                    </DialogTitle>
                    <DialogDescription>
                        {{ form.type === 'income' ? 'Income' : 'Expense' }}
                        category template.
                    </DialogDescription>
                </DialogHeader>

                <form class="flex flex-col gap-4" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="cat-name">Name</Label>
                        <Input id="cat-name" v-model="form.name" required />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="cat-group">Group (optional)</Label>
                        <Input
                            id="cat-group"
                            v-model="form.group"
                            placeholder="e.g. Housing"
                        />
                        <InputError :message="form.errors.group" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="cat-sort">Sort order</Label>
                        <Input
                            id="cat-sort"
                            v-model.number="form.sort_order"
                            type="number"
                            min="0"
                        />
                        <InputError :message="form.errors.sort_order" />
                    </div>
                    <div class="flex items-center gap-2">
                        <Checkbox id="cat-active" v-model="form.is_active" />
                        <Label for="cat-active">Active</Label>
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
                            {{ editing ? 'Save changes' : 'Add category' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
