<?php

namespace Tests\Feature;

use App\Models\ContentItem;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSectionsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function plan(int $priceCents = 499, string $interval = 'month'): Plan
    {
        return Plan::create([
            'name' => 'Premium',
            'slug' => 'premium-'.uniqid(),
            'price_cents' => $priceCents,
            'currency' => 'USD',
            'interval' => $interval,
            'is_active' => true,
        ]);
    }

    // --- Subscriptions ---------------------------------------------------

    public function test_admin_can_view_subscriptions(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('admin.subscriptions.index'))->assertOk();
    }

    public function test_admin_can_assign_a_plan_and_it_creates_a_transaction(): void
    {
        $this->actingAs($this->admin());
        $user = User::factory()->create();
        $plan = $this->plan(999);

        $this->post(route('admin.subscriptions.store'), [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'price_cents' => 999,
        ]);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'amount_cents' => 999,
            'status' => 'paid',
        ]);
    }

    public function test_admin_can_cancel_and_reactivate_a_subscription(): void
    {
        $this->actingAs($this->admin());
        $sub = Subscription::create([
            'user_id' => User::factory()->create()->id,
            'plan_id' => $this->plan()->id,
            'status' => 'active',
            'price_cents' => 499,
            'currency' => 'USD',
            'interval' => 'month',
        ]);

        $this->patch(route('admin.subscriptions.cancel', $sub))->assertRedirect();
        $this->assertSame('cancelled', $sub->fresh()->status);

        $this->patch(route('admin.subscriptions.reactivate', $sub))->assertRedirect();
        $this->assertSame('active', $sub->fresh()->status);
    }

    public function test_mrr_normalises_yearly_plans(): void
    {
        $this->actingAs($this->admin());
        Subscription::create([
            'user_id' => User::factory()->create()->id,
            'plan_id' => $this->plan(1200, 'year')->id,
            'status' => 'active',
            'price_cents' => 1200,
            'currency' => 'USD',
            'interval' => 'year',
        ]);

        // 1200 / 12 = 100 monthly cents.
        $this->assertSame(100, Subscription::first()->monthlyCents());
    }

    // --- Content ---------------------------------------------------------

    public function test_admin_can_manage_content(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('admin.content.index'))->assertOk();

        $this->post(route('admin.content.store'), [
            'type' => 'faq',
            'title' => 'How does it work?',
            'body' => 'Like this.',
            'is_published' => true,
        ])->assertRedirect();

        $item = ContentItem::where('slug', 'how-does-it-work')->first();
        $this->assertNotNull($item);
        $this->assertTrue($item->is_published);
        $this->assertNotNull($item->published_at);

        $this->patch(route('admin.content.update', $item), [
            'type' => 'faq',
            'title' => 'How does it work?',
            'body' => 'Updated.',
            'is_published' => false,
        ])->assertRedirect();
        $this->assertSame('Updated.', $item->fresh()->body);
        $this->assertNull($item->fresh()->published_at);

        $this->delete(route('admin.content.destroy', $item))->assertRedirect();
        $this->assertDatabaseMissing('content_items', ['id' => $item->id]);
    }

    // --- Settings --------------------------------------------------------

    public function test_admin_can_view_and_update_settings(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('admin.settings.index'))->assertOk();

        $this->patch(route('admin.settings.update'), [
            'app_name' => 'MyMoney',
            'support_email' => 'help@my.test',
            'default_currency' => 'inr',
            'default_country' => 'in',
            'free_max_loans' => 5,
            'free_max_cards' => 4,
            'free_max_budgets' => 6,
            'registration_enabled' => true,
            'maintenance_mode' => false,
        ])->assertRedirect();

        $this->assertDatabaseHas('settings', ['key' => 'app_name', 'value' => 'MyMoney']);
        $this->assertDatabaseHas('settings', ['key' => 'default_currency', 'value' => 'INR']);
        $this->assertDatabaseHas('settings', ['key' => 'free_max_loans', 'value' => '5']);
        $this->assertSame('IN', Setting::map()['default_country']);
    }

    // --- Access control --------------------------------------------------

    public function test_non_admin_cannot_reach_new_sections(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $this->get(route('admin.subscriptions.index'))->assertForbidden();
        $this->get(route('admin.content.index'))->assertForbidden();
        $this->get(route('admin.settings.index'))->assertForbidden();
    }
}
