<?php

namespace Tests\Feature;

use App\Models\ExpenseCategory;
use App\Services\Finance\ReceivedInvoiceAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\EconomicFixture;
use Tests\TestCase;

class EconomicDeploymentPreflightTest extends TestCase
{
    use RefreshDatabase;

    private function expense(string $internal): array
    {
        return ['expense_category_id' => ExpenseCategory::firstOrFail()->id, 'type' => 'invoice', 'number' => $internal, 'issuer_country' => 'ES', 'issuer_tax_id' => 'B12345678', 'document_number' => 'PREFLIGHT-ONE', 'expense_date' => today(), 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121, 'paid_amount' => 0];
    }

    public function test_read_only_preflight_works_before_the_eco02_schema_exists(): void
    {
        Storage::fake(config('filesystems.private'));
        EconomicFixture::legacy(function (): void {
            $supplier = EconomicFixture::supplier(['legal_name' => 'Synthetic preflight supplier', 'country' => 'ES', 'tax_id' => 'B12345678']);
            EconomicFixture::purchase(['type' => 'supplier_invoice', 'supplier_id' => $supplier->id, 'supplier_number' => 'PREFLIGHT-ONE', 'number' => 'SYN-PREFLIGHT-PURCHASE', 'status' => 'registered', 'issue_date' => today(), 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121]);
            EconomicFixture::expense($this->expense('SYN-PREFLIGHT-EXPENSE'));
        });
        foreach (['103000_guard_received_invoice_economic_effects', '102000_scope_existing_invoice_identity_to_receiver', '101000_guard_received_invoice_identity', '100000_share_received_invoice_identity'] as $name) {
            $migration = require database_path('migrations/2026_10_26_'.$name.'.php');
            DB::transaction(fn () => $migration->down());
        }
        $before = [DB::table('purchase_documents')->get()->toJson(), DB::table('expenses')->get()->toJson(), DB::table('audit_logs')->count()];
        $writes = [];
        DB::listen(function ($query) use (&$writes): void {
            if (! str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
                $writes[] = $query->sql;
            }
        });
        $report = app(ReceivedInvoiceAudit::class)->report();
        $this->assertFalse($report['schema_identity_guards_installed']);
        $this->assertCount(1, $report['identity_collisions']);
        $this->assertSame('requires_identity_review', $report['identity_collisions'][0]['classification']);
        $this->assertTrue($report['read_only']);
        $this->assertSame([], $writes);
        $this->assertSame($before, [DB::table('purchase_documents')->get()->toJson(), DB::table('expenses')->get()->toJson(), DB::table('audit_logs')->count()]);
    }

    public function test_preflight_reports_duplicates_that_would_block_unique_index_migration(): void
    {
        Storage::fake(config('filesystems.private'));
        $migration = require database_path('migrations/2026_10_26_091000_enforce_existing_supplier_invoice_identity.php');
        $migration->down();
        EconomicFixture::legacy(function (): void {
            EconomicFixture::expense($this->expense('SYN-PREFLIGHT-ONE'));
            EconomicFixture::expense($this->expense('SYN-PREFLIGHT-TWO'));
        });
        $report = app(ReceivedInvoiceAudit::class)->report();
        $this->assertSame(2, $report['migration_blockers']['expense_unique_index_collisions'][0]['records']);
        $this->assertSame(2, DB::table('expenses')->count());
        $this->assertSame(0, DB::table('received_invoice_identities')->count());
    }
}
