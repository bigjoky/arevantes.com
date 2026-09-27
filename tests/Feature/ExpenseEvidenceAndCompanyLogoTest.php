<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Expense;
use App\Models\ExpenseAttachment;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpenseEvidenceAndCompanyLogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_evidence_can_be_added_to_paid_expense_without_changing_financial_data(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $expense = Expense::create(['expense_category_id' => ExpenseCategory::firstOrFail()->id, 'created_by' => $admin->id, 'number' => 'EXP-EVIDENCE', 'type' => 'receipt', 'expense_date' => '2026-07-13', 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121, 'paid_amount' => 121, 'status' => 'paid']);
        $before = $expense->refresh()->getAttributes();
        $this->actingAs($admin)->post(route('expenses.attachments.store', $expense), ['attachments' => [UploadedFile::fake()->image('invoice.jpg')]])->assertRedirect()->assertSessionHasNoErrors();
        $attachment = $expense->attachments()->sole();
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertSame($admin->id, $attachment->uploaded_by);
        $this->assertSame($before, $expense->fresh()->getAttributes());
        $this->post(route('expenses.attachments.store', $expense), ['attachments' => [UploadedFile::fake()->createWithContent('bad.php', '<?php echo 1;')]])->assertSessionHasErrors('attachments.0');
        $this->assertSame(1, $expense->attachments()->count());
        $this->actingAs(User::factory()->create(['role' => 'technician']))->post(route('expenses.attachments.store', $expense), ['attachments' => [UploadedFile::fake()->image('forbidden.jpg')]])->assertForbidden();
    }

    public function test_failed_attachment_insert_removes_new_files(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $expense = Expense::create(['expense_category_id' => ExpenseCategory::firstOrFail()->id, 'number' => 'EXP-FAIL', 'type' => 'receipt', 'expense_date' => '2026-07-13', 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121, 'status' => 'pending']);
        ExpenseAttachment::creating(fn () => throw new \RuntimeException('Simulated failure'));
        try {
            $this->actingAs($admin)->post(route('expenses.attachments.store', $expense), ['attachments' => [UploadedFile::fake()->image('invoice.jpg')]])->assertStatus(500);
        } finally {
            ExpenseAttachment::flushEventListeners();
        }
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame(0, $expense->attachments()->count());
    }

    private function companyData(): array
    {
        return ['legal_name' => 'Test company', 'tax_id' => 'B12345678', 'address' => 'Test street', 'postal_code' => '14001', 'city' => 'Cordoba', 'province' => 'Cordoba', 'country' => 'ES', 'email' => 'test@example.com'];
    }

    public function test_logo_is_replaced_then_removed_without_leaving_the_old_file(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $url = route('fiscal-settings.company.update');
        $this->post($url, [...$this->companyData(), 'logo' => UploadedFile::fake()->image('first.png')])->assertSessionHasNoErrors();
        $old = CompanyProfile::firstOrFail()->logo_path;
        Storage::disk('local')->assertExists($old);
        $this->post($url, [...$this->companyData(), 'logo' => UploadedFile::fake()->image('second.png')])->assertSessionHasNoErrors();
        $new = CompanyProfile::firstOrFail()->logo_path;
        $this->assertNotSame($old, $new);
        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertExists($new);
        $this->get('/company-fiscal/logo')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->post($url, [...$this->companyData(), 'remove_logo' => 1])->assertSessionHasNoErrors();
        $this->assertNull(CompanyProfile::firstOrFail()->logo_path);
        Storage::disk('local')->assertMissing($new);
        $this->get('/company-fiscal/logo')->assertNotFound();
    }

    public function test_invalid_replacement_keeps_existing_logo(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('company/existing.png', 'old');
        CompanyProfile::firstOrFail()->update(['logo_path' => 'company/existing.png']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('fiscal-settings.company.update'), [...$this->companyData(), 'logo' => UploadedFile::fake()->createWithContent('bad.php', '<?php echo 1;')])->assertSessionHasErrors('logo');
        Storage::disk('local')->assertExists('company/existing.png');
        $this->assertSame('company/existing.png', CompanyProfile::firstOrFail()->logo_path);
    }
}
