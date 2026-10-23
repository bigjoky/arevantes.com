<?php

namespace Tests\Feature;

use App\Http\Controllers\CompanyStaffController;
use App\Models\AuditLog;
use App\Models\CompanyDocument;
use App\Models\CompanyProfile;
use App\Models\HrDepartment;
use App\Models\HrEmploymentDocument;
use App\Models\HrEmploymentProfile;
use App\Models\HrLeaveRequest;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompanyRedesignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        CompanyProfile::first()->update(['legal_name' => 'Empresa real', 'tax_id' => 'B12345678', 'iban' => 'ES9121000418450200051332']);
        config(['filesystems.private' => 'local']);
        Storage::fake('local');
    }

    private function employee(string $name = 'Ana', array $profile = []): Person
    {
        $person = Person::create(['category' => 'staff', 'first_name' => $name, 'email' => 'secret@example.test', 'phone' => '600123987', 'document_number' => 'PRIVATE-ID']);
        HrEmploymentProfile::create(['person_id' => $person->id, 'employment_type' => 'permanent', 'status' => 'active', ...$profile]);

        return $person;
    }

    private function upload(array $extra = [])
    {
        return $this->post('/company/documents', ['title' => 'Seguro real', 'category' => 'insurance', 'visibility' => 'restricted', 'upload_token' => (string) Str::uuid(), 'file' => UploadedFile::fake()->createWithContent('policy.pdf', "%PDF-1.7\nreal fixture\n%%EOF"), ...$extra]);
    }

    private function professionalOnly(): void
    {
        Gate::define('manage-admin', fn () => false);
        Gate::define('manage-tax', fn () => false);
        Gate::define('manage-staff', fn () => true);
        Gate::define('view-sensitive-hr', fn () => false);
        Gate::define('manage-sensitive-hr', fn () => false);
    }

    public function test_dashboard_counts_actual_employees_not_users_contractors_or_unregistered_people(): void
    {
        $dep = HrDepartment::create(['name' => 'Técnico', 'is_active' => true]);
        $this->employee('Ana', ['department_id' => $dep->id]);
        $this->employee('Luis', ['employment_type' => 'contractor']);
        $this->employee('Fin', ['status' => 'ended']);
        Person::create(['category' => 'staff', 'first_name' => 'Sin ficha']);
        User::factory()->count(2)->create();
        $this->get('/company')->assertOk()->assertInertia(fn (Assert $p) => $p->component('company/workspace')->where('metrics.employees', 1)->where('metrics.documents', 0)->where('metrics.configuration', 'registered')->where('metrics.renewals', 0)->where('distributions.departments.0.total', 1)->missing('company.iban'));
    }

    public function test_renewal_total_is_not_truncated_to_the_dashboard_preview(): void
    {
        for ($i = 0; $i < 23; $i++) {
            $this->employee('Persona '.$i, ['end_date' => today()->addDays(5)]);
        }
        $this->get('/company')->assertInertia(fn (Assert $p) => $p->where('metrics.renewals', 23)->has('expiries', 20));
    }

    public function test_staff_filters_are_paginated_and_detail_keeps_return_context(): void
    {
        for ($i = 0; $i < 13; $i++) {
            $this->employee('Equipo '.$i);
        }
        $this->employee('Otro');
        $this->get('/company/staff?search=Equipo&page=2')->assertInertia(fn (Assert $p) => $p->component('company/workspace')->where('staff.total', 13)->has('staff.data', 3)->where('staff.data.0.href', fn ($href) => str_contains($href, 'return_page=2') && str_contains($href, 'return_search=Equipo')));
        $person = Person::where('first_name', 'Equipo 0')->first();
        $this->get('/company/staff/'.$person->id.'?return_search=Equipo&return_page=2')->assertInertia(fn (Assert $p) => $p->component('company/employee')->where('returnFilters.return_page', '2'));
    }

    public function test_new_employee_reuses_contract_history_without_creating_a_user(): void
    {
        $users = User::count();
        $this->post('/company/staff', ['first_name' => 'Nueva', 'last_name' => 'Persona', 'employee_number' => 'EMP-1', 'employment_type' => 'permanent', 'status' => 'active', 'job_title' => 'Técnica'])->assertRedirect();
        $person = Person::where('first_name', 'Nueva')->firstOrFail();
        $this->assertSame($users, User::count());
        $this->assertSame('Técnica', $person->employmentProfile->job_title);
        $this->assertSame(1, $person->contractChanges()->count());
        $this->post('/company/staff', ['first_name' => 'Duplicada', 'employee_number' => 'EMP-1', 'employment_type' => 'permanent', 'status' => 'active'])->assertSessionHasErrors('employee_number');
        $this->assertSame(0, Person::where('first_name', 'Duplicada')->count());
    }

    public function test_invalid_employment_rolls_back_person_creation(): void
    {
        $count = Person::count();
        $this->post('/company/staff', ['first_name' => 'Fallida', 'employee_number' => 'EMP-2', 'employment_type' => 'invalid', 'status' => 'active'])->assertSessionHasErrors('employment_type');
        $this->assertSame($count, Person::count());
    }

    public function test_professional_update_detects_stale_forms_and_does_not_accept_salary(): void
    {
        $person = $this->employee();
        $fingerprint = CompanyStaffController::fingerprint($person);
        $data = ['expected_fingerprint' => $fingerprint, 'employment_type' => 'permanent', 'status' => 'active', 'job_title' => 'Responsable', 'annual_reference_salary' => '90000'];
        $this->put('/company/staff/'.$person->id, $data)->assertRedirect();
        $this->assertNull($person->employmentProfile()->first()->annual_reference_salary);
        $this->put('/company/staff/'.$person->id, $data)->assertConflict();
    }

    public function test_professional_payloads_and_legacy_routes_do_not_leak_sensitive_fields(): void
    {
        $person = $this->employee();
        $this->professionalOnly();
        $doc = HrEmploymentDocument::create(['person_id' => $person->id, 'title' => 'Private labor', 'category' => 'certificate', 'path' => 'labor/secret.pdf', 'original_name' => 'secret.pdf', 'mime_type' => 'application/pdf', 'size' => 20, 'visibility' => 'employee']);
        HrLeaveRequest::create(['person_id' => $person->id, 'type' => 'medical', 'start_date' => today(), 'end_date' => today(), 'requested_days' => 1, 'status' => 'pending', 'notes' => 'PRIVATE-DIAGNOSIS']);
        $this->get('/company/staff/'.$person->id)->assertInertia(fn (Assert $p) => $p->has('documents.data', 0)->missing('employee.email')->missing('employee.phone')->missing('employee.document_number')->missing('employee.annual_reference_salary')->missing('leave.data.0.notes')->missing('leave.data.0.type'));
        $this->get('/human-resources/leave')->assertRedirect('/company/staff?tab=absences');
        $this->get('/staff/'.$person->id)->assertInertia(fn (Assert $p) => $p->component('company/employee'));
        Storage::disk('local')->put($doc->path, 'fixture');
        $this->get('/human-resources/employees/'.$person->id.'/documents/'.$doc->id)->assertForbidden();
        $this->put('/staff/'.$person->id, ['first_name' => 'Ana', 'email' => 'changed@example.test'])->assertForbidden();
        $this->assertSame('secret@example.test', $person->fresh()->email);
    }

    public function test_training_is_filtered_before_pagination(): void
    {
        $person = $this->employee();
        foreach (['contract', 'certificate'] as $category) {
            HrEmploymentDocument::create(['person_id' => $person->id, 'title' => $category, 'category' => $category, 'path' => 'private/'.$category, 'original_name' => 'x.pdf', 'mime_type' => 'application/pdf', 'size' => 20, 'visibility' => 'hr_only']);
        }
        $this->get('/company/staff/'.$person->id.'?tab=training')->assertInertia(fn (Assert $p) => $p->where('documents.total', 1)->where('documents.data.0.category', 'certificate'));
    }

    public function test_document_upload_is_private_audited_and_does_not_reuse_ai_knowledge(): void
    {
        $this->upload()->assertRedirect();
        $doc = CompanyDocument::firstOrFail();
        $version = $doc->versions()->firstOrFail();
        Storage::disk('local')->assertExists($version->path);
        $this->assertSame(64, strlen($version->sha256));
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => CompanyDocument::class, 'auditable_id' => $doc->id, 'event' => 'created']);
        $this->get('/company/documents/'.$doc->id)->assertInertia(fn (Assert $p) => $p->component('company/document')->where('versions.data.0.author', auth()->user()->name)->missing('versions.data.0.path')->missing('versions.data.0.sha256')->missing('document.upload_token'));
        $this->get('/company/documents/'.$doc->id.'/versions/'.$version->id.'/download')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame(0, DB::table('knowledge_documents')->count());
    }

    public function test_upload_retry_is_idempotent_but_changed_request_is_rejected(): void
    {
        $token = (string) Str::uuid();
        $this->upload(['upload_token' => $token])->assertRedirect();
        $this->upload(['upload_token' => $token])->assertRedirect();
        $this->assertSame(1, CompanyDocument::count());
        $this->assertSame(1, CompanyDocument::first()->versions()->count());
        $this->upload(['upload_token' => $token, 'title' => 'Changed'])->assertConflict();
    }

    public function test_new_versions_preserve_history_and_reject_stale_updates(): void
    {
        $this->upload();
        $doc = CompanyDocument::firstOrFail();
        $old = $doc->versions()->first();
        $this->post('/company/documents/'.$doc->id.'/versions', ['expected_revision' => 1, 'file' => UploadedFile::fake()->createWithContent('v2.pdf', "%PDF-1.7\nversion2\n%%EOF")])->assertRedirect();
        $this->assertSame(2, $doc->fresh()->version);
        Storage::disk('local')->assertExists($old->path);
        $this->post('/company/documents/'.$doc->id.'/versions', ['expected_revision' => 1, 'file' => UploadedFile::fake()->createWithContent('v3.pdf', "%PDF-1.7\nversion3\n%%EOF")])->assertConflict();
        $this->assertSame(2, $doc->versions()->count());
        $this->patch('/company/documents/'.$doc->id, ['expected_revision' => 1, 'title' => 'Changed', 'category' => 'legal', 'visibility' => 'restricted', 'status' => 'archived'])->assertConflict();
    }

    public function test_document_permissions_are_checked_on_list_detail_upload_and_download(): void
    {
        $this->upload();
        $doc = CompanyDocument::first();
        $v = $doc->versions()->first();
        $this->professionalOnly();
        $this->get('/company/documents')->assertInertia(fn (Assert $p) => $p->where('documents.total', 0));
        $this->get('/company/documents/'.$doc->id)->assertNotFound();
        $this->get('/company/documents/'.$doc->id.'/versions/'.$v->id.'/download')->assertNotFound();
        $this->upload()->assertForbidden();
        Gate::define('manage-tax', fn () => true);
        Gate::define('manage-staff', fn () => false);
        $this->get('/company/documents/'.$doc->id)->assertNotFound();
        $this->get('/company')->assertInertia(fn (Assert $p) => $p->where('metrics.employees', null)->where('metrics.documents', null)->where('metrics.renewals', null));
    }

    public function test_shared_staff_document_and_filtered_back_link(): void
    {
        $this->upload(['visibility' => 'staff']);
        $doc = CompanyDocument::first();
        $this->professionalOnly();
        $this->get('/company/documents?search=Seguro&category=insurance')->assertInertia(fn (Assert $p) => $p->where('documents.total', 1)->where('documents.data.0.href', fn ($href) => str_contains($href, 'return_category=insurance')));
        $this->get('/company/documents/'.$doc->id.'?return_category=insurance')->assertInertia(fn (Assert $p) => $p->where('returnFilters.return_category', 'insurance')->where('canManage', false));
    }

    public function test_cross_document_downloads_corrupt_files_and_missing_files_are_rejected(): void
    {
        $this->upload();
        $this->upload(['title' => 'Second']);
        [$a,$b] = CompanyDocument::all();
        $v = $a->versions()->first();
        $this->get('/company/documents/'.$b->id.'/versions/'.$v->id.'/download')->assertNotFound();
        Storage::disk('local')->put($v->path, 'tampered');
        $this->get('/company/documents/'.$a->id.'/versions/'.$v->id.'/download')->assertConflict();
        Storage::disk('local')->delete($v->path);
        $this->get('/company/documents/'.$a->id.'/versions/'.$v->id.'/download')->assertNotFound();
    }

    public function test_executable_and_mismatched_uploads_are_rejected(): void
    {
        $this->upload(['file' => UploadedFile::fake()->createWithContent('evil.php', '<?php echo 1;')])->assertStatus(302)->assertSessionHasErrors('file');
        $this->upload(['file' => UploadedFile::fake()->createWithContent('fake.pdf', 'plain text not PDF')->mimeType('text/plain')])->assertStatus(302)->assertSessionHasErrors('file');
        $this->upload(['file' => UploadedFile::fake()->image('disguised.pdf')->mimeType('image/png')])->assertSessionHasErrors('file');
        $this->assertSame(0, CompanyDocument::count());
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_public_storage_is_rejected_without_creating_rows(): void
    {
        config(['filesystems.private' => 'public']);
        $this->upload()->assertStatus(503);
        $this->assertSame(0, CompanyDocument::count());
    }

    public function test_database_failure_cleans_new_files_and_rolls_back_document(): void
    {
        AuditLog::creating(function ($audit) {
            if ($audit->auditable_type === CompanyDocument::class) {
                throw new \RuntimeException('audit unavailable');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->upload();
            $this->fail('Expected failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('audit unavailable', $e->getMessage());
        }
        $this->assertSame(0, CompanyDocument::count());
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_archive_preserves_versions_and_blocks_new_content(): void
    {
        $this->upload();
        $doc = CompanyDocument::first();
        $this->patch('/company/documents/'.$doc->id, ['title' => 'Seguro', 'category' => 'insurance', 'visibility' => 'restricted', 'status' => 'archived', 'expected_revision' => 1])->assertRedirect();
        $this->get('/company/documents')->assertInertia(fn (Assert $p) => $p->where('documents.total', 0));
        $this->get('/company/documents?state=archived')->assertInertia(fn (Assert $p) => $p->where('documents.total', 1));
        $this->assertSame(1, $doc->versions()->count());
        $this->post('/company/documents/'.$doc->id.'/versions', ['expected_revision' => 2, 'file' => UploadedFile::fake()->createWithContent('x.pdf', "%PDF-1.7\n%%EOF")])->assertConflict();
    }

    public function test_multiple_company_context_is_explicitly_blocked(): void
    {
        $this->upload();
        $doc = CompanyDocument::first();
        $v = $doc->versions()->first();
        CompanyProfile::create(['legal_name' => 'Other company']);
        foreach (['/company', '/company/staff', '/company/documents', '/company/settings', '/company/documents/'.$doc->id, '/company/documents/'.$doc->id.'/versions/'.$v->id.'/download'] as $url) {
            $this->get($url)->assertConflict();
        }
        $this->upload()->assertConflict();
    }

    public function test_document_versions_are_immutable_through_the_model(): void
    {
        $this->upload();
        $version = CompanyDocument::first()->versions()->first();
        $this->expectException(ValidationException::class);
        $version->update(['original_name' => 'replacement.pdf']);
    }

    public function test_unprivileged_accounts_cannot_enter_any_company_route(): void
    {
        $person = $this->employee();
        $this->actingAs(User::factory()->create(['role' => 'commercial']));
        foreach (['/company', '/company/staff', '/company/documents', '/company/settings', '/company/staff/'.$person->id] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/company/staff', [])->assertForbidden();
        $this->upload()->assertForbidden();
    }

    public function test_rollback_refuses_to_destroy_document_history(): void
    {
        $this->upload();
        $migration = require database_path('migrations/2026_10_23_020000_create_corporate_document_archive.php');
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }
}
