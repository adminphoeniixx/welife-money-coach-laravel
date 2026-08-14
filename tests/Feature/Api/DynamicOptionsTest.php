<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Support\Options;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The mobile app renders no business values of its own: every picker option,
 * card and list item must come from the API, with stable keys, ISO dates,
 * major-unit amounts and an id on anything editable.
 *
 * These tests lock that contract in place.
 */
class DynamicOptionsTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $user = User::factory()->create(['country' => 'IN', 'currency' => 'INR']);
        Sanctum::actingAs($user);

        return $user;
    }

    /** A user with one of everything, so no list under test comes back empty. */
    private function userWithData(): User
    {
        $user = $this->user();

        $user->entries()->create([
            'type' => 'expense', 'category' => 'Food', 'description' => 'Swiggy dinner',
            'payee' => 'Swiggy', 'method' => 'UPI', 'amount_cents' => 64050, 'occurred_on' => now(),
        ]);
        $user->entries()->create([
            'type' => 'income', 'category' => 'Salary', 'amount_cents' => 12000000, 'occurred_on' => now(),
        ]);
        $user->budgets()->create(['category' => 'Food', 'limit_cents' => 1500000]);
        $user->goals()->create([
            'name' => 'Emergency Fund', 'type' => 'emergency_fund',
            'target_cents' => 30000000, 'saved_cents' => 18500000, 'target_date' => now()->addYear(),
        ]);
        $user->debts()->create([
            'name' => 'HDFC Credit Card', 'institution' => 'HDFC', 'kind' => 'credit_card',
            'balance_cents' => 1840000, 'principal_cents' => 1840000, 'interest_rate' => 42,
            'credit_limit_cents' => 10000000, 'min_due_cents' => 420000, 'due_day' => 22, 'status' => 'active',
        ]);
        $user->bills()->create([
            'name' => 'Netflix', 'kind' => 'subscription', 'category' => 'Entertainment',
            'amount_cents' => 64900, 'due_date' => now()->addDays(3), 'repeat' => 'monthly',
            'remind_days_before' => 2, 'status' => 'upcoming',
        ]);
        $user->financeAccounts()->create(['name' => 'HDFC Savings', 'type' => 'bank', 'balance_cents' => 24035000]);

        return $user;
    }

    // --- The options catalogue ---------------------------------------------

    public function test_meta_options_serves_every_picker_list(): void
    {
        $this->user();

        $this->getJson('/api/meta/options')->assertOk()->assertJsonStructure([
            'transactions' => ['income_categories', 'expense_categories', 'payment_methods'],
            'assets' => ['types' => [['key', 'label']]],
            'debts' => ['loan_categories', 'kinds'],
            'planning' => ['goal_types' => [['key', 'label']], 'budget_categories'],
            'reminders' => ['kinds', 'repeat_options', 'remind_days_before'],
            'vault' => ['categories' => [['key', 'label']]],
            'family' => ['categories'],
            'onboarding' => ['goals' => [['key', 'label']]],
            'notifications' => ['channels' => [['key', 'label']]],
            'region' => ['currencies', 'countries', 'timezones', 'number_formats'],
        ]);
    }

    public function test_budget_categories_match_the_expense_categories(): void
    {
        $this->user();

        $options = $this->getJson('/api/meta/options')->assertOk();
        $planning = $this->getJson('/api/planning')->assertOk();

        $this->assertSame(
            $options->json('transactions.expense_categories'),
            $options->json('planning.budget_categories'),
        );
        $this->assertSame(
            $options->json('transactions.expense_categories'),
            $planning->json('budget_categories'),
        );
    }

    // --- Dashboard ----------------------------------------------------------

    public function test_dashboard_items_carry_ids_and_iso_dates(): void
    {
        $user = $this->userWithData();

        $response = $this->getJson('/api/dashboard')->assertOk();

        $this->assertSame($user->id, $response->json('user.id'));
        $this->assertNotNull($response->json('emergency_fund.id'));
        $this->assertNotNull($response->json('budgets.0.id'));
        $this->assertNotNull($response->json('upcoming.0.id'));
        $this->assertNotNull($response->json('debts.0.id'));
        $this->assertNotNull($response->json('priority.id'));

        // Dates are machine-readable; display copy lives in its own key.
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', (string) $response->json('upcoming.0.due_date'));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}$/', (string) $response->json('trend.0.month'));
        $this->assertNotNull($response->json('upcoming.0.when'));
    }

    public function test_dashboard_cards_use_the_same_keys_as_the_debts_screen(): void
    {
        $this->userWithData();

        $dashboard = $this->getJson('/api/dashboard')->assertOk()->json('debts.0');
        $card = $this->getJson('/api/debts')->assertOk()->json('cards.0');

        foreach (['credit_limit', 'min_due'] as $key) {
            $this->assertSame($card[$key], $dashboard[$key], "{$key} must match across endpoints.");
        }

        // `limit` was the old name for credit_limit — it must be gone.
        $this->assertArrayNotHasKey('limit', $dashboard);
        $this->assertArrayNotHasKey('limit', $card);
    }

    public function test_dashboard_tips_expose_a_message(): void
    {
        $user = $this->userWithData();
        $user->bills()->create([
            'name' => 'Electricity', 'kind' => 'bill', 'amount_cents' => 250000,
            'due_date' => now()->subDays(4), 'repeat' => 'monthly',
            'remind_days_before' => 2, 'status' => 'overdue',
        ]);

        $tips = $this->getJson('/api/dashboard')->assertOk()->json('tips');

        $this->assertNotEmpty($tips);
        $this->assertSame($tips[0]['text'], $tips[0]['message']);
    }

    // --- Screens ------------------------------------------------------------

    public function test_transactions_send_options_and_iso_group_dates(): void
    {
        $this->userWithData();

        $response = $this->getJson('/api/transactions')->assertOk();

        $this->assertSame(Options::paymentMethods(), $response->json('payment_methods'));
        $this->assertSame(Options::incomeCategories(), $response->json('categories.income'));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', (string) $response->json('groups.0.date'));
        $this->assertNotNull($response->json('groups.0.label'));
        $this->assertIsNumeric($response->json('groups.0.items.0.amount'));
    }

    public function test_debts_send_options_and_card_fields(): void
    {
        $this->userWithData();

        $response = $this->getJson('/api/debts')->assertOk();

        $this->assertSame(Options::loanCategories(), $response->json('loan_categories'));
        $this->assertSame(Options::debtKinds(), $response->json('kinds'));
        $this->assertIsNumeric($response->json('summary.progress'));
        $this->assertEqualsWithDelta(100000, $response->json('cards.0.credit_limit'), 0.001);
        $this->assertEqualsWithDelta(4200, $response->json('cards.0.min_due'), 0.001);
        $this->assertSame(22, $response->json('cards.0.due_day'));
    }

    public function test_net_worth_summary_matches_its_accounts(): void
    {
        $this->userWithData();

        $response = $this->getJson('/api/net-worth')->assertOk();

        $this->assertSame(Options::assetTypes(), $response->json('types'));
        $this->assertSame(
            array_sum(array_column($response->json('accounts'), 'balance')),
            $response->json('summary.assets'),
        );
        $this->assertNotNull($response->json('accounts.0.id'));
    }

    public function test_planning_sends_options_and_consistent_budget_keys(): void
    {
        $this->userWithData();

        $listed = $this->getJson('/api/planning')->assertOk()->json('budgets.0');

        $updated = $this->putJson('/api/budgets/'.$listed['id'], ['category' => 'Food', 'limit' => 20000])
            ->assertOk()->json('budget');

        // A budget looks the same wherever it comes from.
        $this->assertSame(array_keys($listed), array_keys($updated));
        $this->assertNotNull($this->getJson('/api/planning')->json('goals.0.id'));
    }

    public function test_reminders_send_options_and_iso_due_dates(): void
    {
        $this->userWithData();

        $response = $this->getJson('/api/reminders')->assertOk();

        $this->assertSame(Options::reminderKinds(), $response->json('kinds'));
        $this->assertSame(Options::repeatOptions(), $response->json('repeat_options'));
        $this->assertSame(Options::remindDaysBefore(), $response->json('remind_days_before_options'));

        $subscription = $response->json('subscriptions.0');
        $this->assertNotNull($subscription['id']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', (string) $subscription['due_date']);
        $this->assertSame('monthly', $subscription['repeat']);
    }

    public function test_a_one_time_reminder_is_accepted_and_does_not_roll_forward(): void
    {
        $user = $this->user();

        $id = $this->postJson('/api/bills', [
            'name' => 'Car insurance', 'kind' => 'bill', 'amount' => 12000,
            'due_date' => now()->addDays(5)->format('Y-m-d'), 'repeat' => 'one_time',
            'remind_days_before' => 3,
        ])->assertCreated()->json('bill.id');

        $this->postJson("/api/bills/{$id}/paid")->assertOk()->assertJsonPath('bill.status', 'paid');
        $this->assertSame('paid', $user->bills()->sole()->status);
    }

    public function test_insights_months_are_iso(): void
    {
        $this->userWithData();

        $response = $this->getJson('/api/insights?year='.now()->year)->assertOk();

        $this->assertCount(12, $response->json('by_month'));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}$/', (string) $response->json('by_month.0.month'));
        $this->assertNotNull($response->json('by_month.0.label'));
    }

    public function test_search_reports_a_count(): void
    {
        $this->userWithData();

        $this->getJson('/api/search?q=netflix')->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonStructure(['query', 'count', 'results' => ['transactions', 'debts', 'bills', 'assets']]);
    }

    public function test_challenge_mutations_return_the_challenge(): void
    {
        $this->user();

        $preset = $this->getJson('/api/challenges')->assertOk()->json('presets.0');
        $this->assertArrayHasKey('key', $preset);

        $created = $this->postJson('/api/challenges', ['key' => $preset['key']])->assertCreated();
        $id = $created->json('challenge.id');
        $this->assertNotNull($id);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', (string) $created->json('challenge.ends_on'));

        $progress = $this->postJson("/api/challenges/{$id}/progress", ['amount' => 500])
            ->assertOk()->json('challenge.progress');

        $this->assertEqualsWithDelta(500, $progress, 0.001);
    }

    public function test_vault_categories_carry_labels_and_counts(): void
    {
        $user = $this->user();
        $user->forceFill(['vault_pin' => bcrypt('1234')])->save();
        $this->postJson('/api/vault/unlock', ['pin' => '1234'])->assertOk();

        $response = $this->getJson('/api/vault?category=all&search=')->assertOk();

        $this->assertSame(Options::vaultCategories()[0]['key'], $response->json('categories.0.key'));
        $this->assertSame(0, $response->json('categories.0.count'));
        $this->assertSame([], $response->json('documents'));
    }

    public function test_family_keys_are_stable_without_a_household(): void
    {
        $this->user();

        $this->getJson('/api/family')->assertOk()
            ->assertJsonPath('household', null)
            ->assertJsonPath('expenses', [])
            ->assertJsonPath('budgets', [])
            ->assertJsonPath('can_manage', false)
            ->assertJsonPath('categories', Options::familyCategories());
    }

    // --- Profile & settings -------------------------------------------------

    public function test_profile_exposes_contact_and_region_preferences(): void
    {
        $this->user();

        $this->putJson('/api/profile', [
            'name' => 'Rahul', 'email' => 'rahul@example.com', 'phone' => '+919876543210',
        ])->assertOk()->assertJsonPath('profile.phone', '+919876543210');

        $this->getJson('/api/profile')->assertOk()->assertJsonStructure([
            'profile' => [
                'id', 'name', 'email', 'phone', 'currency', 'currency_symbol', 'locale',
                'country', 'timezone', 'number_format', 'notification_prefs',
                'has_vault_pin', 'has_household',
            ],
        ])->assertJsonPath('profile.timezone', 'Asia/Kolkata');
    }

    public function test_region_settings_offer_and_save_timezone_and_number_format(): void
    {
        $user = $this->user();

        $this->getJson('/api/settings/region')->assertOk()->assertJsonStructure([
            'currency', 'locale', 'country', 'timezone', 'number_format',
            'currencies', 'countries' => [['key', 'label']],
            'timezones' => [['key', 'label']], 'number_formats' => [['key', 'label']],
        ]);

        $this->putJson('/api/settings/region', [
            'country' => 'US', 'timezone' => 'America/New_York', 'number_format' => 'international',
        ])->assertOk();

        $user->refresh();
        $this->assertSame('USD', $user->currency);
        $this->assertSame('America/New_York', $user->timezone);
        $this->assertSame('international', $user->number_format);
    }

    public function test_notification_settings_list_their_channels(): void
    {
        $this->user();

        $this->getJson('/api/settings/notifications')->assertOk()
            ->assertJsonPath('available_channels', Options::notificationChannels())
            ->assertJsonPath('channels.bill_reminders', true);

        $this->putJson('/api/settings/notifications', [
            'notifications_enabled' => true,
            'channels' => ['bill_reminders' => false],
        ])->assertOk()->assertJsonPath('channels.bill_reminders', false);
    }

    public function test_onboarding_options_come_from_the_backend(): void
    {
        $this->user();

        $response = $this->getJson('/api/onboarding')->assertOk();

        $this->assertSame(Options::onboardingGoals(), $response->json('goals'));
        $this->assertContains('USD', $response->json('currencies'));
        $this->assertNotEmpty($response->json('countries'));
    }

    // --- Mutations return fresh data ---------------------------------------

    public function test_entry_mutations_return_the_entry_and_refreshed_totals(): void
    {
        $this->user();

        $created = $this->postJson('/api/entries', [
            'type' => 'expense', 'category' => 'Food', 'amount' => 640.50,
            'occurred_on' => now()->toDateString(),
        ])->assertCreated();

        $id = $created->json('entry.id');
        $this->assertNotNull($id);
        $this->assertEqualsWithDelta(640.50, $created->json('totals.expense'), 0.001);

        $deleted = $this->deleteJson("/api/entries/{$id}")->assertOk();

        $this->assertSame($id, $deleted->json('deleted_id'));
        $this->assertEqualsWithDelta(0, $deleted->json('totals.expense'), 0.001);
    }

    public function test_asset_and_debt_deletes_return_the_refreshed_summary(): void
    {
        $user = $this->userWithData();

        $assetId = $user->financeAccounts()->sole()->id;
        $deleted = $this->deleteJson("/api/assets/{$assetId}")->assertOk();
        $this->assertSame($assetId, $deleted->json('deleted_id'));
        $this->assertEqualsWithDelta(0, $deleted->json('summary.assets'), 0.001);

        $debtId = $user->debts()->sole()->id;
        $deleted = $this->deleteJson("/api/debts/{$debtId}")->assertOk();
        $this->assertSame($debtId, $deleted->json('deleted_id'));
        $this->assertEqualsWithDelta(0, $deleted->json('summary.total'), 0.001);
        $this->assertSame(0, $deleted->json('summary.count'));
    }

    public function test_every_delete_reports_the_id_it_removed(): void
    {
        $user = $this->userWithData();

        $cases = [
            '/api/budgets/'.$user->budgets()->sole()->id,
            '/api/goals/'.$user->goals()->sole()->id,
            '/api/bills/'.$user->bills()->sole()->id,
        ];

        foreach ($cases as $url) {
            $expected = (int) substr($url, (int) strrpos($url, '/') + 1);
            $this->deleteJson($url)->assertOk()->assertJsonPath('deleted_id', $expected);
        }
    }

    public function test_family_mutations_return_the_whole_screen(): void
    {
        $this->user();

        $created = $this->postJson('/api/family', ['name' => 'Sharma Family'])->assertCreated();
        $this->assertNotNull($created->json('household.id'));
        $this->assertSame([], $created->json('expenses'));

        $added = $this->postJson('/api/family/expenses', [
            'category' => 'Groceries', 'amount' => 1200, 'occurred_on' => now()->toDateString(),
        ])->assertCreated();

        // The snapshot already reflects the new expense — no refetch needed.
        $this->assertCount(1, $added->json('expenses'));
        $this->assertEqualsWithDelta(1200, $added->json('summary.expense'), 0.001);

        $removed = $this->deleteJson('/api/family/expenses/'.$added->json('id'))->assertOk();
        $this->assertSame([], $removed->json('expenses'));
        $this->assertEqualsWithDelta(0, $removed->json('summary.expense'), 0.001);
    }

    // --- Test-data cleanup --------------------------------------------------

    public function test_purge_command_removes_matching_records_only_with_force(): void
    {
        $user = User::factory()->create();
        $user->budgets()->create(['category' => 'Codex Budget', 'limit_cents' => 100]);
        $user->budgets()->create(['category' => 'Food', 'limit_cents' => 100]);

        $this->artisan('moneycoach:purge-test-data')->assertSuccessful();
        $this->assertSame(2, $user->budgets()->count());

        $this->artisan('moneycoach:purge-test-data --force')->assertSuccessful();
        $this->assertSame(['Food'], $user->budgets()->pluck('category')->all());
    }
}
