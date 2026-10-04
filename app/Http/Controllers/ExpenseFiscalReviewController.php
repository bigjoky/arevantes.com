<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Expense;
use App\Services\Tax\ExpenseFiscalReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseFiscalReviewController extends Controller
{
    public function show(Request $request, Expense $expense, ExpenseFiscalReview $review): Response
    {
        abort_unless($request->user()->hasRole('admin'), 403);
        $expense->load(['taxLines', 'fiscalClassification.reviewer:id,name']);
        $protection = $review->protectionIssues($expense);

        return Inertia::render('tax/expense-classification', [
            'expense' => $expense->only(['id', 'number', 'document_number', 'issuer_legal_name', 'expense_date', 'subtotal', 'tax_amount']),
            'classification' => $expense->fiscalClassification ? [...$expense->fiscalClassification->attributesToArray(),
                'reviewed_by_name' => $expense->fiscalClassification->evidence_snapshot['reviewer']['name'] ?? $expense->fiscalClassification->reviewer?->name] : null,
            'revision' => $review->revision($expense), 'current' => $review->isCurrent($expense),
            'issues' => array_values(array_unique([...$review->issues($expense), ...$protection])), 'canReview' => $protection === [],
            'taxLines' => $expense->taxLines->map(fn ($line) => $line->only(['id', 'taxable_base', 'tax_rate', 'tax_amount', 'deductible_percent', 'deductible_amount'])),
            'history' => AuditLog::where('auditable_type', $expense->getMorphClass())->where('auditable_id', $expense->id)
                ->where('event', 'expense_fiscal_classified')->with('user:id,name')->latest('id')->limit(30)->get()
                ->map(fn ($log) => ['id' => $log->id, 'author' => $log->new_values['evidence_snapshot']['reviewer']['name'] ?? $log->user?->name, 'date' => $log->created_at->toIso8601String(),
                    'operation_class' => $log->new_values['operation_class'], 'reason' => $log->new_values['reason']]),
        ]);
    }

    public function update(Request $request, Expense $expense, ExpenseFiscalReview $review): RedirectResponse
    {
        $data = $request->validate(['operation_class' => ['required', Rule::in(ExpenseFiscalReview::CLASSES)],
            'revision' => ['required', 'string', 'size:64'], 'reason' => ['required', 'string', 'max:1000'],
            'confirmed' => ['nullable', 'boolean']]);
        $review->save($expense, $data, $request);

        return back()->with('toast', ['type' => 'success', 'message' => __('Fiscal classification saved without changing invoice amounts or payments.')]);
    }
}
