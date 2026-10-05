export const purchaseIssueLabels: Record<string, string> = {
    purchase_review_pending:
        'Purchase VAT classification and deduction period require explicit review.',
    purchase_rectification_pending:
        'Supplier credit notes require separate fiscal treatment.',
    purchase_registration_pending:
        'The supplier invoice is not registered or requires review.',
    purchase_identity_missing:
        'The invoice needs a supplier number and retained legal name and tax ID.',
    purchase_territory_pending:
        'The retained supplier country does not establish Spanish domestic VAT treatment.',
    purchase_shipping_pending:
        'Shipping VAT needs documentary allocation; the legacy 21% estimate is not fiscal evidence.',
    purchase_lines_pending:
        'The line breakdown needs review: missing lines, unsupported rates, non-positive amounts or inconsistent totals.',
    purchase_totals_mismatch:
        'Stored line amounts do not exactly match invoice totals; unallocated differences remain pending.',
};
