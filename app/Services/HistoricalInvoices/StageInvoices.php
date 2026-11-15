<?php

namespace App\Services\HistoricalInvoices;

use App\Models\CompanyProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/** Preparation only: never creates an economic document, payment or journal. */
final class StageInvoices
{
    /** @param list<array{row:int, values:array<string,string>, errors:list<string>}> $rows */
    public function fromCsv(array $rows, int $userId): int
    {
        if (count($rows) > CsvReader::MAX_ROWS) {
            throw new \InvalidArgumentException('Demasiadas filas.');
        }

        return DB::transaction(function () use ($rows, $userId) {
            $batchId = DB::table('historical_invoice_batches')->insertGetId([
                'created_by' => $userId, 'source' => 'csv', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $seen = [];
            foreach ($rows as $row) {
                $data = $row['values'];
                $validator = Validator::make($data, [
                    'supplier_tax_id' => ['required', 'string', 'max:32'],
                    'supplier_number' => ['required', 'string', 'max:255'],
                    'issue_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2026-01-01', 'before_or_equal:2026-12-31', 'before_or_equal:today'],
                    'operation_date' => ['nullable', 'date_format:Y-m-d'],
                    'received_date' => ['nullable', 'date_format:Y-m-d'],
                    'due_date' => ['nullable', 'date_format:Y-m-d'],
                    'taxable_base' => ['required', 'numeric', 'min:0'],
                    'vat_rate' => ['required', 'numeric', 'between:0,100'],
                    'vat_amount' => ['required', 'numeric', 'min:0'],
                    'withholding_rate' => ['required', 'numeric', 'between:0,100'],
                    'withholding_amount' => ['required', 'numeric', 'min:0'],
                    'total' => ['required', 'numeric', 'min:0'],
                ]);
                $errors = [...$row['errors'], ...$validator->errors()->all()];
                $taxId = strtoupper(str_replace([' ', '-', '.'], '', trim($data['supplier_tax_id'] ?? '')));
                $number = trim($data['supplier_number'] ?? '');
                if ($taxId !== '' && $number !== '') {
                    $key = json_encode([$taxId, $number], JSON_THROW_ON_ERROR);
                    if (isset($seen[$key])) {
                        $errors[] = 'La identidad se repite en este lote; requiere revisión.';
                    }
                    $seen[$key] = true;
                    $receiver = CompanyProfile::min('id');
                    if (! $receiver || DB::table('received_invoice_candidates')->where('receiver_company_id', $receiver)->where('issuer_country', 'ES')->where('issuer_tax_id', $taxId)->where('document_kind', 'invoice')->where('external_number', $number)->exists()) {
                        $errors[] = 'Identidad existente o empresa receptora no identificada; requiere revisión.';
                    }
                }
                DB::table('historical_invoice_rows')->insert([
                    'batch_id' => $batchId, 'row_number' => $row['row'], 'status' => 'review_required',
                    'document' => json_encode($data, JSON_THROW_ON_ERROR), 'errors' => json_encode($errors, JSON_THROW_ON_ERROR),
                    'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            return $batchId;
        });
    }
}
