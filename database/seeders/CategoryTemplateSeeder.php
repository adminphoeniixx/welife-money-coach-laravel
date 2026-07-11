<?php

namespace Database\Seeders;

use App\Models\CategoryTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategoryTemplateSeeder extends Seeder
{
    /**
     * Seed the default income/expense category templates from the feature plan.
     */
    public function run(): void
    {
        $income = [
            'Salary', 'Business', 'Freelance', 'Rent', 'Interest', 'Dividends',
            'Investments', 'Pension', 'Benefits', 'Bonus', 'Gifts', 'Cashback',
            'Refund', 'Other',
        ];

        $expense = [
            'Housing' => ['Rent', 'Mortgage', 'Property Tax', 'Maintenance'],
            'Utilities' => ['Electricity', 'Water', 'Gas', 'Internet', 'Mobile'],
            'Food' => ['Groceries', 'Dining Out', 'Delivery'],
            'Transport' => ['Fuel', 'Public Transport', 'Ride Hailing', 'Vehicle Service'],
            'Loans & Cards' => ['Loan EMI', 'Credit Card Payment'],
            'Insurance' => ['Health Insurance', 'Life Insurance', 'Vehicle Insurance'],
            'Healthcare' => ['Doctor', 'Pharmacy', 'Hospital'],
            'Education' => ['Tuition', 'Books', 'Courses'],
            'Personal' => ['Clothing', 'Grooming', 'Subscriptions'],
            'Entertainment' => ['Streaming', 'Events', 'Travel'],
            'Investments' => ['Mutual Funds', 'Stocks', 'Retirement'],
            'Taxes' => ['Income Tax', 'Other Taxes'],
            'Business' => ['Supplies', 'Software', 'Marketing'],
            'Miscellaneous' => ['Other'],
        ];

        $sort = 0;
        foreach ($income as $name) {
            CategoryTemplate::updateOrCreate(
                ['type' => 'income', 'slug' => Str::slug($name)],
                ['name' => $name, 'group' => null, 'is_active' => true, 'sort_order' => $sort++],
            );
        }

        $sort = 0;
        foreach ($expense as $group => $items) {
            foreach ($items as $name) {
                CategoryTemplate::updateOrCreate(
                    ['type' => 'expense', 'slug' => Str::slug("{$group}-{$name}")],
                    ['name' => $name, 'group' => $group, 'is_active' => true, 'sort_order' => $sort++],
                );
            }
        }
    }
}
