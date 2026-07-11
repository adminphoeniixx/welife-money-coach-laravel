<?php

namespace Tests\Feature;

use App\Models\CategoryTemplate;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_guests_are_redirected_from_admin(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_non_admins_are_forbidden(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->get(route('admin.users.index'))->assertForbidden();
        $this->get(route('admin.categories.index'))->assertForbidden();
        $this->get(route('admin.plans.index'))->assertForbidden();
    }

    public function test_admins_can_view_every_admin_page(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('admin.users.index'))->assertOk();
        $this->get(route('admin.categories.index'))->assertOk();
        $this->get(route('admin.plans.index'))->assertOk();
    }

    public function test_admin_can_toggle_another_users_role(): void
    {
        $this->actingAs($this->admin());
        $target = User::factory()->create(['is_admin' => false]);

        $this->patch(route('admin.users.toggle-admin', $target))->assertRedirect();

        $this->assertTrue($target->fresh()->is_admin);
    }

    public function test_admin_cannot_toggle_their_own_role(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->patch(route('admin.users.toggle-admin', $admin))->assertRedirect();

        $this->assertTrue($admin->fresh()->is_admin);
    }

    public function test_admin_can_create_a_category_template(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('admin.categories.store'), [
            'type' => 'expense',
            'name' => 'Pet Care',
            'group' => 'Family',
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('category_templates', [
            'type' => 'expense',
            'name' => 'Pet Care',
            'slug' => 'pet-care',
        ]);
    }

    public function test_admin_can_create_a_plan_with_features(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('admin.plans.store'), [
            'name' => 'Team',
            'description' => 'For families',
            'price' => 9.99,
            'currency' => 'usd',
            'interval' => 'month',
            'features' => ['Shared budgets', 'Roles'],
            'is_active' => true,
        ])->assertRedirect();

        $plan = Plan::where('slug', 'team')->first();
        $this->assertNotNull($plan);
        $this->assertSame(999, $plan->price_cents);
        $this->assertSame('USD', $plan->currency);
        $this->assertSame(['Shared budgets', 'Roles'], $plan->features);
    }

    public function test_admin_can_delete_a_category(): void
    {
        $this->actingAs($this->admin());
        $category = CategoryTemplate::create([
            'type' => 'income', 'name' => 'Temp', 'slug' => 'temp', 'is_active' => true,
        ]);

        $this->delete(route('admin.categories.destroy', $category))->assertRedirect();

        $this->assertDatabaseMissing('category_templates', ['id' => $category->id]);
    }
}
