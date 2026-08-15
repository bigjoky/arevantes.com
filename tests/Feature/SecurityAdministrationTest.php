<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\PermissionProfile;
use App\Models\Person;
use App\Models\PurchaseDocument;
use App\Models\SecurityEvent;
use App\Models\Supplier;
use App\Models\SystemHealthCheck;
use App\Models\User;
use App\Rules\SafeUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use PDO;
use Tests\TestCase;

class SecurityAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_and_user_override_are_enforced_by_sensitive_routes(): void
    {
        $finance = PermissionProfile::where('slug', 'finance')->firstOrFail();
        $user = User::factory()->create(['role' => 'commercial', 'permission_profile_id' => $finance->id]);

        $this->actingAs($user)->get(route('finance.index'))->assertOk();
        $this->get(route('purchase-inbox.index'))->assertForbidden();

        $permission = Permission::where('key', 'view-finance')->firstOrFail();
        $user->permissionOverrides()->attach($permission, ['allowed' => false]);
        $this->get(route('finance.index'))->assertForbidden();
    }

    public function test_document_download_is_isolated_from_users_without_purchase_permission(): void
    {
        $finance = PermissionProfile::where('slug', 'finance')->firstOrFail();
        $user = User::factory()->create(['permission_profile_id' => $finance->id]);
        $supplier = Supplier::create(['legal_name' => 'Private Supplier SL', 'country' => 'ES']);
        $document = PurchaseDocument::create([
            'type' => 'supplier_invoice', 'supplier_id' => $supplier->id, 'created_by' => $user->id,
            'number' => 'FR-PRIVATE', 'status' => 'registered', 'issue_date' => today(),
            'subtotal' => 1, 'tax_amount' => 0, 'amount' => 1,
        ]);

        $this->actingAs($user)->get(route('supplier-invoices.document.download', $document))->assertForbidden();
    }

    public function test_permission_changes_are_audited_and_generate_security_alerts(): void
    {
        $administrator = PermissionProfile::where('slug', 'administrator')->firstOrFail();
        $technician = PermissionProfile::where('slug', 'technician')->firstOrFail();
        $admin = User::factory()->create(['role' => 'admin', 'permission_profile_id' => $administrator->id]);
        $user = User::factory()->create(['role' => 'technician', 'permission_profile_id' => $technician->id]);

        $this->actingAs($admin)->patch(route('admin.users.update', $user), ['permission_profile_id' => $administrator->id])->assertRedirect();

        $this->assertDatabaseHas('security_events', ['type' => 'permissions_changed', 'user_id' => $user->id]);
        $this->assertTrue(AuditLog::where('auditable_type', User::class)->where('auditable_id', $user->id)->exists());
        $this->assertNotNull($user->refresh()->access_review_due_at);
    }

    public function test_manage_staff_cannot_manage_user_access_without_manage_access(): void
    {
        $hrProfile = PermissionProfile::where('slug', 'human-resources')->firstOrFail();
        $hrUser = User::factory()->create(['role' => 'commercial', 'permission_profile_id' => $hrProfile->id]);
        $staff = Person::create(['category' => 'staff', 'first_name' => 'Irene', 'last_name' => 'Ruiz']);

        $this->actingAs($hrUser)->post(route('staff.access.store', $staff), [
            'email' => 'irene@example.com',
            'role' => 'technician',
            'password' => 'Strong-Password-2026!',
            'password_confirmation' => 'Strong-Password-2026!',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'irene@example.com']);
    }

    public function test_last_administrator_cannot_be_demoted_by_another_access_manager(): void
    {
        $administrator = PermissionProfile::where('slug', 'administrator')->firstOrFail();
        $technician = PermissionProfile::where('slug', 'technician')->firstOrFail();
        $lastAdmin = User::factory()->create(['role' => 'admin', 'permission_profile_id' => $administrator->id]);
        $accessManager = User::factory()->create(['role' => 'commercial', 'permission_profile_id' => $technician->id]);
        $accessManager->permissionOverrides()->attach(
            Permission::where('key', 'manage-access')->firstOrFail(),
            ['allowed' => true],
        );

        $this->actingAs($accessManager)
            ->patch(route('admin.users.update', $lastAdmin), ['permission_profile_id' => $technician->id])
            ->assertSessionHasErrors('role');

        $this->assertSame($administrator->id, $lastAdmin->refresh()->permission_profile_id);
    }

    public function test_last_administrator_cannot_deny_essential_permissions(): void
    {
        $administrator = PermissionProfile::where('slug', 'administrator')->firstOrFail();
        $lastAdmin = User::factory()->create(['role' => 'admin', 'permission_profile_id' => $administrator->id]);
        $manageAccess = Permission::where('key', 'manage-access')->firstOrFail();

        $this->actingAs($lastAdmin)->patch(route('admin.users.update', $lastAdmin), [
            'permission_profile_id' => $administrator->id,
            'overrides' => [$manageAccess->id => 'deny'],
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('user_permission_overrides', [
            'user_id' => $lastAdmin->id,
            'permission_id' => $manageAccess->id,
            'allowed' => false,
        ]);
    }

    public function test_role_command_keeps_legacy_role_and_permission_profile_in_sync(): void
    {
        $commercial = PermissionProfile::where('slug', 'commercial')->firstOrFail();
        $technician = PermissionProfile::where('slug', 'technician')->firstOrFail();
        $user = User::factory()->create(['role' => 'commercial', 'permission_profile_id' => $commercial->id]);

        $this->artisan('user:role', ['email' => $user->email, 'role' => 'technician'])->assertSuccessful();

        $this->assertSame('technician', $user->refresh()->role);
        $this->assertSame($technician->id, $user->permission_profile_id);
        $this->assertNotNull($user->access_reviewed_at);
    }

    public function test_audit_export_requires_permission_and_contains_correlation_data(): void
    {
        $technician = PermissionProfile::where('slug', 'technician')->firstOrFail();
        $adminProfile = PermissionProfile::where('slug', 'administrator')->firstOrFail();
        $technicianUser = User::factory()->create(['permission_profile_id' => $technician->id]);
        $admin = User::factory()->create(['role' => 'admin', 'permission_profile_id' => $adminProfile->id]);
        AuditLog::create(['event' => 'updated', 'auditable_type' => User::class, 'auditable_id' => $admin->id, 'correlation_id' => '11111111-1111-4111-8111-111111111111']);

        $this->actingAs($technicianUser)->get(route('admin.audit.export'))->assertForbidden();
        $response = $this->actingAs($admin)->get(route('admin.audit.export'));
        $response->assertOk();
        $this->assertStringContainsString('11111111-1111-4111-8111-111111111111', $response->streamedContent());
    }

    public function test_failed_queue_or_scheduler_health_creates_an_alert(): void
    {
        Cache::forget('system.scheduler.heartbeat');
        $this->app['db']->table('failed_jobs')->insert([
            'uuid' => (string) str()->uuid(), 'connection' => 'database', 'queue' => 'default',
            'payload' => '{}', 'exception' => 'Integration failed', 'failed_at' => now(),
        ]);
        $this->artisan('system:health-check')->assertFailed();
        $this->assertDatabaseHas('system_health_checks', ['component' => 'scheduler', 'status' => 'failed']);
        $this->assertDatabaseHas('system_health_checks', ['component' => 'queue', 'status' => 'failed']);
        $this->assertTrue(SecurityEvent::where('type', 'system_component_failed')->exists());
        $this->assertTrue(SystemHealthCheck::where('component', 'queue')->exists());
    }

    public function test_deye_health_uses_the_credentials_required_by_the_client(): void
    {
        config()->set([
            'services.deye.app_id' => 'app-id',
            'services.deye.app_secret' => 'app-secret',
            'services.deye.email' => 'solar@example.com',
            'services.deye.password' => 'password',
        ]);
        Cache::put('system.scheduler.heartbeat', now());

        $this->artisan('system:health-check')->assertSuccessful();

        $this->assertDatabaseHas('system_health_checks', [
            'component' => 'integration:deye',
            'status' => 'healthy',
            'message' => 'Integration is configured.',
        ]);
    }

    public function test_backup_is_really_restored_and_checked_in_an_isolated_database(): void
    {
        $directory = storage_path('app/private/backups');
        File::ensureDirectoryExists($directory);
        $plain = tempnam(sys_get_temp_dir(), 'backup-fixture-');
        $pdo = new PDO('sqlite:'.$plain);
        $pdo->exec('CREATE TABLE migrations (id INTEGER PRIMARY KEY, migration TEXT, batch INTEGER)');
        $pdo = null;
        $backup = $directory.'/database-test.sqlite.enc';
        File::put($backup, Crypt::encryptString(File::get($plain)));

        try {
            $this->artisan('database:verify-backup', ['backup' => basename($backup)])->assertSuccessful();
            $this->assertDatabaseHas('backup_restorations', ['backup_name' => basename($backup), 'status' => 'healthy', 'environment' => 'isolated']);
        } finally {
            File::delete([$plain, $backup]);
        }
    }

    public function test_all_administration_routes_have_explicit_permission_middleware(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())->filter(fn ($route) => str_starts_with($route->uri(), 'admin'));
        $this->assertNotEmpty($routes);
        foreach ($routes as $route) {
            $this->assertTrue(collect($route->gatherMiddleware())->contains(fn ($middleware) => str_starts_with($middleware, 'can:')), $route->uri().' has no explicit permission.');
        }
    }

    public function test_upload_validation_rejects_spoofed_file_contents(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'spoofed-upload-');
        File::put($path, 'This is plain text, not a PDF.');
        $file = new UploadedFile($path, 'invoice.pdf', null, null, true);

        try {
            $validator = Validator::make(['document' => $file], ['document' => [new SafeUpload(['pdf'])]]);

            $this->assertTrue($validator->fails());
        } finally {
            File::delete($path);
        }
    }

    public function test_upload_validation_rejects_a_zip_renamed_as_docx(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'spoofed-docx-');
        File::put($path, "PK\x03\x04not-a-word-package");
        $file = new UploadedFile($path, 'contract.docx', 'application/zip', null, true);

        try {
            $validator = Validator::make(['document' => $file], ['document' => [new SafeUpload(['docx'])]]);

            $this->assertTrue($validator->fails());
        } finally {
            File::delete($path);
        }
    }
}
