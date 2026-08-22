<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\HrContractChange;
use App\Models\HrEmergencyContact;
use App\Models\HrEmploymentDocument;
use App\Models\HrEmploymentProfile;
use App\Models\Permission;
use App\Models\PermissionProfile;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HumanResourcesDossierTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_changes_are_traced_and_sensitive_values_are_encrypted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = $this->staff('Elena', 'Moreno');
        HrEmploymentProfile::create([
            'person_id' => $employee->id,
            'employment_type' => 'permanent',
            'status' => 'active',
        ]);

        $this->actingAs($admin)->put(route('human-resources.employees.sensitive.update', $employee), [
            'annual_reference_salary' => '42000.50',
            'collective_agreement' => 'Metal de Madrid',
            'cost_center' => 'OPS-01',
            'payroll_code' => 'NOM-008',
            'change_effective_date' => '2026-08-01',
            'change_reason' => 'Revisión anual',
        ])->assertRedirect();

        $profile = $employee->employmentProfile()->firstOrFail();
        $this->assertSame('42000.50', $profile->annual_reference_salary);
        $this->assertStringNotContainsString(
            '42000.50',
            DB::table('hr_employment_profiles')->where('id', $profile->id)->value('annual_reference_salary'),
        );

        $change = HrContractChange::firstOrFail();
        $this->assertSame($admin->id, $change->changed_by);
        $this->assertSame('Revisión anual', $change->reason);
        $this->assertSame('42000.50', $change->new_values['annual_reference_salary']);
        $this->assertStringNotContainsString(
            '42000.50',
            DB::table('hr_contract_changes')->where('id', $change->id)->value('new_values'),
        );
    }

    public function test_emergency_contacts_are_encrypted_and_limited_to_sensitive_hr_permission(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = $this->staff('Raúl', 'Vega');

        $this->actingAs($admin)->post(route('human-resources.employees.contacts.store', $employee), [
            'name' => 'Isabel Vega',
            'relationship' => 'Hermana',
            'phone' => '+34 600 111 222',
            'prevention_notes' => 'Avisar ante exposición a frutos secos.',
            'is_primary' => true,
        ])->assertRedirect();

        $contact = HrEmergencyContact::firstOrFail();
        $this->assertSame('+34 600 111 222', $contact->phone);
        $raw = DB::table('hr_emergency_contacts')->where('id', $contact->id)->first();
        $this->assertStringNotContainsString('600 111 222', $raw->phone);
        $this->assertStringNotContainsString('frutos secos', $raw->prevention_notes);

        $audit = AuditLog::query()
            ->where('auditable_type', HrEmergencyContact::class)
            ->where('auditable_id', $contact->id)
            ->latest('id')
            ->firstOrFail();
        $this->assertSame('[REDACTED]', $audit->new_values['phone']);
        $this->assertSame('[REDACTED]', $audit->new_values['prevention_notes']);

        $commercial = User::factory()->create(['role' => 'commercial']);
        $this->actingAs($commercial)->post(route('human-resources.employees.contacts.store', $employee), [
            'name' => 'Sin permiso',
            'phone' => '123',
        ])->assertForbidden();
    }

    public function test_private_documents_respect_employee_visibility_and_ownership(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = $this->staff('Lucía', 'Santos');
        $employeeUser = User::factory()->create([
            'person_id' => $employee->id,
            'role' => 'technician',
        ]);
        $other = $this->staff('Mario', 'Gil');
        $otherUser = User::factory()->create([
            'person_id' => $other->id,
            'role' => 'technician',
        ]);

        $this->actingAs($admin)->post(route('human-resources.employees.documents.store', $employee), [
            'category' => 'contract',
            'title' => 'Contrato vigente',
            'visibility' => 'employee',
            'expires_at' => '2027-08-01',
            'file' => UploadedFile::fake()->create('contrato.pdf', 120, 'application/pdf'),
        ])->assertRedirect();
        $shared = HrEmploymentDocument::firstOrFail();
        Storage::disk('local')->assertExists($shared->path);

        $this->actingAs($admin)->post(route('human-resources.employees.documents.store', $employee), [
            'category' => 'payroll',
            'title' => 'Nota interna',
            'visibility' => 'hr_only',
            'file' => UploadedFile::fake()->create('interna.pdf', 20, 'application/pdf'),
        ])->assertRedirect();
        $internal = HrEmploymentDocument::where('visibility', 'hr_only')->firstOrFail();

        $this->actingAs($employeeUser)
            ->get(route('my-human-resources.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('documents', 1)
                ->where('documents.0.id', $shared->id));
        $this->get(route('my-human-resources.documents.download', $shared))->assertOk();
        $this->get(route('my-human-resources.documents.download', $internal))->assertNotFound();

        $this->actingAs($otherUser)
            ->get(route('my-human-resources.documents.download', $shared))
            ->assertNotFound();

        $this->actingAs($admin)
            ->delete(route('human-resources.employees.documents.destroy', [$employee, $shared]))
            ->assertRedirect();
        $this->assertSoftDeleted($shared);
        Storage::disk('local')->assertExists($shared->path);
    }

    public function test_sensitive_hr_denial_hides_and_blocks_employment_documents(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = $this->staff('Nora', 'Campos');

        $this->actingAs($admin)->post(route('human-resources.employees.documents.store', $employee), [
            'category' => 'payroll',
            'title' => 'Nómina privada',
            'visibility' => 'hr_only',
            'file' => UploadedFile::fake()->create('nomina.pdf', 20, 'application/pdf'),
        ])->assertRedirect();
        $document = HrEmploymentDocument::firstOrFail();

        $hrProfile = PermissionProfile::where('slug', 'human-resources')->firstOrFail();
        $hrUser = User::factory()->create(['role' => 'commercial', 'permission_profile_id' => $hrProfile->id]);
        foreach (['view-sensitive-hr', 'manage-sensitive-hr'] as $permissionKey) {
            $hrUser->permissionOverrides()->attach(
                Permission::where('key', $permissionKey)->firstOrFail(),
                ['allowed' => false],
            );
        }

        $this->actingAs($hrUser)
            ->get(route('human-resources.employees.show', $employee))
            ->assertInertia(fn (Assert $page) => $page
                ->where('canViewSensitive', false)
                ->has('employee.documents', 0));
        $this->get(route('human-resources.employees.documents.download', [$employee, $document]))->assertForbidden();
        $this->delete(route('human-resources.employees.documents.destroy', [$employee, $document]))->assertForbidden();
    }

    public function test_admin_can_open_and_export_a_complete_dossier(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = $this->staff('Carmen', 'López');
        HrEmploymentProfile::create([
            'person_id' => $employee->id,
            'employee_number' => 'EMP-020',
            'job_title' => 'Técnica de campo',
            'professional_category' => 'Oficial 1ª',
            'employment_type' => 'permanent',
            'status' => 'active',
            'weekly_hours' => 40,
            'annual_reference_salary' => '36000',
        ]);

        $this->actingAs($admin)
            ->get(route('human-resources.employees.show', $employee))
            ->assertInertia(fn (Assert $page) => $page
                ->component('human-resources/dossier')
                ->where('canViewSensitive', true)
                ->where('canManageSensitive', true)
                ->where('employee.employment.jobTitle', 'Técnica de campo')
                ->where('employee.employment.annualReferenceSalary', '36000'));

        $this->get(route('human-resources.employees.export', $employee))
            ->assertOk()
            ->assertDownload("gestoria-empleado-{$employee->id}.csv");
        $this->assertDatabaseHas('security_events', [
            'user_id' => $admin->id,
            'type' => 'hr_dossier_exported',
        ]);
    }

    public function test_sensitive_view_only_permission_renders_a_read_only_dossier(): void
    {
        $employee = $this->staff('Inés', 'Lago');
        HrEmploymentProfile::create([
            'person_id' => $employee->id,
            'employment_type' => 'permanent',
            'status' => 'active',
            'annual_reference_salary' => '39000',
        ]);
        $profile = PermissionProfile::where('slug', 'human-resources')->firstOrFail();
        $user = User::factory()->create(['role' => 'commercial', 'permission_profile_id' => $profile->id]);
        $user->permissionOverrides()->attach(
            Permission::where('key', 'manage-sensitive-hr')->firstOrFail(),
            ['allowed' => false],
        );

        $this->actingAs($user)
            ->get(route('human-resources.employees.show', $employee))
            ->assertInertia(fn (Assert $page) => $page
                ->where('canViewSensitive', true)
                ->where('canManageSensitive', false)
                ->where('employee.employment.annualReferenceSalary', '39000'));

        $this->put(route('human-resources.employees.sensitive.update', $employee), [
            'annual_reference_salary' => '40000',
            'change_effective_date' => '2026-08-15',
            'change_reason' => 'No autorizado',
        ])->assertForbidden();
    }

    public function test_gestoria_export_neutralizes_spreadsheet_formulas(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = Person::create([
            'category' => 'staff',
            'first_name' => '=CMD()',
            'last_name' => 'Prueba',
            'tax_id' => '@SUM(1+1)',
            'email' => 'formula@example.com',
        ]);
        HrEmploymentProfile::create([
            'person_id' => $employee->id,
            'job_title' => '+Técnico',
            'employment_type' => 'permanent',
            'status' => 'active',
        ]);

        $content = $this->actingAs($admin)
            ->get(route('human-resources.employees.export', $employee))
            ->streamedContent();

        $this->assertStringContainsString("'=CMD() Prueba", $content);
        $this->assertStringContainsString("'@SUM(1+1)", $content);
        $this->assertStringContainsString("'+Técnico", $content);
    }

    private function staff(string $firstName, string $lastName): Person
    {
        return Person::create([
            'category' => 'staff',
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => strtolower($firstName.'.'.$lastName).'@example.com',
        ]);
    }
}
