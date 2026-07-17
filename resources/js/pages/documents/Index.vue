<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    CreditCard,
    Download,
    Eye,
    FileText,
    IdCard,
    Lock,
    Pencil,
    Plus,
    Search,
    ShieldCheck,
    Trash2,
    Upload,
} from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Documents Vault', href: '/vault' }],
    },
});

interface Category {
    key: string;
    label: string;
    count: number;
}
interface Doc {
    id: number;
    title: string;
    category: string;
    category_label: string;
    side: string | null;
    is_image: boolean;
    mime_type: string;
    size: string;
    notes: string | null;
    uploaded_at: string | null;
}

const props = defineProps<{
    filters: { search: string; category: string };
    categories: Category[];
    documents: Doc[];
    total: number;
}>();

const search = ref(props.filters.search);
const activeCategory = computed(() => props.filters.category || 'all');

const applyFilters = (category?: string) =>
    router.get(
        '/vault',
        { search: search.value, category: category ?? activeCategory.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );

const onSearch = useDebounceFn(() => applyFilters(), 300);

// Categories where a front/back side is meaningful.
const SIDED = ['debit_atm_card', 'credit_card', 'aadhaar', 'pan', 'driving_license', 'voter_id'];

const CATEGORY_ICON: Record<string, unknown> = {
    debit_atm_card: CreditCard,
    credit_card: CreditCard,
    aadhaar: IdCard,
    pan: IdCard,
    driving_license: IdCard,
    voter_id: IdCard,
    passport: IdCard,
};
const iconFor = (cat: string) => CATEGORY_ICON[cat] ?? FileText;

// ---- Upload / edit dialog ----
const dialogOpen = ref(false);
const editingId = ref<number | null>(null);
const fileName = ref('');

const form = useForm<{
    category: string;
    title: string;
    side: string;
    notes: string;
    file: File | null;
}>({
    category: 'aadhaar',
    title: '',
    side: '',
    notes: '',
    file: null,
});

const showSide = computed(() => SIDED.includes(form.category));

const openAdd = () => {
    editingId.value = null;
    fileName.value = '';
    form.reset();
    form.clearErrors();
    dialogOpen.value = true;
};

const openEdit = (doc: Doc) => {
    editingId.value = doc.id;
    fileName.value = '';
    form.clearErrors();
    form.defaults({
        category: doc.category,
        title: doc.title,
        side: doc.side ?? '',
        notes: doc.notes ?? '',
        file: null,
    });
    form.reset();
    dialogOpen.value = true;
};

const onFile = (e: Event) => {
    const files = (e.target as HTMLInputElement).files;
    form.file = files && files.length ? files[0] : null;
    fileName.value = form.file ? form.file.name : '';
};

const submit = () => {
    const options = {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            dialogOpen.value = false;
            form.reset();
            fileName.value = '';
        },
    };

    if (editingId.value) {
        form.post(`/vault/documents/${editingId.value}`, options);
    } else {
        form.post('/vault/documents', options);
    }
};

// ---- Delete confirm ----
const deleteTarget = ref<Doc | null>(null);
const deleteForm = useForm({});
const confirmDelete = () => {
    if (!deleteTarget.value) {
return;
}

    deleteForm.delete(`/vault/documents/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onSuccess: () => (deleteTarget.value = null),
    });
};

const lockVault = () => router.post('/vault/lock');
</script>

<template>
    <Head title="Documents Vault" />

    <div class="flex flex-1 flex-col gap-5 p-4 sm:p-6">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div
                    class="grid h-11 w-11 place-items-center rounded-xl text-white"
                    style="background: linear-gradient(135deg, #CC1D79 0%, #06B7AD 100%)"
                >
                    <ShieldCheck :size="22" />
                </div>
                <div>
                    <h1 class="text-lg font-extrabold leading-tight">Secure Documents Vault</h1>
                    <p class="text-xs text-muted-foreground">
                        {{ total }} document{{ total === 1 ? '' : 's' }} · encrypted &amp; PIN-protected
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button
                    class="flex items-center gap-1.5 rounded-xl border border-border px-3 py-2 text-sm font-semibold hover:bg-muted"
                    @click="lockVault"
                >
                    <Lock :size="15" /> Lock
                </button>
                <button
                    class="flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-sm font-semibold text-white"
                    style="background: linear-gradient(135deg, #CC1D79 0%, #06B7AD 100%)"
                    @click="openAdd"
                >
                    <Plus :size="16" /> Add document
                </button>
            </div>
        </div>

        <!-- Search -->
        <div class="relative">
            <Search class="absolute top-1/2 left-3 -translate-y-1/2 text-muted-foreground" :size="16" />
            <input
                v-model="search"
                type="search"
                placeholder="Search documents by name or note…"
                class="w-full rounded-xl border border-border bg-card py-2.5 pr-4 pl-9 text-sm outline-none focus:border-[#CC1D79]"
                @input="onSearch"
            />
        </div>

        <!-- Category chips -->
        <div class="flex flex-wrap gap-2">
            <button
                class="rounded-full px-3 py-1.5 text-xs font-semibold transition-colors"
                :class="activeCategory === 'all' ? 'bg-foreground text-background' : 'bg-muted text-muted-foreground hover:bg-muted/70'"
                @click="applyFilters('all')"
            >
                All · {{ total }}
            </button>
            <button
                v-for="c in categories.filter((c) => c.count > 0 || c.key === activeCategory)"
                :key="c.key"
                class="rounded-full px-3 py-1.5 text-xs font-semibold transition-colors"
                :class="activeCategory === c.key ? 'bg-foreground text-background' : 'bg-muted text-muted-foreground hover:bg-muted/70'"
                @click="applyFilters(c.key)"
            >
                {{ c.label }} · {{ c.count }}
            </button>
        </div>

        <!-- Documents grid -->
        <div v-if="documents.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="doc in documents"
                :key="doc.id"
                class="flex flex-col rounded-2xl border border-border bg-card p-4"
            >
                <div class="flex items-start gap-3">
                    <span class="grid h-11 w-11 flex-none place-items-center rounded-xl bg-[#CC1D79]/10 text-[#CC1D79]">
                        <component :is="iconFor(doc.category)" :size="20" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-semibold">{{ doc.title }}</div>
                        <div class="text-xs text-muted-foreground">{{ doc.category_label }}</div>
                    </div>
                    <span
                        v-if="doc.side"
                        class="rounded-full bg-muted px-2 py-0.5 text-[10px] font-bold uppercase text-muted-foreground"
                    >{{ doc.side }}</span>
                </div>

                <p v-if="doc.notes" class="mt-3 line-clamp-2 text-xs text-muted-foreground">{{ doc.notes }}</p>

                <div class="mt-3 flex items-center gap-2 text-[11px] text-muted-foreground">
                    <span class="rounded bg-muted px-1.5 py-0.5 font-medium uppercase">
                        {{ doc.mime_type.includes('pdf') ? 'PDF' : 'Image' }}
                    </span>
                    <span>{{ doc.size }}</span>
                    <span v-if="doc.uploaded_at">· {{ doc.uploaded_at }}</span>
                </div>

                <div class="mt-4 flex items-center gap-1 border-t border-border pt-3">
                    <a
                        :href="`/vault/documents/${doc.id}/view`"
                        target="_blank"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded-lg py-1.5 text-xs font-semibold text-muted-foreground hover:bg-muted hover:text-foreground"
                    >
                        <Eye :size="14" /> View
                    </a>
                    <a
                        :href="`/vault/documents/${doc.id}/download`"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded-lg py-1.5 text-xs font-semibold text-muted-foreground hover:bg-muted hover:text-foreground"
                    >
                        <Download :size="14" /> Download
                    </a>
                    <button
                        class="grid h-8 w-8 place-items-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground"
                        title="Edit"
                        @click="openEdit(doc)"
                    >
                        <Pencil :size="14" />
                    </button>
                    <button
                        class="grid h-8 w-8 place-items-center rounded-lg text-muted-foreground hover:bg-[#CC1D79]/10 hover:text-[#CC1D79]"
                        title="Delete"
                        @click="deleteTarget = doc"
                    >
                        <Trash2 :size="14" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-else class="grid place-items-center rounded-2xl border border-dashed border-border bg-card p-12 text-center">
            <div>
                <div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-muted text-muted-foreground">
                    <Upload :size="26" />
                </div>
                <h3 class="mt-3 font-bold">No documents yet</h3>
                <p class="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                    Keep your Aadhaar, PAN, cards, insurance and more in one encrypted place for emergencies.
                </p>
                <button
                    class="mt-4 inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-sm font-semibold text-white"
                    style="background: linear-gradient(135deg, #CC1D79 0%, #06B7AD 100%)"
                    @click="openAdd"
                >
                    <Plus :size="16" /> Add your first document
                </button>
            </div>
        </div>
    </div>

    <!-- Upload / edit dialog -->
    <Dialog v-model:open="dialogOpen">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ editingId ? 'Update document' : 'Add a document' }}</DialogTitle>
                <DialogDescription>
                    Files are encrypted before they are stored. Only you can open them.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Category</label>
                    <select
                        v-model="form.category"
                        class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]"
                    >
                        <option v-for="c in categories" :key="c.key" :value="c.key">{{ c.label }}</option>
                    </select>
                    <InputError :message="form.errors.category" class="mt-1" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div :class="showSide ? '' : 'col-span-2'">
                        <label class="mb-1.5 block text-sm font-medium">Title</label>
                        <input
                            v-model="form.title"
                            type="text"
                            placeholder="e.g. HDFC Debit Card"
                            class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]"
                        />
                        <InputError :message="form.errors.title" class="mt-1" />
                    </div>
                    <div v-if="showSide">
                        <label class="mb-1.5 block text-sm font-medium">Side</label>
                        <select
                            v-model="form.side"
                            class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]"
                        >
                            <option value="">—</option>
                            <option value="front">Front</option>
                            <option value="back">Back</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">
                        File {{ editingId ? '(leave empty to keep current)' : '' }}
                    </label>
                    <label
                        class="flex cursor-pointer items-center gap-2 rounded-xl border border-dashed border-input bg-background px-3 py-2.5 text-sm text-muted-foreground hover:border-[#CC1D79]"
                    >
                        <Upload :size="16" />
                        <span class="truncate">{{ fileName || 'Choose a photo or PDF (max 8 MB)' }}</span>
                        <input type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" class="hidden" @change="onFile" />
                    </label>
                    <InputError :message="form.errors.file" class="mt-1" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">Notes (optional)</label>
                    <textarea
                        v-model="form.notes"
                        rows="2"
                        placeholder="Any reference detail you want to remember…"
                        class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-[#CC1D79]"
                    />
                    <InputError :message="form.errors.notes" class="mt-1" />
                </div>

                <DialogFooter>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full rounded-xl py-2.5 font-semibold text-white disabled:opacity-60"
                        style="background: linear-gradient(135deg, #CC1D79 0%, #06B7AD 100%)"
                    >
                        {{ form.processing ? 'Saving…' : editingId ? 'Save changes' : 'Add to vault' }}
                    </button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Delete confirm -->
    <Dialog :open="deleteTarget !== null" @update:open="(v) => { if (!v) deleteTarget = null; }">
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle>Delete document?</DialogTitle>
                <DialogDescription>
                    “{{ deleteTarget?.title }}” will be permanently removed from your vault. This cannot be undone.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <button
                    class="flex-1 rounded-xl border border-border py-2.5 font-semibold hover:bg-muted"
                    @click="deleteTarget = null"
                >
                    Cancel
                </button>
                <button
                    class="flex-1 rounded-xl bg-[#CC1D79] py-2.5 font-semibold text-white disabled:opacity-60"
                    :disabled="deleteForm.processing"
                    @click="confirmDelete"
                >
                    Delete
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
