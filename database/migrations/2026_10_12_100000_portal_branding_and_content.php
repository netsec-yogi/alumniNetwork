<?php

use App\Enums\RoleName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Portal branding and landing-page text, editable by administrators.
 *
 * Drafts and the published version live in site_settings (the existing
 * key → JSON store); this table keeps every published version so an earlier
 * one can be restored. The two new permissions go to the super administrator
 * only. Other roles get them deliberately, not by default.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['portal.branding.manage', 'landing-page.content.manage'];

    public function up(): void
    {
        Schema::create('content_revisions', function (Blueprint $table) {
            $table->id();
            $table->string('area', 40);
            $table->json('values');
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->index(['area', 'id']);
        });

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }
        Role::where('name', RoleName::SuperAdmin->value)->where('guard_name', 'web')->first()?->givePermissionTo(self::PERMISSIONS);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('content_revisions');
        Permission::whereIn('name', self::PERMISSIONS)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
