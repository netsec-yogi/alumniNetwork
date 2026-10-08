<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Administrator password management:
 *  - audit entries gain an outcome (success / failed / denied) and a reason;
 *  - audit entries become tamper-resistant at the database level: UPDATE is
 *    always refused, DELETE only for entries older than the retention period
 *    (so `audit:prune` keeps working) — this binds the application's own
 *    database account, not just the application code;
 *  - users can be required to choose a new password at their next sign-in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('status', 20)->default('success')->after('new_values');
            $table->string('reason', 500)->nullable()->after('status');
            $table->index(['action', 'created_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('password_change_required')->default(false)->after('password_changed_at');
        });

        if (DB::getDriverName() === 'mysql') {
            $days = max(1, (int) config('security.audit_retention_days'));
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs
                FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs is append-only'
            SQL);
            DB::unprepared(<<<SQL
                CREATE TRIGGER audit_logs_retention_only_delete BEFORE DELETE ON audit_logs
                FOR EACH ROW BEGIN
                    IF OLD.created_at >= NOW() - INTERVAL {$days} DAY THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs entries can only be removed after the retention period';
                    END IF;
                END
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_update');
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_retention_only_delete');
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('password_change_required'));
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['action', 'created_at']);
            $table->dropColumn(['status', 'reason']);
        });
    }
};
