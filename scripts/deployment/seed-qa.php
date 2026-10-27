<?php

use App\Models\CompanyProfile;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Person;
use App\Models\PurchaseDocument;
use App\Models\Quote;
use App\Models\SalesDocument;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('local') || DB::connection()->getDatabaseName() !== 'arevantes_deploy02_qa_test' || config('app.url') !== 'http://127.0.0.1:8027') {
    throw new RuntimeException('Only the isolated synthetic QA application is allowed.');
}
$credentials = json_decode(file_get_contents(storage_path('qa-credentials.json')), true, flags: JSON_THROW_ON_ERROR);
if (User::where('email', 'admin@qa.invalid')->exists()) {
    throw new RuntimeException('Synthetic QA already seeded; refusing to reset accounts.');
}
// Never retain the framework factory password on the local QA seed account.
User::query()->each(fn (User $user) => $user->update(['password' => Hash::make(bin2hex(random_bytes(32)))]));
$admin = User::create(['name' => 'QA synthetic administrator', 'email' => $credentials['email'], 'role' => 'admin', 'password' => Hash::make($credentials['password']), 'email_verified_at' => now()]);
User::create(['name' => 'QA synthetic technician', 'email' => 'technician@qa.invalid', 'role' => 'technician', 'password' => Hash::make($credentials['technician_password']), 'email_verified_at' => now()]);
$company = CompanyProfile::firstOrCreate([], ['legal_name' => 'QA synthetic issuer']);
$company->update(['legal_name' => 'QA synthetic issuer', 'trade_name' => 'QA only', 'tax_id' => 'B12345678', 'address' => 'Synthetic test address', 'country' => 'ES', 'email' => 'issuer@qa.invalid', 'iban' => null, 'phone' => null]);
$customer = Person::create(['category' => 'customer', 'first_name' => 'QA synthetic customer', 'tax_id' => 'B87654321', 'email' => 'customer@qa.invalid']);
$quote = Quote::create(['person_id' => $customer->id, 'created_by' => $admin->id, 'number' => 'QA-PROPOSAL-001', 'version' => 1, 'status' => 'draft', 'approval_status' => 'not_required', 'issue_date' => today(), 'valid_until' => today()->addDays(10), 'amount' => '121.00', 'subtotal' => '100.00', 'tax_amount' => '21.00', 'discount_amount' => 0, 'customer_snapshot' => $customer->customerSnapshot()]);
$invoice = SalesDocument::create(['type' => 'invoice', 'person_id' => $customer->id, 'number' => 'QA-DRAFT-001', 'status' => 'draft', 'issue_date' => today(), 'subtotal' => '100.00', 'tax_amount' => '21.00', 'amount' => '121.00', 'paid_amount' => 0, 'customer_snapshot' => $customer->customerSnapshot()]);
$invoice->lines()->create(['position' => 1, 'description' => 'QA synthetic material', 'quantity' => 1, 'unit_price' => 100, 'subtotal' => 100, 'discount_percent' => 0, 'tax_percent' => 21, 'tax_amount' => 21, 'total' => 121]);
$supplier = Supplier::create(['legal_name' => 'QA synthetic supplier', 'tax_id' => 'B11111111', 'country' => 'ES', 'email' => 'supplier@qa.invalid']);
PurchaseDocument::create(['supplier_id' => $supplier->id, 'type' => 'purchase_order', 'number' => 'QA-ORDER-001', 'status' => 'draft', 'issue_date' => today(), 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121]);
Expense::create(['expense_category_id' => ExpenseCategory::firstOrFail()->id, 'type' => 'invoice', 'number' => 'QA-EXPENSE-001', 'issuer_tax_id' => 'B11111111', 'issuer_country' => 'ES', 'document_number' => 'QA-EXT-001', 'expense_date' => today(), 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121, 'paid_amount' => 0]);
file_put_contents(storage_path('qa-fixtures.json'), json_encode(['quote_id' => $quote->id, 'invoice_id' => $invoice->id, 'customer_id' => $customer->id], JSON_PRETTY_PRINT));
