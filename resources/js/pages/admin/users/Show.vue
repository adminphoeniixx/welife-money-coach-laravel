<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    KeyRound,
    LogIn,
    Mail,
    Pencil,
    ShieldCheck,
    ShieldOff,
    Trash2,
    UserX,
} from '@lucide/vue';
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
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface AdminUser {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    suspended_at: string | null;
    email_verified_at: string | null;
    two_factor_enabled: boolean;
    created_at: string;
    updated_at: string;
}

const props = defineProps<{ user: AdminUser }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'Users', href: '/admin/users' },
            { title: 'Detail', href: '/admin/users' },
        ],
    },
});

const editOpen = ref(false);
const form = useForm({ name: props.user.name, email: props.user.email });

const base = () => `/admin/users/${props.user.id}`;
const opts = { preserveScroll: true };

function submitEdit() {
    form.patch(base(), {
        preserveScroll: true,
        onSuccess: () => (editOpen.value = false),
    });
}

function toggleAdmin() {
    router.patch(`${base()}/admin`, {}, opts);
}
function toggleVerified() {
    router.patch(`${base()}/verified`, {}, opts);
}
function sendPasswordReset() {
    router.post(`${base()}/password-reset`, {}, opts);
}
function toggleSuspend() {
    const verb = props.user.suspended_at ? 'reinstate' : 'suspend';

    if (!window.confirm(`Are you sure you want to ${verb} ${props.user.name}?`)) {
return;
}

    router.patch(`${base()}/suspend`, {}, opts);
}
function impersonate() {
    if (!window.confirm(`Log in as ${props.user.name}? You can return from the banner.`)) {
return;
}

    router.post(`${base()}/impersonate`, {});
}
function destroy() {
    if (!window.confirm(`Delete ${props.user.name}? This permanently removes the account.`)) {
return;
}

    router.delete(base());
}

function formatDate(value: string | null): string {
    return value
        ? new Date(value).toLocaleString(undefined, {
              year: 'numeric',
              month: 'short',
              day: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
          })
        : '—';
}

const initials = props.user.name
    .split(' ')
    .map((p) => p[0])
    .slice(0, 2)
    .join('')
    .toUpperCase();
</script>

<template>
    <Head :title="user.name" />

    <div class="flex flex-col gap-6 p-4">
        <Link
            href="/admin/users"
            class="flex w-fit items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" /> Back to users
        </Link>

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-center gap-4">
                <div
                    class="flex size-14 items-center justify-center rounded-full bg-muted text-lg font-semibold"
                >
                    {{ initials }}
                </div>
                <div>
                    <Heading :title="user.name" :description="user.email" />
                    <div class="mt-1 flex flex-wrap gap-2">
                        <Badge :variant="user.is_admin ? 'default' : 'secondary'">
                            {{ user.is_admin ? 'Admin' : 'User' }}
                        </Badge>
                        <Badge
                            :variant="user.email_verified_at ? 'outline' : 'destructive'"
                        >
                            {{ user.email_verified_at ? 'Verified' : 'Unverified' }}
                        </Badge>
                        <Badge v-if="user.suspended_at" variant="destructive">
                            Suspended
                        </Badge>
                        <Badge v-if="user.two_factor_enabled" variant="outline">
                            2FA on
                        </Badge>
                    </div>
                </div>
            </div>
            <Button variant="outline" @click="editOpen = true">
                <Pencil class="size-4" /> Edit
            </Button>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <Card>
                <CardHeader><CardTitle>Account</CardTitle></CardHeader>
                <CardContent class="grid gap-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">User ID</span>
                        <span>{{ user.id }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Email verified</span>
                        <span>{{ formatDate(user.email_verified_at) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Two-factor auth</span>
                        <span>{{ user.two_factor_enabled ? 'Enabled' : 'Disabled' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Joined</span>
                        <span>{{ formatDate(user.created_at) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Last updated</span>
                        <span>{{ formatDate(user.updated_at) }}</span>
                    </div>
                    <div v-if="user.suspended_at" class="flex justify-between">
                        <span class="text-muted-foreground">Suspended</span>
                        <span class="text-destructive">{{ formatDate(user.suspended_at) }}</span>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle>Support actions</CardTitle></CardHeader>
                <CardContent class="grid gap-2">
                    <Button variant="outline" class="justify-start" @click="toggleAdmin">
                        <component :is="user.is_admin ? ShieldOff : ShieldCheck" class="size-4" />
                        {{ user.is_admin ? 'Revoke admin role' : 'Make administrator' }}
                    </Button>
                    <Button variant="outline" class="justify-start" @click="toggleVerified">
                        <Mail class="size-4" />
                        {{ user.email_verified_at ? 'Mark email unverified' : 'Mark email verified' }}
                    </Button>
                    <Button variant="outline" class="justify-start" @click="sendPasswordReset">
                        <KeyRound class="size-4" /> Send password reset link
                    </Button>
                    <Button variant="outline" class="justify-start" @click="impersonate">
                        <LogIn class="size-4" /> Log in as this user
                    </Button>
                    <Button
                        variant="outline"
                        class="justify-start"
                        :class="user.suspended_at ? '' : 'text-destructive hover:text-destructive'"
                        @click="toggleSuspend"
                    >
                        <UserX class="size-4" />
                        {{ user.suspended_at ? 'Reinstate account' : 'Suspend account' }}
                    </Button>
                    <Button
                        variant="outline"
                        class="justify-start text-destructive hover:text-destructive"
                        @click="destroy"
                    >
                        <Trash2 class="size-4" /> Delete account
                    </Button>
                </CardContent>
            </Card>
        </div>

        <Dialog v-model:open="editOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit user</DialogTitle>
                </DialogHeader>
                <form class="flex flex-col gap-4" @submit.prevent="submitEdit">
                    <div class="grid gap-2">
                        <Label for="edit-name">Name</Label>
                        <Input id="edit-name" v-model="form.name" required />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit-email">Email</Label>
                        <Input id="edit-email" v-model="form.email" type="email" required />
                        <InputError :message="form.errors.email" />
                        <p class="text-xs text-muted-foreground">
                            Changing the email marks it as unverified.
                        </p>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="editOpen = false">
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="form.processing">Save changes</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
