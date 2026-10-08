<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\PasswordChangedByAdmin;
use App\Notifications\PasswordResetByAdmin;
use App\Services\AdminPasswordService;
use App\Services\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\TestCase;

class AdminPasswordManagementTest extends TestCase
{
    private const NEW = 'correct horse battery staple';

    private const REASON = 'User locked out after phone loss; identity verified by call.';

    private function confirmed(User $admin): static
    {
        return $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);
    }

    private function fakeSession(User $user, string $id): void
    {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'ip_address' => '10.0.0.1', 'user_agent' => 'Test', 'payload' => '', 'last_activity' => time()]);
    }

    /** Nothing secret may ever reach the audit log. */
    private function assertAuditHasNoSecrets(User $user, string ...$secrets): void
    {
        $json = AuditLog::all()->toJson();
        foreach ([...$secrets, $user->fresh()->password, $user->fresh()->remember_token] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
    }

    public function test_admins_without_the_permission_are_refused_and_it_is_audited(): void
    {
        $alumniAdmin = $this->admin(RoleName::AlumniAdmin); // users.manage, but not users.password.manage
        $user = $this->verifiedAlumnus()->user;
        $hash = $user->password;

        $this->confirmed($alumniAdmin)->put(route('admin.users.password.change', $user), ['password' => self::NEW, 'password_confirmation' => self::NEW, 'reason' => self::REASON])->assertForbidden();
        $this->confirmed($alumniAdmin)->post(route('admin.users.password.reset', $user), ['reason' => self::REASON])->assertForbidden();

        $this->assertSame($hash, $user->fresh()->password);
        $this->assertSame(2, AuditLog::where('status', 'denied')->where('entity_id', $user->id)->whereIn('action', [AdminPasswordService::CHANGED, AdminPasswordService::RESET])->count());
        $this->actingAs($alumniAdmin)->get(route('admin.users.index'))->assertInertia(fn (Assert $p) => $p->where('users.data.0.can_manage_password', false));
    }

    public function test_change_password_hashes_revokes_sessions_notifies_and_audits(): void
    {
        Notification::fake();
        $admin = $this->admin(RoleName::SuperAdmin);
        $user = $this->verifiedAlumnus()->user;
        $this->fakeSession($user, 'their-laptop');
        $remember = $user->remember_token;

        $this->confirmed($admin)->put(route('admin.users.password.change', $user), ['password' => self::NEW, 'password_confirmation' => self::NEW, 'require_change' => true, 'reason' => self::REASON])
            ->assertRedirect()->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertTrue(Hash::check(self::NEW, $user->password));
        $this->assertNotSame(self::NEW, $user->password);
        $this->assertTrue($user->password_change_required);
        $this->assertNotSame($remember, $user->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'their-laptop']);
        Notification::assertSentTo($user, PasswordChangedByAdmin::class);

        $entry = AuditLog::where('action', AdminPasswordService::CHANGED)->sole();
        $this->assertSame([$admin->id, 'User', $user->id, 'success', self::REASON], [$entry->user_id, $entry->entity_type, $entry->entity_id, $entry->status, $entry->reason]);
        $this->assertNotNull($entry->ip_address);
        $this->assertNotNull($entry->request_id);
        $this->assertAuditHasNoSecrets($user, self::NEW);
    }

    public function test_weak_or_unconfirmed_passwords_fail_and_the_failure_is_audited_without_the_password(): void
    {
        $admin = $this->admin(RoleName::SuperAdmin);
        $user = $this->verifiedAlumnus()->user;
        $hash = $user->password;

        $this->confirmed($admin)->put(route('admin.users.password.change', $user), ['password' => 'short', 'password_confirmation' => 'short', 'reason' => self::REASON])->assertSessionHasErrors('password');
        $this->confirmed($admin)->put(route('admin.users.password.change', $user), ['password' => self::NEW, 'password_confirmation' => 'different words here', 'reason' => self::REASON])->assertSessionHasErrors('password');
        $this->confirmed($admin)->put(route('admin.users.password.change', $user), ['password' => self::NEW, 'password_confirmation' => self::NEW])->assertSessionHasErrors('reason');

        $this->assertSame($hash, $user->fresh()->password);
        $this->assertSame(3, AuditLog::where('action', AdminPasswordService::CHANGED)->where('status', 'failed')->count());
        $this->assertAuditHasNoSecrets($user, self::NEW, 'short');
    }

    public function test_reset_invalidates_the_password_and_the_emailed_link_works(): void
    {
        Notification::fake();
        $admin = $this->admin(RoleName::SuperAdmin);
        $user = $this->verifiedAlumnus()->user;

        $this->confirmed($admin)->post(route('admin.users.password.reset', $user), ['reason' => self::REASON])->assertRedirect();

        $this->assertFalse(Hash::check('password', $user->fresh()->password), 'The old password must stop working.');
        $token = null;
        Notification::assertSentTo($user, PasswordResetByAdmin::class, function ($n) use (&$token, $user) {
            $mail = $n->toMail($user);
            $token = preg_match('#/reset-password/([^?]+)#', $mail->actionUrl, $m) ? $m[1] : null;

            return $token !== null && ! str_contains(implode(' ', $mail->introLines), 'password:');
        });
        $this->assertSame('success', AuditLog::where('action', AdminPasswordService::RESET)->sole()->status);
        $this->assertAuditHasNoSecrets($user, $token);
        auth()->logout();

        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => self::NEW, 'password_confirmation' => self::NEW])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check(self::NEW, $user->fresh()->password));
    }

    public function test_user_with_an_admin_set_password_must_choose_their_own(): void
    {
        $admin = $this->admin(RoleName::SuperAdmin);
        $user = $this->verifiedAlumnus()->user;
        $this->confirmed($admin)->put(route('admin.users.password.change', $user), ['password' => self::NEW, 'password_confirmation' => self::NEW, 'require_change' => true, 'reason' => self::REASON]);
        auth()->logout();

        $this->post('/login', ['email' => $user->email, 'password' => self::NEW]);
        $this->get(route('dashboard'))->assertRedirect(route('password.change-required'));
        $this->get(route('password.change-required'))->assertOk()->assertInertia(fn (Assert $p) => $p->component('Auth/PasswordChangeRequired'));

        $this->put('/user/password', ['current_password' => self::NEW, 'password' => self::NEW, 'password_confirmation' => self::NEW])->assertSessionHasErrorsIn('updatePassword', 'password');
        $this->put('/user/password', ['current_password' => self::NEW, 'password' => 'my very own secret phrase', 'password_confirmation' => 'my very own secret phrase'])->assertSessionHasNoErrors();

        $this->assertFalse($user->fresh()->password_change_required);
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_no_self_service_no_climbing_and_reauth_required(): void
    {
        $super = $this->admin(RoleName::SuperAdmin);
        $alumniAdmin = $this->admin(RoleName::AlumniAdmin);
        $alumniAdmin->givePermissionTo('users.password.manage');

        // Fresh password confirmation required (checked first: session state persists across requests in a test).
        $this->actingAs($super)->post(route('admin.users.password.reset', $this->verifiedAlumnus()->user), ['reason' => self::REASON])->assertRedirect(route('password.confirm'));
        // Own account: use the Security page instead.
        $this->confirmed($super)->post(route('admin.users.password.reset', $super), ['reason' => self::REASON])->assertForbidden();
        // Privilege ceiling: can't touch an account above your own level.
        $this->confirmed($alumniAdmin)->post(route('admin.users.password.reset', $super), ['reason' => self::REASON])->assertForbidden();
    }

    public function test_password_operations_are_rate_limited(): void
    {
        $admin = $this->admin(RoleName::SuperAdmin);
        $user = $this->verifiedAlumnus()->user;

        foreach (range(1, 5) as $i) {
            $this->confirmed($admin)->post(route('admin.users.password.reset', $user), ['reason' => self::REASON])->assertRedirect();
        }
        $this->confirmed($admin)->post(route('admin.users.password.reset', $user), ['reason' => self::REASON])->assertStatus(429);
    }

    public function test_audit_entries_are_immutable_but_retention_still_prunes(): void
    {
        $entry = app(AuditLogger::class)->record('test.entry', 'security');

        foreach ([fn () => $entry->update(['action' => 'tampered']), fn () => $entry->delete()] as $attempt) {
            try {
                $attempt();
                $this->fail('Model-level tampering must be refused.');
            } catch (LogicException) {
            }
        }
        foreach ([fn () => DB::table('audit_logs')->where('id', $entry->id)->update(['action' => 'tampered']), fn () => DB::table('audit_logs')->where('id', $entry->id)->delete()] as $attempt) {
            try {
                $attempt();
                $this->fail('Database-level tampering must be refused.');
            } catch (QueryException $e) {
                $this->assertStringContainsString('audit_logs', $e->getMessage());
            }
        }
        $this->assertSame('test.entry', $entry->fresh()->action);

        // Entries past the retention period can still be pruned.
        DB::table('audit_logs')->insert(['action' => 'ancient', 'module' => 'security', 'status' => 'success', 'created_at' => now()->subDays(config('security.audit_retention_days') + 5)]);
        $this->artisan('audit:prune')->assertSuccessful();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'ancient']);
        $this->assertDatabaseHas('audit_logs', ['id' => $entry->id]);
    }

    public function test_the_audit_screen_lists_password_events_without_secrets(): void
    {
        $admin = $this->admin(RoleName::SuperAdmin);
        $user = $this->verifiedAlumnus()->user;
        $this->confirmed($admin)->put(route('admin.users.password.change', $user), ['password' => self::NEW, 'password_confirmation' => self::NEW, 'reason' => self::REASON]);

        $this->actingAs($admin)->get(route('admin.audit-logs.index', ['preset' => 'passwords']))
            ->assertInertia(fn (Assert $p) => $p
                ->where('logs.data.0.action', AdminPasswordService::CHANGED)
                ->where('logs.data.0.label', 'Password changed by administrator')
                ->where('logs.data.0.subject', $user->name)
                ->where('logs.data.0.status', 'success')
                ->where('logs.data.0.reason', self::REASON));
        $this->assertStringNotContainsString(self::NEW, $this->actingAs($admin)->get(route('admin.audit-logs.index'))->getContent());
    }
}
