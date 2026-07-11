<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Megaphone, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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

interface Item {
    id: number;
    type: string;
    title: string;
    slug: string;
    body: string | null;
    is_published: boolean;
    sort_order: number;
    updated_at: string;
}

const props = defineProps<{
    type: string;
    items: Item[];
    counts: Record<string, number>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'Content', href: '/admin/content' },
        ],
    },
});

const types = [
    { key: 'announcement', label: 'Announcements' },
    { key: 'faq', label: 'FAQ' },
    { key: 'legal', label: 'Legal' },
    { key: 'page', label: 'Pages' },
];

const dialogOpen = ref(false);
const editing = ref<Item | null>(null);
const form = useForm({
    type: props.type,
    title: '',
    body: '',
    is_published: false,
    sort_order: 0,
});

function switchType(type: string) {
    router.get('/admin/content', { type }, { preserveScroll: true, preserveState: false });
}
function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    form.type = props.type;
    dialogOpen.value = true;
}
function openEdit(item: Item) {
    editing.value = item;
    form.clearErrors();
    form.type = item.type;
    form.title = item.title;
    form.body = item.body ?? '';
    form.is_published = item.is_published;
    form.sort_order = item.sort_order;
    dialogOpen.value = true;
}
function submit() {
    const opts = { preserveScroll: true, onSuccess: () => (dialogOpen.value = false) };
    if (editing.value) form.patch(`/admin/content/${editing.value.id}`, opts);
    else form.post('/admin/content', opts);
}
function destroy(item: Item) {
    if (!window.confirm(`Delete "${item.title}"?`)) return;
    router.delete(`/admin/content/${item.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Content" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                title="Content"
                description="Announcements, FAQ, legal pages and other content shown in the app."
            />
            <Button @click="openCreate"><Plus class="size-4" /> Add content</Button>
        </div>

        <div class="flex flex-wrap gap-1">
            <Button
                v-for="t in types"
                :key="t.key"
                size="sm"
                :variant="type === t.key ? 'default' : 'outline'"
                @click="switchType(t.key)"
            >
                {{ t.label }} ({{ counts[t.key] ?? 0 }})
            </Button>
        </div>

        <Card>
            <CardContent class="p-0">
                <ul class="divide-y">
                    <li v-for="item in items" :key="item.id" class="flex items-start justify-between gap-3 px-4 py-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <Megaphone v-if="item.type === 'announcement'" class="size-4 text-muted-foreground" />
                                <span class="font-medium">{{ item.title }}</span>
                                <Badge :variant="item.is_published ? 'default' : 'secondary'">
                                    {{ item.is_published ? 'Published' : 'Draft' }}
                                </Badge>
                            </div>
                            <p v-if="item.body" class="mt-1 line-clamp-2 max-w-2xl text-sm text-muted-foreground">
                                {{ item.body }}
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-1">
                            <Button variant="ghost" size="sm" @click="openEdit(item)"><Pencil class="size-4" /></Button>
                            <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive" @click="destroy(item)">
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </li>
                    <li v-if="items.length === 0" class="px-4 py-10 text-center text-sm text-muted-foreground">
                        No {{ type }} content yet.
                    </li>
                </ul>
            </CardContent>
        </Card>

        <Dialog v-model:open="dialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ editing ? 'Edit content' : 'Add content' }}</DialogTitle>
                </DialogHeader>
                <form class="flex flex-col gap-4" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="c-title">Title</Label>
                        <Input id="c-title" v-model="form.title" required />
                        <InputError :message="form.errors.title" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="c-body">Body</Label>
                        <textarea
                            id="c-body"
                            v-model="form.body"
                            rows="6"
                            class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        />
                        <InputError :message="form.errors.body" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="c-sort">Sort order</Label>
                        <Input id="c-sort" v-model.number="form.sort_order" type="number" min="0" />
                    </div>
                    <div class="flex items-center gap-2">
                        <Checkbox id="c-pub" v-model="form.is_published" />
                        <Label for="c-pub">Published</Label>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="dialogOpen = false">Cancel</Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ editing ? 'Save changes' : 'Add content' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
