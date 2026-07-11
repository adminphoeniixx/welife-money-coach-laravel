<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Seed default subscription tiers (free + premium).
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'Core expense tracking, reminders and debt coach.',
                'price_cents' => 0,
                'currency' => 'USD',
                'interval' => 'month',
                'features' => [
                    'Income & expense tracking',
                    'Up to 2 loans and 2 cards',
                    'Snowball / Avalanche debt coach',
                    'Due-date reminders',
                ],
                'is_active' => true,
                'sort_order' => 0,
            ],
            [
                'name' => 'Premium Monthly',
                'slug' => 'premium-monthly',
                'description' => 'Unlimited accounts, advanced insights and priority support.',
                'price_cents' => 499,
                'currency' => 'USD',
                'interval' => 'month',
                'features' => [
                    'Unlimited loans, cards & budgets',
                    'Advanced debt scenarios',
                    'Smart spending alerts',
                    'Scheduled exports',
                    'Priority, ad-free support',
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Premium Yearly',
                'slug' => 'premium-yearly',
                'description' => 'Everything in Premium, billed yearly at a discount.',
                'price_cents' => 4999,
                'currency' => 'USD',
                'interval' => 'year',
                'features' => [
                    'All Premium Monthly features',
                    '2 months free vs monthly',
                    'Multi-currency wallets',
                ],
                'is_active' => true,
                'sort_order' => 2,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
