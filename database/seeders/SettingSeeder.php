<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Seed default platform settings.
     */
    public function run(): void
    {
        $defaults = [
            'app_name' => 'MoneyCoach',
            'support_email' => 'support@moneycoach.test',
            'default_currency' => 'USD',
            'default_country' => 'US',
            'free_max_loans' => '2',
            'free_max_cards' => '2',
            'free_max_budgets' => '3',
            'registration_enabled' => '1',
            'maintenance_mode' => '0',
        ];

        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
