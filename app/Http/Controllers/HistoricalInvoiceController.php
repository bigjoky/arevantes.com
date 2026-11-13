<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use App\Models\AuditLog;
use App\Models\Supplier;
use App\Rules\SafeUpload;
use App\Services\HistoricalInvoices\Access;
use App\Services\HistoricalInvoices\ConfirmInvoice;
use App\Services\HistoricalInvoices\CsvReader;
use App\Services\HistoricalInvoices\StageInvoices;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class HistoricalInvoiceController extends Controller
{
    private function authorizeAccess(Request $request): void
    {
        abort_unless(app(Access::class)->allows($request->user()), 403);
    }

    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);
        $rows = DB::table('historical_invoice_rows')->orderByDesc('id')->limit(100)->get()->map(fn ($row) => [
            'id' => $row->id, 'batch_id' => $row->batch_id, 'status' => $row->status,
            'document' => json_decode($row->document, true), 'errors' => json_decode($row->errors, true),
            'expense_id' => $row->expense_id, 'evidence' => $row->evidence ? json_decode($row->evidence, true)['original_name'] : null,
            'created_at' => $row->created_at, 'accounting_date' => $row->accounting_date,
        ]);

        return Inertia::render('billing/historical-invoices', ['rows' => $rows,
            'suppliers' => Supplier::where('is_active', true)->orderBy('legal_name')->get(['id', 'legal_name', 'tax_id']),
            'categories' => ExpenseCategory::orderBy('name')->get(['id', 'name'])]);
    }

    public function save(Request $request, ?int $id = null): RedirectResponse
    {
        $this->authorizeAccess($request);
        $data = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'], 'supplier_tax_id' => ['required', 'string', 'max:32'], 'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'supplier_number' => ['required', 'string', 'max:255'], 'issue_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2026-01-01', 'before_or_equal:2026-11-10'],
            'operation_date' => ['nullable', 'date_format:Y-m-d'], 'received_date' => ['nullable', 'date_format:Y-m-d'], 'due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:issue_date'],
            'taxable_base' => ['required', 'numeric', 'decimal:0,2', 'min:0'], 'vat_rate' => ['required', 'numeric', 'between:0,100'],
            'vat_amount' => ['required', 'numeric', 'decimal:0,2', 'min:0'], 'total' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
            'withholding_rate' => ['required', 'numeric', 'between:0,100'], 'withholding_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'], 'evidence' => ['nullable', 'file', new SafeUpload(['pdf'], 10240)],
        ]);
        $supplier = Supplier::whereKey($data['supplier_id'])->where('is_active', true)->firstOrFail();
        $file = $data['evidence'] ?? null;
        unset($data['evidence']);
        $taxKey = fn ($tax) => strtoupper(str_replace([' ', '-', '.'], '', trim($tax ?? '')));
        if ($taxKey($data['supplier_tax_id']) !== $taxKey($supplier->tax_id)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['supplier_tax_id' => 'El NIF no coincide con el proveedor seleccionado.']);
        }
        $evidence = null;
        if ($file) {
            $path = $file->store('historical-invoices/'.Str::uuid(), config('filesystems.private'));
            if (! is_string($path) || $path === '') {
                abort(500, 'No se pudo guardar el justificante.');
            }
            $evidence = ['path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath())];
        }
        try {
            DB::transaction(function () use ($request, $id, $data, $evidence) {
                if ($id) {
                    $row = DB::table('historical_invoice_rows')->where('id', $id)->lockForUpdate()->first();
                    abort_unless($row, 404);
                    abort_if($row->expense_id || $row->status !== 'review_required', 409);
                    $attributes = ['document' => json_encode($data, JSON_THROW_ON_ERROR), 'errors' => '[]', 'updated_at' => now()];
                    if ($evidence) {
                        // Preserve the previous file as audit evidence; never delete it on replacement.
                        $attributes['evidence'] = json_encode($evidence, JSON_THROW_ON_ERROR);
                    }
                    DB::table('historical_invoice_rows')->where('id', $id)->update($attributes);
                    AuditLog::create(['user_id' => $request->user()->id, 'event' => 'historical_invoice_preparation_updated', 'auditable_type' => 'historical_invoice_row', 'auditable_id' => $id, 'old_values' => ['document' => json_decode($row->document, true), 'evidence' => $row->evidence ? json_decode($row->evidence, true) : null], 'new_values' => ['document' => $data, 'evidence' => $evidence]]);
                } else {
                    $batch = DB::table('historical_invoice_batches')->insertGetId(['created_by' => $request->user()->id, 'source' => 'individual', 'created_at' => now(), 'updated_at' => now()]);
                    DB::table('historical_invoice_rows')->insert(['batch_id' => $batch, 'row_number' => 1, 'status' => 'review_required', 'document' => json_encode($data, JSON_THROW_ON_ERROR), 'errors' => '[]', 'evidence' => $evidence ? json_encode($evidence, JSON_THROW_ON_ERROR) : null, 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
                }
            });
        } catch (Throwable $exception) {
            if ($evidence) {
                Storage::disk(config('filesystems.private'))->delete($evidence['path']);
            }
            throw $exception;
        }

        return back();
    }

    public function import(Request $request, CsvReader $reader, StageInvoices $staging): RedirectResponse
    {
        $this->authorizeAccess($request);
        $request->validate(['csv' => ['required', 'file', 'max:1024', 'extensions:csv,txt']]);
        try {
            $rows = $reader->read($request->file('csv')->getRealPath());
        } catch (\RuntimeException $exception) {
            throw \Illuminate\Validation\ValidationException::withMessages(['csv' => $exception->getMessage()]);
        }
        $staging->fromCsv($rows, $request->user()->id);

        return back();
    }

    public function template(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorizeAccess($request);

        return response()->streamDownload(function () { $output = fopen('php://output', 'w'); fputcsv($output, CsvReader::COLUMNS, escape: ''); fclose($output); }, 'plantilla-facturas-2026.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function confirm(Request $request, int $id, ConfirmInvoice $service): RedirectResponse
    {
        $this->authorizeAccess($request);
        $data = $request->validate(['accounting_date' => ['required', 'date_format:Y-m-d']]);
        $expense = $service->confirm($id, $request->user(), $data['accounting_date']);

        return redirect('/billing/suppliers/expense/'.$expense->id);
    }
}
