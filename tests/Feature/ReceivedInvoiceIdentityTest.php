<?php

namespace Tests\Feature;

use App\Exceptions\IssuedDocumentUnavailable;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankTransaction;
use App\Models\CompanyProfile;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Installation;
use App\Models\Person;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\Documents\DocumentPdf;
use App\Services\Finance\ReceivedInvoiceAudit;
use App\Services\Finance\ReceivedInvoiceIdentity;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\EconomicFixture;
use Tests\TestCase;

class ReceivedInvoiceIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_registry_is_protected_from_public_data_api_roles(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL row security requires PostgreSQL.');
        }
        $this->assertTrue(DB::selectOne("SELECT relrowsecurity FROM pg_class WHERE oid='public.received_invoice_identities'::regclass")->relrowsecurity);
        $this->assertSame(0, DB::table('pg_policies')->where('schemaname', 'public')->where('tablename', 'received_invoice_identities')->count());
    }

    private function purchase(string $number = 'EXT-ONE', ?Supplier $supplier = null, ?int $receiver = null): PurchaseDocument
    {
        $supplier ??= Supplier::create(['legal_name' => 'Synthetic issuer', 'country' => 'ES', 'tax_id' => 'B12345678']);
        $document = new PurchaseDocument(['type' => 'supplier_invoice', 'supplier_id' => $supplier->id, 'supplier_number' => $number, 'number' => 'SYN-'.bin2hex(random_bytes(5)), 'issue_date' => today(), 'status' => 'registered', 'subtotal' => 100, 'tax_amount' => 0, 'amount' => 100]);
        $document->receiver_company_id = $receiver;
        $document->save();

        return $document;
    }

    private function expense(string $number = 'EXT-ONE', ?int $receiver = null): Expense
    {
        $expense = new Expense(['expense_category_id' => ExpenseCategory::firstOrFail()->id, 'number' => 'EXP-'.bin2hex(random_bytes(5)), 'type' => 'invoice', 'issuer_country' => 'ES', 'issuer_tax_id' => 'B-12345678', 'issuer_legal_name' => 'Synthetic issuer', 'document_number' => $number, 'expense_date' => today(), 'status' => 'pending', 'subtotal' => 100, 'tax_amount' => 0, 'amount' => 100]);
        $expense->receiver_company_id = $receiver;
        $expense->save();

        return $expense;
    }

    public function test_purchase_reserves_the_shared_identity_and_posts_only_once(): void
    {
        $invoice = $this->purchase();
        $accounting = app(AccountingSynchronizer::class);
        $this->assertSame(1, $accounting->syncPurchaseDocument($invoice));
        $this->assertSame(0, $accounting->syncPurchaseDocument($invoice));
        $this->assertSame(1, $invoice->accountingEntry()->count());
        $this->assertSame(1, DB::table('received_invoice_identities')->count());
        $this->expectException(QueryException::class);
        $this->expense();
    }

    public function test_expense_also_prevents_a_duplicate_purchase_with_another_supplier_record(): void
    {
        $this->expense();
        $duplicateSupplier = Supplier::create(['legal_name' => 'Another name', 'country' => 'ES', 'tax_id' => 'b.12345678']);
        $this->expectException(QueryException::class);
        $this->purchase(supplier: $duplicateSupplier);
    }

    public function test_equal_amounts_on_distinct_numbers_are_distinct_invoices(): void
    {
        $this->purchase('EXT-A');
        $this->expense('EXT-B');
        $this->assertSame(2, DB::table('received_invoice_identities')->count());
    }

    public function test_two_receivers_have_separate_identity_namespaces(): void
    {
        $one = CompanyProfile::firstOrFail();
        $two = CompanyProfile::create(['legal_name' => 'Synthetic other receiver', 'country' => 'ES', 'tax_id' => 'B87654321']);
        $this->expense(receiver: $one->id);
        $this->expense(receiver: $two->id);
        $this->assertSame(2, DB::table('received_invoice_identities')->count());
    }

    public function test_claimed_identity_cannot_be_replaced_silently(): void
    {
        $invoice = $this->purchase();
        $this->expectException(QueryException::class);
        $invoice->update(['supplier_number' => 'REPLACED']);
    }

    public function test_original_download_never_reconstructs_a_missing_issued_document_even_with_final_flag(): void
    {
        $invoice = new SalesDocument(['type' => 'invoice', 'status' => 'issued', 'locked_at' => now()]);
        $pdf = app(DocumentPdf::class);
        $this->assertSame('historical_original_missing', $pdf->custody($invoice));
        $this->expectException(IssuedDocumentUnavailable::class);
        $pdf->render($invoice, 'invoice', final: true);
    }

    public function test_original_bytes_are_independent_of_current_company_and_altered_files_are_blocked(): void
    {
        Storage::fake(config('filesystems.private'));
        $bytes = '%PDF synthetic original';
        Storage::disk(config('filesystems.private'))->put('original.pdf', $bytes);
        $invoice = (new SalesDocument)->forceFill(['type' => 'invoice', 'status' => 'issued', 'locked_at' => now(), 'archived_pdf_path' => 'original.pdf', 'archived_pdf_hash' => hash('sha256', $bytes)]);
        CompanyProfile::firstOrFail()->update(['legal_name' => 'Changed after issue']);
        $this->assertSame($bytes, app(DocumentPdf::class)->render($invoice, 'invoice'));
        Storage::disk(config('filesystems.private'))->put('original.pdf', 'altered');
        $this->assertSame('integrity_failed', app(DocumentPdf::class)->custody($invoice));
        $this->expectException(IssuedDocumentUnavailable::class);
        app(DocumentPdf::class)->render($invoice, 'invoice');
    }

    public function test_archival_rendering_cannot_be_used_outside_an_emission_transaction(): void
    {
        $this->expectException(\LogicException::class);
        app(DocumentPdf::class)->renderForArchiving(new SalesDocument(['type' => 'invoice', 'status' => 'draft']));
    }

    public function test_unknown_issuer_cannot_become_a_new_received_invoice(): void
    {
        $unknown = Supplier::create(['legal_name' => 'Unknown tax identity', 'country' => 'ES']);
        $this->expectException(QueryException::class);
        $this->purchase(supplier: $unknown);
    }

    public function test_authorized_expense_correction_preserves_old_identity_and_reserves_new_identity(): void
    {
        $expense = $this->expense();
        ReceivedInvoiceIdentity::correctingExpense($expense->id, fn () => $expense->update(['document_number' => 'CORRECTED-NUMBER']));
        $this->assertSame(['EXT-ONE', 'CORRECTED-NUMBER'], DB::table('received_invoice_identities')->orderBy('id')->pluck('external_number')->all());
        $this->expectException(QueryException::class);
        $this->purchase('EXT-ONE');
    }

    public function test_other_receiver_cannot_be_posted_by_the_single_company_operating_context(): void
    {
        $other = CompanyProfile::create(['legal_name' => 'Other company', 'country' => 'ES']);
        $invoice = $this->expense(receiver: $other->id);
        $this->expectException(ValidationException::class);
        app(AccountingSynchronizer::class)->syncExpense($invoice);
    }

    public function test_historical_collision_is_diagnosed_without_claiming_or_rewriting_rows(): void
    {
        Storage::fake(config('filesystems.private'));
        EconomicFixture::legacy(function (): void {
            $this->purchase(receiver: CompanyProfile::firstOrFail()->id);
            $this->expense(receiver: CompanyProfile::firstOrFail()->id);
        });
        $before = [PurchaseDocument::all()->toJson(), Expense::all()->toJson(), DB::table('audit_logs')->count()];
        $writes = [];
        DB::listen(function ($query) use (&$writes): void {
            if (! str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
                $writes[] = $query->sql;
            }
        });
        $report = app(ReceivedInvoiceAudit::class)->report();
        $this->assertSame([], $writes);
        $this->assertCount(1, $report['identity_collisions']);
        $this->assertSame('confirmed_identity_collision', $report['identity_collisions'][0]['classification']);
        $this->assertFalse($report['identity_collisions'][0]['same_physical_original_confirmed']);
        $this->assertSame($before, [PurchaseDocument::all()->toJson(), Expense::all()->toJson(), DB::table('audit_logs')->count()]);
        $this->assertSame(0, DB::table('received_invoice_identities')->count());
    }

    public function test_guard_migration_does_not_merge_or_rewrite_historical_duplicates(): void
    {
        EconomicFixture::legacy(function (): void {
            $this->purchase();
            $this->expense();
        });
        $migration = require database_path('migrations/2026_10_26_101000_guard_received_invoice_identity.php');
        $before = [PurchaseDocument::all()->toJson(), Expense::all()->toJson()];
        $migration->down();
        $migration->up();
        $this->assertSame($before, [PurchaseDocument::all()->toJson(), Expense::all()->toJson()]);
        $this->assertSame(0, DB::table('received_invoice_identities')->count());
    }

    public function test_one_invoice_keeps_lines_project_and_multiple_reconciled_payments_without_another_owner(): void
    {
        $project = Installation::create(['site_address_line_1' => 'Synthetic test site', 'project_reference' => 'SYNTHETIC-ECO02', 'service_type' => 'solar', 'status' => 'active']);
        $invoice = $this->purchase();
        $invoice->update(['installation_id' => $project->id]);
        $invoice->lines()->createMany([
            ['position' => 1, 'description' => 'Material', 'quantity' => 1, 'unit_price' => 60, 'subtotal' => 60, 'tax_amount' => 0, 'total' => 60],
            ['position' => 2, 'description' => 'Installation work', 'quantity' => 1, 'unit_price' => 40, 'subtotal' => 40, 'tax_amount' => 0, 'total' => 40],
        ]);
        $bank = BankAccount::create(['name' => 'Synthetic bank', 'currency' => 'EUR']);
        foreach ([40, 60] as $index => $amount) {
            $payment = $invoice->payments()->create(['amount' => $amount, 'method' => 'bank_transfer', 'paid_at' => today()]);
            $transaction = BankTransaction::create(['bank_account_id' => $bank->id, 'booked_on' => today(), 'amount' => -$amount, 'description' => 'Synthetic payment', 'import_hash' => hash('sha256', 'ECO02-'.$index)]);
            BankReconciliation::create(['bank_transaction_id' => $transaction->id, 'supplier_payment_id' => $payment->id, 'method' => 'manual', 'reconciled_at' => now()]);
            app(AccountingSynchronizer::class)->syncSupplierPayment($payment);
        }
        $this->assertSame(2, $invoice->lines()->count());
        $this->assertSame(100.0, (float) $invoice->payments()->sum('amount'));
        $this->assertSame(1, DB::table('received_invoice_identities')->count());
        $this->assertSame(2, DB::table('bank_reconciliations')->count());
        $this->assertSame($project->id, $invoice->fresh()->installation_id);
    }

    public function test_missing_archive_path_is_not_replaced_and_explicit_reconstruction_is_admin_only(): void
    {
        Storage::fake(config('filesystems.private'));
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Synthetic customer']);
        $document = SalesDocument::create(['person_id' => $customer->id, 'type' => 'invoice', 'status' => 'issued', 'number' => 'SYN-HISTORICAL', 'locked_at' => now(), 'issue_date' => today(), 'subtotal' => 100, 'tax_amount' => 0, 'amount' => 100, 'customer_snapshot' => ['first_name' => 'Historical customer']]);
        $document->lines()->create(['position' => 1, 'description' => 'Historical available line', 'quantity' => 1, 'unit_price' => 100, 'subtotal' => 100, 'tax_amount' => 0, 'total' => 100]);
        $document->forceFill(['archived_pdf_path' => 'issued-invoices/missing.pdf', 'archived_pdf_hash' => hash('sha256', 'lost original')])->save();
        $before = $document->fresh()->getAttributes();
        $commercial = User::factory()->create(['role' => 'commercial']);
        $this->actingAs($commercial)->get(route('sales-documents.pdf', $document))->assertStatus(409);
        $this->get(route('sales-documents.informative-pdf', $document))->assertForbidden();
        $this->assertSame(0, DB::table('audit_logs')->where('event', 'informative_invoice_reconstruction')->count());
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('sales-documents.informative-pdf', $document))->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('Content-Disposition', 'attachment; filename="RECONSTRUCCION-SYN-HISTORICAL.pdf"');
        $this->assertSame(1, DB::table('audit_logs')->where('event', 'informative_invoice_reconstruction')->count());
        $this->assertSame($before, $document->fresh()->getAttributes());
        $this->assertSame([], Storage::disk(config('filesystems.private'))->allFiles());
        $this->get(route('sales-documents.pdf', $document))->assertStatus(409);
    }

    public function test_registry_cannot_be_edited_or_deleted_to_release_a_used_identity(): void
    {
        $this->purchase();
        $this->expectException(QueryException::class);
        DB::table('received_invoice_identities')->delete();
    }

    public function test_duplicate_attempt_rolls_back_the_entire_new_source_and_keeps_one_obligation(): void
    {
        $this->purchase();
        try {
            DB::transaction(fn () => $this->expense());
            $this->fail('Duplicate source must fail.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('received_invoice', $exception->getMessage());
        }
        $this->assertSame(0, Expense::count());
        $this->assertSame(1, PurchaseDocument::count());
        $this->assertSame(1, DB::table('received_invoice_identities')->count());
    }

    public function test_a_claimed_invoice_cannot_delete_its_attachment_before_a_rejected_delete(): void
    {
        Storage::fake(config('filesystems.private'));
        $invoice = $this->expense();
        $actor = User::factory()->create(['role' => 'admin']);
        Storage::disk(config('filesystems.private'))->put('received-original.pdf', 'original received bytes');
        $invoice->attachments()->create(['uploaded_by' => $actor->id, 'original_name' => 'original.pdf', 'path' => 'received-original.pdf', 'mime_type' => 'application/pdf', 'size' => 23]);
        $this->actingAs($actor)->delete(route('expenses.destroy', $invoice))->assertStatus(422);
        $this->assertTrue($invoice->fresh()->exists);
        $this->assertSame(1, $invoice->attachments()->count());
        $this->assertSame('original received bytes', Storage::disk(config('filesystems.private'))->get('received-original.pdf'));
    }

    public function test_migration_reversal_refuses_to_destroy_used_identity_evidence(): void
    {
        $invoice = $this->purchase();
        foreach (['100000_share_received_invoice_identity', '101000_guard_received_invoice_identity', '102000_scope_existing_invoice_identity_to_receiver', '103000_guard_received_invoice_economic_effects'] as $name) {
            $migration = require database_path('migrations/2026_10_26_'.$name.'.php');
            try {
                DB::transaction(fn () => $migration->down());
                $this->fail('A used identity must prevent destructive reversal.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('evidence', strtolower($exception->getMessage()));
            }
        }
        $this->assertSame(1, DB::table('received_invoice_identities')->count());
        $this->assertTrue($invoice->fresh()->exists);
    }

    public function test_empty_identity_schema_can_be_reversed_and_reapplied_without_rewriting_history(): void
    {
        EconomicFixture::legacy(fn () => $this->expense());
        $before = Expense::firstOrFail()->getAttributes();
        unset($before['receiver_company_id']);
        $migrations = [];
        foreach (['100000_share_received_invoice_identity', '101000_guard_received_invoice_identity', '102000_scope_existing_invoice_identity_to_receiver', '103000_guard_received_invoice_economic_effects'] as $name) {
            $migrations[] = require database_path('migrations/2026_10_26_'.$name.'.php');
        }
        foreach (array_reverse($migrations) as $migration) {
            DB::transaction(fn () => $migration->down());
        }
        $during = Expense::firstOrFail()->getAttributes();
        $this->assertSame($before, $during);
        foreach ($migrations as $migration) {
            DB::transaction(fn () => $migration->up());
        }
        $after = Expense::firstOrFail()->getAttributes();
        unset($after['receiver_company_id']);
        $this->assertSame($before, $after);
        $this->assertSame(0, DB::table('received_invoice_identities')->count());
    }
}
