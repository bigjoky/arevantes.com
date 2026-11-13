<?php

namespace App\Services\HistoricalInvoices;

use App\Models\AuditLog;
use App\Models\CompanyProfile;
use App\Models\Expense;
use App\Models\FiscalYear;
use App\Models\Supplier;
use App\Models\TaxReturn;
use App\Models\User;
use App\Services\Accounting\AccountingPeriodManager;
use App\Services\Accounting\ExpenseRegistration;
use App\Services\Finance\ReceivedInvoiceIdentity;
use App\Services\Security\PermissionResolver;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Confirms only the narrow supported case; unresolved documents remain in staging. */
final class ConfirmInvoice
{
    public function confirm(int $rowId, User $user, string $accountingDate): Expense
    {
        abort_unless(config('fiscal.historical_invoice_2026') && $user->hasRole('admin') && $user->permission_profile_id
            && $user->can('manage-accounting') && app(PermissionResolver::class)->allows($user, 'manage-historical-invoices-2026'), 403);

        return DB::transaction(function () use ($rowId, $user, $accountingDate) {
            $row = DB::table('historical_invoice_rows')->where('id', $rowId)->lockForUpdate()->first();
            abort_unless($row, 404);
            if ($row->expense_id) {
                return Expense::findOrFail($row->expense_id);
            }
            if ($row->status !== 'review_required') {
                throw ValidationException::withMessages(['row' => 'El estado de la preparación no permite registrarla.']);
            }
            $data = json_decode($row->document, true, flags: JSON_THROW_ON_ERROR);
            if (array_diff(array_keys($data), [...CsvReader::COLUMNS, 'supplier_id', 'expense_category_id', 'notes']) !== [] || filled($data['received_date'] ?? null)) {
                throw ValidationException::withMessages(['row' => 'Este documento contiene datos que requieren revisión adicional.']);
            }
            $data['accounting_date'] = $accountingDate;
            Validator::make($data, [
                'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
                'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
                'supplier_tax_id' => ['required', 'string', 'max:32'],
                'supplier_number' => ['required', 'string', 'max:255'],
                'issue_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2026-01-01', 'before_or_equal:2026-12-31', 'before_or_equal:today'],
                'operation_date' => ['nullable', 'date_format:Y-m-d'],
                'received_date' => ['nullable', 'date_format:Y-m-d'],
                'due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:issue_date'],
                'accounting_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:issue_date'],
                'taxable_base' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
                'vat_rate' => ['required', 'numeric', 'between:0,100'],
                'vat_amount' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
                'withholding_rate' => ['required', 'numeric', 'in:0,0.0,0.00'],
                'withholding_amount' => ['required', 'numeric', 'in:0,0.0,0.00'],
                'total' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
                'purchase_document_id' => ['prohibited'], 'deduction_year' => ['prohibited'], 'deduction_quarter' => ['prohibited'],
                'attachments' => ['prohibited'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ])->validate();
            if (json_decode($row->errors, true, flags: JSON_THROW_ON_ERROR) !== []) {
                throw ValidationException::withMessages(['row' => 'La preparación contiene incidencias pendientes de revisión.']);
            }
            $base = BigDecimal::of($data['taxable_base']);
            $vat = BigDecimal::of($data['vat_amount']);
            if (! $base->multipliedBy($data['vat_rate'])->dividedBy(100, 2, RoundingMode::HALF_UP)->isEqualTo($vat)
                || ! $base->plus($vat)->isEqualTo(BigDecimal::of($data['total']))) {
                throw ValidationException::withMessages(['total' => 'El desglose fiscal no coincide con el total.']);
            }
            $taxKey = fn ($id) => strtoupper(str_replace([' ', '-', '.'], '', trim($id ?? '')));
            // Ordinary expense registration locks fiscal identity before touching
            // supplier accounts. Keep the same order to avoid inverse waits.
            $identity = app(ReceivedInvoiceIdentity::class);
            $identity->lockInput('ES', $data['supplier_tax_id'], 'invoice', $data['supplier_number']);
            $supplier = Supplier::whereKey($data['supplier_id'])->lockForUpdate()->firstOrFail();
            if (! $supplier->is_active || strtoupper($supplier->country ?? 'ES') !== 'ES' || $taxKey($supplier->tax_id) !== $taxKey($data['supplier_tax_id']) || CompanyProfile::count() !== 1) {
                throw ValidationException::withMessages(['supplier_id' => 'Identidad del proveedor o receptor no verificada.']);
            }
            $query = DB::table('received_invoice_candidates')->where('receiver_company_id', CompanyProfile::min('id'))->where('issuer_country', 'ES')->where('issuer_tax_id', $taxKey($data['supplier_tax_id']))->where('document_kind', 'invoice')->where('external_number', trim($data['supplier_number']));
            if ($query->exists()) {
                throw ValidationException::withMessages(['supplier_number' => 'Esta identidad ya existe en compras o gastos.']);
            }
            // A historical invoice and its posting can fall in different fiscal
            // years. Lock both in chronological order before checking returns.
            $years = FiscalYear::where(function ($query) use ($data, $accountingDate) {
                foreach (array_unique([$data['issue_date'], $accountingDate]) as $date) {
                    $query->orWhere(fn ($q) => $q->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date));
                }
            })->orderBy('starts_on')->lockForUpdate()->get();
            $yearFor = fn ($date) => $years->first(fn (FiscalYear $year) => $year->starts_on->toDateString() <= $date && $year->ends_on->toDateString() >= $date);
            $year = $yearFor($accountingDate);
            if (! $yearFor($data['issue_date']) || ! $year || $year->status !== 'open') {
                throw ValidationException::withMessages(['accounting_date' => 'El ejercicio contable no está abierto.']);
            }
            app(AccountingPeriodManager::class)->assertOpen($accountingDate);
            foreach (array_unique([$data['issue_date'], $accountingDate]) as $date) {
                if (TaxReturn::whereIn('status', ['prepared', 'filed'])->whereDate('period_from', '<=', $date)->whereDate('period_to', '>=', $date)->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages(['accounting_date' => 'Una declaración protegida requiere revisión fiscal.']);
                }
            }
            $files = [];
            if ($row->evidence) {
                $evidence = json_decode($row->evidence, true, flags: JSON_THROW_ON_ERROR);
                $storage = Storage::disk(config('filesystems.private'));
                if (! isset($evidence['path'], $evidence['sha256'], $evidence['original_name'], $evidence['mime_type'], $evidence['size'])
                    || ! str_starts_with($evidence['path'], 'historical-invoices/') || ! $storage->exists($evidence['path'])
                    || $storage->size($evidence['path']) > 10485760 || $storage->size($evidence['path']) !== (int) $evidence['size']
                    || ! hash_equals($evidence['sha256'], hash('sha256', $storage->get($evidence['path'])))) {
                    throw ValidationException::withMessages(['evidence' => 'No se puede verificar el justificante privado.']);
                }
                $files[] = collect($evidence)->only(['path', 'original_name', 'mime_type', 'size'])->all();
            }
            $expense = app(ExpenseRegistration::class)->register([
                'expense_category_id' => $data['expense_category_id'], 'supplier_id' => $supplier->id,
                'type' => 'invoice', 'document_number' => $data['supplier_number'], 'issuer_legal_name' => $supplier->legal_name,
                'issuer_tax_id' => $data['supplier_tax_id'], 'issuer_country' => 'ES', 'expense_date' => $data['issue_date'],
                'operation_date' => ($data['operation_date'] ?? null) ?: null, 'due_date' => ($data['due_date'] ?? null) ?: null,
                'currency_code' => 'EUR', 'tax_regime' => 'standard', 'deductible_expense_percent' => 100,
                'subtotal' => $data['taxable_base'], 'tax_amount' => $data['vat_amount'], 'withholding_rate' => 0, 'withholding_amount' => 0,
                'notes' => $data['notes'] ?? null,
            ], [[
                'position' => 1, 'kind' => 'vat', 'taxable_base' => $data['taxable_base'], 'tax_rate' => $data['vat_rate'],
                'tax_amount' => $data['vat_amount'], 'deductible_percent' => 100, 'deductible_amount' => $data['vat_amount'],
            ]], $files, $user->id, $accountingDate);
            DB::table('historical_invoice_rows')->where('id', $rowId)->update([
                'expense_id' => $expense->id, 'status' => 'registered', 'accounting_date' => $accountingDate,
                'confirmed_by' => $user->id, 'confirmed_at' => now(), 'updated_at' => now(),
            ]);
            AuditLog::create(['user_id' => $user->id, 'event' => 'historical_invoice_confirmed', 'auditable_type' => 'historical_invoice_row', 'auditable_id' => $rowId, 'new_values' => ['expense_id' => $expense->id, 'accounting_date' => $accountingDate]]);

            return $expense;
        });
    }
}
