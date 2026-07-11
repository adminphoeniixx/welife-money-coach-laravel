<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Settings {
    app_name: string;
    support_email: string;
    default_currency: string;
    default_country: string;
    free_max_loans: number;
    free_max_cards: number;
    free_max_budgets: number;
    registration_enabled: boolean;
    maintenance_mode: boolean;
}

const props = defineProps<{ settings: Settings }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'Settings', href: '/admin/settings' },
        ],
    },
});

const form = useForm<Settings>({ ...props.settings });

function submit() {
    form.patch('/admin/settings', { preserveScroll: true });
}
</script>

<template>
    <Head title="Settings" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            title="Platform settings"
            description="Branding, freemium limits, localisation and feature flags."
        />

        <form class="flex flex-col gap-6" @submit.prevent="submit">
            <div class="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader><CardTitle>Branding</CardTitle></CardHeader>
                    <CardContent class="grid gap-4">
                        <div class="grid gap-2">
                            <Label for="app_name">App name</Label>
                            <Input id="app_name" v-model="form.app_name" />
                            <InputError :message="form.errors.app_name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="support_email">Support email</Label>
                            <Input id="support_email" v-model="form.support_email" type="email" />
                            <InputError :message="form.errors.support_email" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Localisation</CardTitle></CardHeader>
                    <CardContent class="grid gap-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label for="default_currency">Default currency</Label>
                                <Input id="default_currency" v-model="form.default_currency" maxlength="3" />
                                <InputError :message="form.errors.default_currency" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="default_country">Default country</Label>
                                <Input id="default_country" v-model="form.default_country" maxlength="2" />
                                <InputError :message="form.errors.default_country" />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Free plan limits</CardTitle></CardHeader>
                    <CardContent class="grid grid-cols-3 gap-4">
                        <div class="grid gap-2">
                            <Label for="free_max_loans">Loans</Label>
                            <Input id="free_max_loans" v-model.number="form.free_max_loans" type="number" min="0" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="free_max_cards">Cards</Label>
                            <Input id="free_max_cards" v-model.number="form.free_max_cards" type="number" min="0" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="free_max_budgets">Budgets</Label>
                            <Input id="free_max_budgets" v-model.number="form.free_max_budgets" type="number" min="0" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Feature flags</CardTitle></CardHeader>
                    <CardContent class="grid gap-4">
                        <label class="flex items-start gap-3">
                            <Checkbox id="registration_enabled" v-model="form.registration_enabled" />
                            <span>
                                <span class="block text-sm font-medium">Registration enabled</span>
                                <span class="block text-xs text-muted-foreground">Allow new users to sign up.</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3">
                            <Checkbox id="maintenance_mode" v-model="form.maintenance_mode" />
                            <span>
                                <span class="block text-sm font-medium">Maintenance mode</span>
                                <span class="block text-xs text-muted-foreground">Show a maintenance notice to users.</span>
                            </span>
                        </label>
                    </CardContent>
                </Card>
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">Save settings</Button>
                <span v-if="form.recentlySuccessful" class="text-sm text-muted-foreground">Saved.</span>
            </div>
        </form>
    </div>
</template>
