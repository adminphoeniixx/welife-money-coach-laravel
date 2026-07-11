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
        // Defined explicitly (no factory) so seeding also works in production
        // images where faker is a dev-only dependency.
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
            CategoryTemplateSeeder::class,
            PlanSeeder::class,
            SubscriptionSeeder::class,
            ContentSeeder::class,
            SettingSeeder::class,
            DataRequestSeeder::class,
        ]);
    }
}
