<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminUserSupportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_admin_can_view_a_user_detail_page(): void
    {
        $this->actingAs($this->admin());
        $target = User::factory()->create();

        $this->get(route('admin.users.show', $target))->assertOk();
    }

    public function test_admin_can_update_a_user_and_email_change_resets_verification(): void
    {
        $this->actingAs($this->admin());
        $target = User::factory()->create(['email' => 'old@example.com']);

        $this->patch(route('admin.users.update', $target), [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ])->assertRedirect();

        $target->refresh();
        $this->assertSame('New Name', $target->name);
        $this->assertSame('new@example.com', $target->email);
        $this->assertNull($target->email_verified_at);
    }

    public function test_admin_can_toggle_email_verification(): void
    {
        $this->actingAs($this->admin());
        $target = User::factory()->unverified()->create();

        $this->patch(route('admin.users.toggle-verified', $target))->assertRedirect();
        $this->assertNotNull($target->fresh()->email_verified_at);

        $this->patch(route('admin.users.toggle-verified', $target))->assertRedirect();
        $this->assertNull($target->fresh()->email_verified_at);
    }

    public function test_admin_can_send_a_password_reset_link(): void
    {
        Notification::fake();
        $this->actingAs($this->admin());
        $target = User::factory()->create();

        $this->post(route('admin.users.password-reset', $target))->assertRedirect();

        Notification::assertSentTo($target, ResetPassword::class);
    }

    public function test_admin_can_suspend_and_reinstate_a_user(): void
    {
        $this->actingAs($this->admin());
        $target = User::factory()->create();

        $this->patch(route('admin.users.toggle-suspend', $target))->assertRedirect();
        $this->assertNotNull($target->fresh()->suspended_at);

        $this->patch(route('admin.users.toggle-suspend', $target))->assertRedirect();
        $this->assertNull($target->fresh()->suspended_at);
    }

    public function test_admin_cannot_suspend_themselves(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->patch(route('admin.users.toggle-suspend', $admin))->assertRedirect();
        $this->assertNull($admin->fresh()->suspended_at);
    }

    public function test_suspended_user_is_logged_out_and_blocked(): void
    {
        $user = User::factory()->create(['suspended_at' => now()]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_admin_can_impersonate_and_return(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();

        // Start impersonating.
        $this->actingAs($admin)
            ->post(route('admin.users.impersonate', $target))
            ->assertRedirect(route('dashboard'));

        $this->assertSame($target->id, auth()->id());
        $this->assertEquals($admin->id, session('impersonator_id'));

        // Return to the admin account.
        $this->post(route('impersonate.stop'))
            ->assertRedirect(route('admin.users.index'));

        $this->assertSame($admin->id, auth()->id());
        $this->assertNull(session('impersonator_id'));
    }

    public function test_non_admin_cannot_reach_support_actions(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));
        $target = User::factory()->create();

        $this->get(route('admin.users.show', $target))->assertForbidden();
        $this->patch(route('admin.users.toggle-suspend', $target))->assertForbidden();
        $this->post(route('admin.users.impersonate', $target))->assertForbidden();
    }
}
