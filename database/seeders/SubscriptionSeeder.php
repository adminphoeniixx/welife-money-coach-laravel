<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class SubscriptionSeeder extends Seeder
{
    /**
     * Seed a demo subscription + transaction so the admin UI is not empty.
     */
    public function run(): void
    {
        $user = User::where('email', 'test@example.com')->first();
        $plan = Plan::where('slug', 'premium-monthly')->first();

        if (! $user || ! $plan) {
            return;
        }

        $subscription = Subscription::firstOrCreate(
            ['user_id' => $user->id, 'plan_id' => $plan->id],
            [
                'status' => 'active',
                'price_cents' => $plan->price_cents,
                'currency' => $plan->currency,
                'interval' => $plan->interval,
                'started_at' => Carbon::now()->subDays(10),
                'ends_at' => Carbon::now()->addDays(20),
            ],
        );

        Transaction::firstOrCreate(
            ['reference' => 'seed-demo-0001'],
            [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'plan_id' => $plan->id,
                'amount_cents' => $plan->price_cents,
                'currency' => $plan->currency,
                'status' => 'paid',
                'paid_at' => Carbon::now()->subDays(10),
            ],
        );
    }
}
