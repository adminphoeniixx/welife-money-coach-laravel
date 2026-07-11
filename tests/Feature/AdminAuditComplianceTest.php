<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DataRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuditComplianceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    // --- Audit log -------------------------------------------------------

    public function test_admin_actions_are_recorded_in_the_audit_log(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();

        $this->actingAs($admin)->patch(route('admin.users.toggle-suspend', $target));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'admin.users.toggle-suspend',
            'subject_type' => 'User',
            'subject_id' => $target->id,
        ]);
    }

    public function test_read_only_requests_are_not_logged(): void
    {
        $this->actingAs($this->admin())->get(route('admin.users.index'))->assertOk();

        $this->assertSame(0, AuditLog::count());
    }

    public function test_admin_can_view_the_audit_log(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('admin.audit.index'))->assertOk();
    }

    // --- Compliance ------------------------------------------------------

    public function test_admin_can_view_compliance(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('admin.compliance.index'))->assertOk();
    }

    public function test_completing_an_export_request_marks_it_resolved(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $req = DataRequest::create([
            'user_id' => $user->id,
            'user_email' => $user->email,
            'type' => 'export',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->patch(route('admin.compliance.complete', $req))->assertRedirect();

        $req->refresh();
        $this->assertSame('completed', $req->status);
        $this->assertSame($admin->id, $req->resolved_by);
        $this->assertNotNull($req->resolved_at);
        // Export must NOT delete the account.
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_completing_a_deletion_request_removes_the_account(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $req = DataRequest::create([
            'user_id' => $user->id,
            'user_email' => $user->email,
            'type' => 'deletion',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->patch(route('admin.compliance.complete', $req))->assertRedirect();

        $this->assertSame('completed', $req->fresh()->status);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_admin_can_reject_a_request(): void
    {
        $this->actingAs($this->admin());
        $req = DataRequest::create([
            'user_email' => 'ex@example.com',
            'type' => 'export',
            'status' => 'pending',
        ]);

        $this->patch(route('admin.compliance.reject', $req), ['note' => 'Not verified'])->assertRedirect();

        $req->refresh();
        $this->assertSame('rejected', $req->status);
        $this->assertSame('Not verified', $req->note);
    }

    public function test_non_admin_cannot_reach_audit_or_compliance(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $this->get(route('admin.audit.index'))->assertForbidden();
        $this->get(route('admin.compliance.index'))->assertForbidden();
    }
}
