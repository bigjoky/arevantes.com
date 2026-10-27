<?php

namespace Tests\Feature;

use App\Models\ExpenseCategory;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EconomicFixture;
use Tests\TestCase;

class EconomicIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_document_identity_is_protected_beyond_request_validation(): void
    {
        $supplier = EconomicFixture::supplier(['legal_name' => 'Synthetic invoice issuer', 'country' => 'ES']);
        $data = ['type' => 'supplier_invoice', 'supplier_id' => $supplier->id, 'supplier_number' => 'SYNTHETIC-ONE', 'status' => 'registered', 'issue_date' => today(), 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121];
        EconomicFixture::purchase([...$data, 'number' => 'INTERNAL-ONE']);
        $this->expectException(UniqueConstraintViolationException::class);
        EconomicFixture::purchase([...$data, 'number' => 'INTERNAL-TWO']);
    }

    public function test_expense_identity_uses_the_existing_country_and_normalized_tax_id_rule(): void
    {
        $data = ['expense_category_id' => ExpenseCategory::firstOrFail()->id, 'number' => 'SYNTHETIC-ONE', 'type' => 'invoice', 'issuer_country' => null, 'issuer_tax_id' => 'A-12345678', 'issuer_legal_name' => 'Synthetic issuer', 'document_number' => 'EXT-ONE', 'expense_date' => today(), 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121];
        EconomicFixture::expense($data);
        $this->expectException(UniqueConstraintViolationException::class);
        EconomicFixture::expense([...$data, 'number' => 'SYNTHETIC-TWO', 'issuer_country' => 'ES', 'issuer_tax_id' => 'a12345678']);
    }

    public function test_duplicate_history_blocks_migration_without_partial_indexes_or_data_rewrites(): void
    {
        $migration = require database_path('migrations/2026_10_26_091000_enforce_existing_supplier_invoice_identity.php');
        $migration->down();
        $data = ['expense_category_id' => ExpenseCategory::firstOrFail()->id, 'type' => 'invoice', 'issuer_country' => 'ES', 'issuer_tax_id' => 'A12345678', 'issuer_legal_name' => 'Synthetic historical duplicate', 'document_number' => 'EXT-DUPLICATE', 'expense_date' => today(), 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121];
        EconomicFixture::legacy(fn () => EconomicFixture::expense([...$data, 'number' => 'SYNTHETIC-HISTORY-1']));
        EconomicFixture::legacy(fn () => EconomicFixture::expense([...$data, 'number' => 'SYNTHETIC-HISTORY-2']));
        $before = DB::table('expenses')->get()->toJson();
        try {
            $migration->up();
            $this->fail('Duplicate history must block migration.');
        } catch (UniqueConstraintViolationException $exception) {
            $this->assertNotEmpty($exception->errorInfo);
        }
        $this->assertSame($before, DB::table('expenses')->get()->toJson());
        $indexes = Schema::getIndexes('purchase_documents');
        $this->assertNotContains('purchase_supplier_document_identity_unique', array_column($indexes, 'name'));
    }
}
