<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseIssuerLookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate(['tax_id' => ['required', 'string', 'min:3', 'max:32']]);
        $taxId = strtoupper(str_replace([' ', '-', '.'], '', trim($data['tax_id'])));
        if (! preg_match('/^[A-Z0-9]{3,32}$/', $taxId)) {
            return response()->json(['candidates' => []]);
        }

        // Use the same indexed expression as the migration; never fuzzy-match a tax ID.
        $expression = fn (string $column): string => "upper(replace(replace(replace({$column}, ' ', ''), '-', ''), '.', ''))";
        $expenses = Expense::query()->whereRaw($expression('issuer_tax_id').' = ?', [$taxId])
            ->whereNotNull('issuer_legal_name')->where('issuer_legal_name', '<>', '')
            ->select(['issuer_legal_name', 'merchant_name'])
            ->selectRaw('max(number) as number, max(id) as latest_id')
            ->groupBy('issuer_legal_name', 'merchant_name')
            ->orderByDesc('latest_id')->limit(20)->get()
            ->map(fn (Expense $expense): array => [
                'legal_name' => $expense->issuer_legal_name,
                'trade_name' => $expense->merchant_name,
                'source' => 'expense', 'reference' => $expense->number,
            ]);
        $suppliers = Supplier::query()->where('is_active', true)
            ->whereRaw($expression('tax_id').' = ?', [$taxId])->orderBy('id')->limit(20)
            ->get(['legal_name', 'trade_name'])
            ->map(fn (Supplier $supplier): array => [
                'legal_name' => $supplier->legal_name, 'trade_name' => $supplier->trade_name,
                'source' => 'supplier', 'reference' => null,
            ]);

        return response()->json(['candidates' => $expenses->concat($suppliers)
            ->unique(fn (array $candidate): string => mb_strtoupper(trim($candidate['legal_name'])).'|'.mb_strtoupper(trim($candidate['trade_name'] ?? '')))
            ->values()->all()])->header('Cache-Control', 'private, no-store');
    }
}
