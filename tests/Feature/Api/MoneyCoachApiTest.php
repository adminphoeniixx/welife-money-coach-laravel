<?php

namespace Tests\Feature\Api;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MoneyCoachApiTest extends TestCase
{
    use RefreshDatabase;

    /** Register/login and return a real Bearer token string. */
    private function tokenFor(User $user): string
    {
        return $user->createToken('test-device')->plainTextToken;
    }

    // --- Auth ---------------------------------------------------------------

    public function test_register_returns_a_token_and_user(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Rahul Sharma',
            'email' => 'rahul@example.com',
            'password' => 'Password!234',
            'password_confirmation' => 'Password!234',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['id', 'email', 'currency', 'has_vault_pin']]);
        $this->assertDatabaseHas('users', ['email' => 'rahul@example.com']);
    }

    public function test_login_with_valid_credentials_returns_token(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret1234')]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret1234',
        ])->assertOk()->assertJsonStructure(['token', 'user']);
    }

    public function test_login_with_bad_credentials_is_rejected(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret1234')]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong',
        ])->assertStatus(422);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    // --- Onboarding ---------------------------------------------------------

    public function test_onboarding_persists_preferences(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);
        $this->postJson('/api/onboarding', [
            'currency' => 'USD',
            'primary_goal' => 'get_out_of_debt',
            'notifications_enabled' => true,
        ])->assertOk();

        $user->refresh();
        $this->assertSame('USD', $user->currency);
        $this->assertTrue($user->onboarded);
    }

    // --- Transactions (CRUD, money conversion) ------------------------------

    public function test_entry_can_be_created_and_listed(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/entries', [
            'type' => 'expense', 'amount' => 640.50, 'category' => 'Food',
            'occurred_on' => now()->format('Y-m-d'),
        ])->assertCreated();

        $this->assertDatabaseHas('entries', ['user_id' => $user->id, 'amount_cents' => 64050]);

        $this->getJson('/api/transactions')
            ->assertOk()
            ->assertJsonPath('totals.expense', 640.5);
    }

    public function test_cannot_update_another_users_entry(): void
    {
        $owner = User::factory()->create();
        $entry = $owner->entries()->create([
            'type' => 'expense', 'amount_cents' => 1000, 'occurred_on' => now(),
        ]);

        Sanctum::actingAs(User::factory()->create());
        $this->putJson("/api/entries/{$entry->id}", [
            'type' => 'expense', 'amount' => 20, 'occurred_on' => now()->format('Y-m-d'),
        ])->assertForbidden();
    }

    // --- Debts + payment ----------------------------------------------------

    public function test_debt_payment_reduces_balance_and_closes_at_zero(): void
    {
        $user = User::factory()->create();
        $debt = $user->debts()->create([
            'name' => 'Card', 'kind' => 'credit_card', 'balance_cents' => 500000,
            'interest_rate' => 40, 'emi_cents' => 100000, 'status' => 'active',
        ]);

        Sanctum::actingAs($user);
        $this->postJson("/api/debts/{$debt->id}/payment", ['amount' => 5000])
            ->assertOk()->assertJsonPath('closed', true);

        $this->assertSame(0, $debt->fresh()->balance_cents);
        $this->assertSame('closed', $debt->fresh()->status);
    }

    public function test_debt_show_returns_detail_and_payment_history(): void
    {
        $user = User::factory()->create();
        $debt = $user->debts()->create([
            'name' => 'Home Loan', 'kind' => 'loan', 'balance_cents' => 50000000,
            'principal_cents' => 60000000, 'interest_rate' => 8.5, 'emi_cents' => 2500000,
            'total_emis' => 24, 'emis_paid' => 0, 'status' => 'active',
        ]);

        Sanctum::actingAs($user);
        $this->postJson("/api/debts/{$debt->id}/payment", ['amount' => 25000])->assertOk();

        $this->getJson("/api/debts/{$debt->id}")
            ->assertOk()
            ->assertJsonPath('debt.name', 'Home Loan')
            ->assertJsonCount(1, 'payments')
            ->assertJsonPath('payments.0.emi_number', 1)
            ->assertJsonPath('payments.0.balance_after', 475000);
    }

    public function test_cannot_view_another_users_debt(): void
    {
        $debt = User::factory()->create()->debts()->create([
            'name' => 'X', 'kind' => 'loan', 'balance_cents' => 1000,
            'interest_rate' => 5, 'status' => 'active',
        ]);

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/debts/{$debt->id}")->assertForbidden();
    }

    public function test_yearly_insights_aggregate_income_and_expense(): void
    {
        $user = User::factory()->create();
        $year = now()->year;
        $user->entries()->create(['type' => 'income', 'amount_cents' => 5000000, 'occurred_on' => "$year-03-01"]);
        $user->entries()->create(['type' => 'expense', 'amount_cents' => 2000000, 'category' => 'Food', 'occurred_on' => "$year-03-05"]);

        Sanctum::actingAs($user);
        $this->getJson("/api/insights?year=$year")
            ->assertOk()
            ->assertJsonPath('summary.income', 50000)
            ->assertJsonPath('summary.expense', 20000)
            ->assertJsonCount(12, 'by_month')
            ->assertJsonPath('by_category.0.category', 'Food');
    }

    // --- Dashboard / coach --------------------------------------------------

    public function test_dashboard_returns_a_coach_snapshot(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard')->assertOk()->assertJsonStructure(['health']);
    }

    // --- Net worth ----------------------------------------------------------

    public function test_assets_feed_net_worth(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/assets', ['name' => 'HDFC', 'type' => 'bank', 'balance' => 100000])
            ->assertCreated();

        $this->getJson('/api/net-worth')->assertOk()->assertJsonPath('summary.assets', 100000);
    }

    // --- Budgets & goals ----------------------------------------------------

    public function test_goal_contribution_marks_reached(): void
    {
        $user = User::factory()->create();
        $goal = $user->goals()->create([
            'name' => 'Emergency', 'type' => 'emergency_fund',
            'target_cents' => 100000, 'saved_cents' => 0,
        ]);

        Sanctum::actingAs($user);
        $this->postJson("/api/goals/{$goal->id}/contribute", ['amount' => 1000])
            ->assertOk()->assertJsonPath('reached', true);
    }

    // --- Reminders ----------------------------------------------------------

    public function test_reminder_can_be_created_and_appears_in_index(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/bills', [
            'name' => 'Electricity', 'kind' => 'bill', 'amount' => 1200,
            'due_date' => now()->addDays(5)->format('Y-m-d'),
            'repeat' => 'monthly', 'remind_days_before' => 2,
        ])->assertCreated();

        $this->getJson('/api/reminders')->assertOk()->assertJsonCount(1, 'upcoming');
    }

    // --- Insights: search, calendar, achievements, notifications, reports ----

    public function test_search_returns_matching_transactions(): void
    {
        $user = User::factory()->create();
        $user->entries()->create([
            'type' => 'expense', 'amount_cents' => 5000, 'description' => 'Netflix subscription',
            'occurred_on' => now(),
        ]);

        Sanctum::actingAs($user);
        $this->getJson('/api/search?q=Netflix')
            ->assertOk()->assertJsonPath('count', 1);
    }

    public function test_insights_endpoints_respond(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/calendar')->assertOk()->assertJsonStructure(['days']);
        $this->getJson('/api/achievements')->assertOk()->assertJsonStructure(['achievements', 'earned']);
        $this->getJson('/api/notifications')->assertOk()->assertJsonStructure(['notifications']);
        $this->getJson('/api/reports')->assertOk()->assertJsonStructure(['summary']);
        $this->getJson('/api/challenges')->assertOk()->assertJsonStructure(['active', 'presets']);
    }

    // --- Family -------------------------------------------------------------

    public function test_family_can_be_created_and_shared_expense_added(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/family', ['name' => 'Sharma Family'])->assertCreated();
        $this->assertTrue($user->fresh()->hasHousehold());

        $this->postJson('/api/family/expenses', [
            'category' => 'Groceries', 'amount' => 2500, 'occurred_on' => now()->format('Y-m-d'),
        ])->assertCreated();

        $this->getJson('/api/family')->assertOk()->assertJsonCount(1, 'expenses');
    }

    // --- Vault (real token so currentAccessToken() is a PersonalAccessToken) -

    public function test_vault_documents_are_gated_by_pin_and_encrypted(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        // Setting the PIN also unlocks the vault.
        $this->withToken($token)->postJson('/api/vault/pin', [
            'pin' => '1234', 'pin_confirmation' => '1234',
        ])->assertOk()->assertJsonPath('unlocked', true);

        // Upload a document (encrypted at rest).
        $upload = $this->withToken($token)->postJson('/api/vault/documents', [
            'category' => 'aadhaar',
            'title' => 'Aadhaar',
            'file' => UploadedFile::fake()->create('aadhaar.pdf', 40, 'application/pdf'),
        ]);
        $upload->assertCreated();

        $document = Document::firstOrFail();
        $stored = Storage::disk('local')->get($document->path);
        // Encrypted at rest: the blob on disk is ciphertext, not the raw file.
        $this->assertNotSame($stored, Crypt::decryptString($stored));

        $this->withToken($token)->getJson('/api/vault')->assertOk()->assertJsonPath('total', 1);

        // After locking, document routes return 423 Locked.
        $this->withToken($token)->postJson('/api/vault/lock')->assertOk();
        $this->withToken($token)->getJson('/api/vault')->assertStatus(423);
    }

    public function test_wrong_vault_pin_is_rejected(): void
    {
        $user = User::factory()->create(['vault_pin' => Hash::make('1234')]);
        $token = $this->tokenFor($user);

        $this->withToken($token)->postJson('/api/vault/unlock', ['pin' => '9999'])
            ->assertStatus(422);
    }

    // --- Settings & profile -------------------------------------------------

    public function test_region_and_notification_settings_save(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/settings/region', ['currency' => 'AED'])
            ->assertOk();
        $this->assertSame('AED', $user->fresh()->currency);

        $this->putJson('/api/settings/notifications', [
            'notifications_enabled' => true,
            'channels' => ['bill_reminders' => false],
        ])->assertOk();
        $this->assertFalse($user->fresh()->notification_prefs['bill_reminders']);
    }

    public function test_password_change_requires_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret1234')]);
        $token = $this->tokenFor($user);

        $this->withToken($token)->putJson('/api/password', [
            'current_password' => 'wrong',
            'password' => 'NewPass!234',
            'password_confirmation' => 'NewPass!234',
        ])->assertStatus(422);

        $this->withToken($token)->putJson('/api/password', [
            'current_password' => 'secret1234',
            'password' => 'NewPass!234',
            'password_confirmation' => 'NewPass!234',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPass!234', $user->fresh()->password));
    }

    public function test_account_deletion_requires_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret1234')]);
        $token = $this->tokenFor($user);

        $this->withToken($token)->deleteJson('/api/account', ['password' => 'secret1234'])
            ->assertOk();
        $this->assertModelMissing($user);
    }

    // --- Legal --------------------------------------------------------------

    public function test_legal_documents_are_public(): void
    {
        $this->getJson('/api/legal/privacy')->assertOk()->assertJsonStructure(['title', 'body']);
        $this->getJson('/api/legal/terms')->assertOk()->assertJsonStructure(['title', 'body']);
        $this->getJson('/api/legal/nonsense')->assertNotFound();
    }
}
