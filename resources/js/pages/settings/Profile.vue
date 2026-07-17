<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profile settings',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

// Profile photo: live preview + remove flag.
const photoPreview = ref<string | null>(null);
const removePhoto = ref(false);
const initials = computed(() =>
    (user.value.name ?? '?')
        .split(' ')
        .map((p: string) => p[0])
        .join('')
        .slice(0, 2)
        .toUpperCase(),
);
const currentAvatar = computed<string | null>(() => {
    if (removePhoto.value) {
return null;
}

    return photoPreview.value ?? (user.value as { avatar_url?: string | null }).avatar_url ?? null;
});
const onPickPhoto = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0];

    if (!file) {
return;
}

    removePhoto.value = false;
    photoPreview.value = URL.createObjectURL(file);
};
</script>

<template>
    <Head title="Profile settings" />

    <h1 class="sr-only">Profile settings</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Profile"
            description="Update your name and email address"
        />

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <!-- Profile photo -->
            <div class="grid gap-2">
                <Label>Profile photo</Label>
                <div class="flex items-center gap-4">
                    <span class="grid size-16 flex-none place-items-center overflow-hidden rounded-full text-lg font-bold text-white" style="background: linear-gradient(135deg, #CC1D79 0%, #06B7AD 100%)">
                        <img v-if="currentAvatar" :src="currentAvatar" alt="Profile photo" class="size-full object-cover" />
                        <span v-else>{{ initials }}</span>
                    </span>
                    <div class="flex flex-wrap items-center gap-2">
                        <label class="cursor-pointer rounded-xl border border-border px-3 py-2 text-sm font-semibold hover:bg-muted">
                            {{ currentAvatar ? 'Change photo' : 'Upload photo' }}
                            <input type="file" name="photo" accept="image/png,image/jpeg,image/webp" class="hidden" @change="onPickPhoto" />
                        </label>
                        <button v-if="currentAvatar" type="button" class="rounded-xl px-3 py-2 text-sm font-semibold text-[#CC1D79] hover:bg-[#CC1D79]/10" @click="removePhoto = true; photoPreview = null">
                            Remove
                        </button>
                    </div>
                </div>
                <input v-if="removePhoto" type="hidden" name="remove_photo" value="1" />
                <p class="text-xs text-muted-foreground">JPG, PNG or WEBP · up to 4 MB.</p>
                <InputError class="mt-1" :message="errors.photo" />
            </div>

            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    placeholder="Full name"
                />
                <InputError class="mt-2" :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    name="email"
                    :default-value="user.email"
                    required
                    autocomplete="username"
                    placeholder="Email address"
                />
                <InputError class="mt-2" :message="errors.email" />
            </div>

            <div v-if="page.props.mustVerifyEmail && !user.email_verified_at">
                <p class="-mt-4 text-sm text-muted-foreground">
                    Your email address is unverified.
                    <Link
                        :href="send()"
                        as="button"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                    >
                        Click here to re-send the verification email.
                    </Link>
                </p>

                <div
                    v-if="page.props.status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    A new verification link has been sent to your email address.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <Button :disabled="processing" data-test="update-profile-button"
                    >Save</Button
                >
            </div>
        </Form>
    </div>

    <DeleteUser />
</template>
