<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('category')->index();
            $table->boolean('is_sensitive')->default(false);
            $table->timestamps();
        });
        Schema::create('permission_profile_permission', function (Blueprint $table) {
            $table->foreignId('permission_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_profile_id', 'permission_id'], 'profile_permission_primary');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('permission_profile_id')->nullable()->after('role')->constrained()->nullOnDelete();
            $table->timestamp('access_reviewed_at')->nullable();
            $table->timestamp('access_review_due_at')->nullable()->index();
        });
        Schema::create('user_permission_overrides', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->boolean('allowed');
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->primary(['user_id', 'permission_id']);
        });
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->uuid('correlation_id')->nullable()->index();
            $table->string('route')->nullable();
            $table->string('method', 10)->nullable();
        });
        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->index();
            $table->string('severity', 12)->default('info')->index();
            $table->uuid('correlation_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
        });
        Schema::create('system_health_checks', function (Blueprint $table) {
            $table->id();
            $table->string('component')->index();
            $table->string('status', 12)->index();
            $table->string('message');
            $table->json('metrics')->nullable();
            $table->timestamp('checked_at')->index();
            $table->timestamps();
        });
        Schema::create('backup_restorations', function (Blueprint $table) {
            $table->id();
            $table->string('backup_name');
            $table->string('status', 12)->index();
            $table->string('environment')->default('isolated');
            $table->text('details')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        $definitions = [
            ['manage-admin', 'Administrar configuración', 'administration', true],
            ['manage-access', 'Gestionar accesos', 'administration', true],
            ['export-audit', 'Exportar auditoría', 'administration', true],
            ['view-system-health', 'Ver fiabilidad del sistema', 'administration', true],
            ['manage-customers', 'Gestionar clientes', 'commercial', false],
            ['view-installations', 'Ver instalaciones', 'technical', false],
            ['manage-installations', 'Gestionar instalaciones', 'technical', false],
            ['sync-installations', 'Sincronizar instalaciones', 'technical', true],
            ['use-ai', 'Usar asistente IA', 'technical', false],
            ['manage-operations', 'Gestionar operaciones', 'technical', false],
            ['manage-catalog', 'Gestionar catálogo e inventario', 'purchasing', false],
            ['view-purchases', 'Ver compras', 'purchasing', false],
            ['manage-purchases', 'Gestionar compras', 'purchasing', false],
            ['receive-purchases', 'Recepcionar compras', 'purchasing', false],
            ['approve-purchase-invoices', 'Aprobar facturas de compra', 'purchasing', true],
            ['manage-purchase-payments', 'Gestionar pagos de compra', 'finance', true],
            ['view-finance', 'Ver finanzas', 'finance', true],
            ['manage-treasury', 'Gestionar tesorería', 'finance', true],
            ['manage-accounting', 'Gestionar contabilidad', 'finance', true],
            ['approve-payments', 'Aprobar pagos', 'finance', true],
            ['approve-budgets', 'Aprobar presupuestos', 'finance', true],
            ['manage-tax', 'Gestionar impuestos', 'finance', true],
            ['manage-staff', 'Gestionar RR. HH.', 'human_resources', true],
            ['view-sensitive-hr', 'Ver datos sensibles de RR. HH.', 'human_resources', true],
            ['manage-sensitive-hr', 'Gestionar datos sensibles de RR. HH.', 'human_resources', true],
            ['view-analytics', 'Ver analítica ejecutiva', 'administration', true],
        ];
        foreach ($definitions as [$key, $name, $category, $sensitive]) {
            DB::table('permissions')->insert(['key' => $key, 'name' => $name, 'category' => $category, 'is_sensitive' => $sensitive, 'created_at' => now(), 'updated_at' => now()]);
        }
        $profiles = [
            'administrator' => ['Administración', array_column($definitions, 0)],
            'commercial' => ['Comercial', ['manage-customers', 'view-installations', 'manage-installations', 'use-ai', 'manage-operations', 'manage-catalog', 'view-purchases', 'manage-purchases', 'receive-purchases', 'approve-purchase-invoices', 'view-finance', 'manage-treasury', 'manage-accounting', 'view-analytics']],
            'finance' => ['Finanzas', ['view-finance', 'manage-treasury', 'manage-accounting', 'approve-payments', 'approve-budgets', 'manage-tax', 'manage-purchase-payments']],
            'purchasing' => ['Compras', ['manage-catalog', 'view-purchases', 'manage-purchases', 'receive-purchases', 'approve-purchase-invoices']],
            'human-resources' => ['Recursos Humanos', ['manage-staff', 'view-sensitive-hr', 'manage-sensitive-hr']],
            'technician' => ['Técnicos', ['view-installations', 'sync-installations', 'use-ai', 'manage-operations', 'view-purchases', 'receive-purchases', 'view-analytics']],
        ];
        foreach ($profiles as $slug => [$name, $keys]) {
            $profileId = DB::table('permission_profiles')->insertGetId(['name' => $name, 'slug' => $slug, 'is_system' => true, 'created_at' => now(), 'updated_at' => now()]);
            $permissionIds = DB::table('permissions')->whereIn('key', $keys)->pluck('id');
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_profile_permission')->insert(['permission_profile_id' => $profileId, 'permission_id' => $permissionId]);
            }
        }
        $profileIds = DB::table('permission_profiles')->pluck('id', 'slug');
        DB::table('users')->orderBy('id')->eachById(function ($user) use ($profileIds) {
            $slug = $user->role === 'admin' ? 'administrator' : ($user->role === 'technician' ? 'technician' : 'commercial');
            DB::table('users')->where('id', $user->id)->update(['permission_profile_id' => $profileIds[$slug], 'access_review_due_at' => now()->addMonths(3)]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_restorations');
        Schema::dropIfExists('system_health_checks');
        Schema::dropIfExists('security_events');
        Schema::table('audit_logs', fn (Blueprint $table) => $table->dropColumn(['correlation_id', 'route', 'method']));
        Schema::dropIfExists('user_permission_overrides');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('permission_profile_id');
            $table->dropColumn(['access_reviewed_at', 'access_review_due_at']);
        });
        Schema::dropIfExists('permission_profile_permission');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('permission_profiles');
    }
};
