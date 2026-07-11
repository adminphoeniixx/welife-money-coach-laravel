<?php

namespace Database\Seeders;

use App\Models\ContentItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ContentSeeder extends Seeder
{
    /**
     * Seed default content: announcement, FAQs and legal pages.
     */
    public function run(): void
    {
        $items = [
            ['type' => 'announcement', 'title' => 'Welcome to MoneyCoach', 'body' => 'Track your income, expenses, loans and cards — and never miss a due date. This is a launch announcement placeholder.', 'is_published' => true],
            ['type' => 'faq', 'title' => 'How do I add an expense?', 'body' => 'Use Quick Add on the dashboard, pick a category, enter the amount and save.', 'is_published' => true],
            ['type' => 'faq', 'title' => 'What is the debt coach?', 'body' => 'It ranks your debts using the Snowball or Avalanche method and estimates a debt-free date.', 'is_published' => true],
            ['type' => 'faq', 'title' => 'Can I export my data?', 'body' => 'Yes — you can export your data to CSV or PDF from the Reports section.', 'is_published' => false],
            ['type' => 'legal', 'title' => 'Privacy Policy', 'body' => 'This placeholder Privacy Policy describes how MoneyCoach handles your data. Replace before launch.', 'is_published' => true],
            ['type' => 'legal', 'title' => 'Terms of Service', 'body' => 'This placeholder Terms of Service governs use of MoneyCoach. Replace before launch.', 'is_published' => true],
        ];

        foreach ($items as $i => $item) {
            ContentItem::updateOrCreate(
                ['type' => $item['type'], 'slug' => Str::slug($item['title'])],
                [
                    'title' => $item['title'],
                    'body' => $item['body'],
                    'is_published' => $item['is_published'],
                    'published_at' => $item['is_published'] ? Carbon::now() : null,
                    'sort_order' => $i,
                ],
            );
        }
    }
}
