<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PurchaseDocument;
use App\Services\Tax\PurchaseFiscalReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseFiscalReviewController extends Controller
{
    public function show(Request $request, PurchaseDocument $purchaseDocument, PurchaseFiscalReview $review): Response
    {
        abort_unless($request->user()->hasRole('admin'), 403);
        $purchaseDocument->load(['lines', 'fiscalClassification.reviewer:id,name']);
        $protection = $review->protectionIssues($purchaseDocument);

        return Inertia::render('tax/purchase-classification', [
            'purchase' => [
                ...$purchaseDocument->only(['id', 'number', 'subtotal', 'tax_amount']),
                'document_number' => $purchaseDocument->supplier_number,
                'issuer_legal_name' => $purchaseDocument->supplier_snapshot['legal_name'] ?? '',
                'issue_date' => $purchaseDocument->issue_date?->toDateString() ?? '',
            ],
            'classification' => $purchaseDocument->fiscalClassification ? [...$purchaseDocument->fiscalClassification->attributesToArray(),
                'reviewed_by_name' => $purchaseDocument->fiscalClassification->evidence_snapshot['reviewer']['name'] ?? $purchaseDocument->fiscalClassification->reviewer?->name] : null,
            'revision' => $review->revision($purchaseDocument), 'current' => $review->isCurrent($purchaseDocument),
            'issues' => array_values(array_unique([...$review->issues($purchaseDocument), ...$protection])), 'canReview' => $protection === [],
            'taxLines' => $purchaseDocument->lines->map(fn ($line) => [
                'id' => $line->id, 'taxable_base' => $line->subtotal, 'tax_rate' => $line->tax_percent,
                'tax_amount' => $line->tax_amount, 'deductible_percent' => '100', 'deductible_amount' => $line->tax_amount,
            ]),
            'history' => AuditLog::where('auditable_type', $purchaseDocument->getMorphClass())->where('auditable_id', $purchaseDocument->id)
                ->where('event', 'purchase_fiscal_classified')->with('user:id,name')->latest('id')->limit(30)->get()
                ->map(fn ($log) => ['id' => $log->id, 'author' => $log->new_values['evidence_snapshot']['reviewer']['name'] ?? $log->user?->name, 'date' => $log->created_at->toIso8601String(),
                    'operation_class' => $log->new_values['operation_class'], 'reason' => $log->new_values['reason'], 'source_reference' => $log->new_values['source_reference'] ?? '',
                    'registered_on' => $log->new_values['registered_on'] ?? null, 'deduction_year' => $log->new_values['deduction_year'] ?? null,
                    'deduction_quarter' => $log->new_values['deduction_quarter'] ?? null]),
        ]);
    }

    public function update(Request $request, PurchaseDocument $purchaseDocument, PurchaseFiscalReview $review): RedirectResponse
    {
        $data = $request->validate(['operation_class' => ['required', Rule::in(PurchaseFiscalReview::CLASSES)],
            'revision' => ['required', 'string', 'size:64'], 'reason' => ['required', 'string', 'max:1000'],
            'source_reference' => ['required', 'string', 'max:255'], 'confirmed' => ['nullable', 'boolean'],
            'registered_on' => ['nullable', 'date_format:Y-m-d'], 'deduction_year' => ['nullable', 'integer'],
            'deduction_quarter' => ['nullable', 'integer', 'between:1,4']]);
        $review->save($purchaseDocument, $data, $request);

        return back()->with('toast', ['type' => 'success', 'message' => __('Fiscal classification saved without changing invoice amounts or payments.')]);
    }
}
