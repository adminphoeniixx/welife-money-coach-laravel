<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Reference data the app needs in every environment, production
        // included: category templates, plans, CMS content, settings.
        $this->call([
            CategoryTemplateSeeder::class,
            PlanSeeder::class,
            ContentSeeder::class,
            SettingSeeder::class,
        ]);

        if (! $this->demoDataEnabled()) {
            $this->command?->info('Skipping demo accounts and demo finance data (set MONEYCOACH_DEMO_SEED=true to include them).');

            return;
        }

        // --- Demo data below. Never seeded in production: a real account must
        // --- only ever see its own figures, never sample ones.

        // Defined explicitly (no factory) so seeding also works in images
        // where faker is a dev-only dependency.
        $testUser = User::firstOrNew(['email' => 'test@example.com']);
        $testUser->name = 'Test User';
        $testUser->password = Hash::make('password');
        $testUser->email_verified_at = now();
        $testUser->save();

        // is_admin is intentionally not mass-assignable, so set it explicitly.
        $admin = User::firstOrNew(['email' => 'admin@moneycoach.test']);
        $admin->name = 'MoneyCoach Admin';
        $admin->password = Hash::make('password');
        $admin->is_admin = true;
        $admin->email_verified_at = now();
        $admin->save();

        $this->call([
            SubscriptionSeeder::class,
            DataRequestSeeder::class,
            FinanceDemoSeeder::class,
            VaultDemoSeeder::class,
            // After FinanceDemoSeeder — it wipes the demo user's entries.
            FamilyDemoSeeder::class,
        ]);
    }

    /**
     * Demo data is opt-in and never runs in production, so a live deploy's
     * `db:seed` can install reference data without inventing any user's money.
     */
    private function demoDataEnabled(): bool
    {
        return (bool) env('MONEYCOACH_DEMO_SEED', ! app()->environment('production'));
    }
}
