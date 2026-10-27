-- Prepared for an explicitly authorized audit. DEPLOY02 executes ONLY on synthetic local data.
-- Requires the existing sales/banking/fiscal schema; inspect versions before use on another target.
BEGIN ISOLATION LEVEL REPEATABLE READ READ ONLY;
SET LOCAL statement_timeout = '60s';
SET LOCAL lock_timeout = '2s';

-- REVIEW: payment reconciliation with orphan, conflicting targets, amount or stale bank status.
-- A separately posted reclassification can have only journal_entry_id; it is not a missing payment.
SELECT r.id AS reconciliation_id, b.id AS bank_transaction_id,
       b.status AS bank_status, b.amount::text AS bank_amount,
       coalesce(sp.amount,cp.amount,tp.amount)::text AS linked_payment_amount,
       'manual_review_no_automatic_repair' AS classification
FROM bank_reconciliations r
LEFT JOIN bank_transactions b ON b.id=r.bank_transaction_id
LEFT JOIN supplier_payments sp ON sp.id=r.supplier_payment_id
LEFT JOIN sales_document_payments cp ON cp.id=r.sales_document_payment_id
LEFT JOIN tax_return_payments tp ON tp.id=r.tax_return_payment_id
WHERE b.id IS NULL
   OR b.status NOT IN ('reconciled','accounted')
   OR ((r.supplier_payment_id IS NOT NULL)::int+(r.sales_document_payment_id IS NOT NULL)::int+(r.tax_return_payment_id IS NOT NULL)::int)>1
   OR (r.supplier_payment_id IS NOT NULL AND sp.id IS NULL)
   OR (r.sales_document_payment_id IS NOT NULL AND cp.id IS NULL)
   OR (r.tax_return_payment_id IS NOT NULL AND tp.id IS NULL)
   OR (coalesce(sp.amount,cp.amount,tp.amount) IS NOT NULL AND abs(b.amount)<>abs(coalesce(sp.amount,cp.amount,tp.amount)))
ORDER BY r.id;

-- REVIEW: series counters at/below the issued maximum for the current native sales format.
-- No guessing or rewriting of historical/custom formats. Unmapped records are listed separately.
WITH numbered AS (
 SELECT s.id,s.next_number,d.id AS document_id,
        CASE WHEN left(d.number,length(s.prefix||'-'||y.year::text||'-'))=s.prefix||'-'||y.year::text||'-'
                  AND substring(d.number FROM length(s.prefix||'-'||y.year::text||'-')+1) ~ '^[0-9]+$'
             THEN substring(d.number FROM length(s.prefix||'-'||y.year::text||'-')+1)::numeric END AS sequence
 FROM sales_documents d
 JOIN document_series s ON s.id=d.document_series_id
 JOIN fiscal_years y ON y.id=s.fiscal_year_id
 WHERE d.locked_at IS NOT NULL OR d.status IN ('issued','partial','paid','overdue')
)
SELECT id AS series_id,next_number,max(sequence) AS maximum_issued_sequence,'blocking_counter_review' AS classification
FROM numbered GROUP BY id,next_number HAVING next_number<=max(sequence);

SELECT d.id AS document_id,'manual_review_unmapped_issued_number' AS classification
FROM sales_documents d LEFT JOIN document_series s ON s.id=d.document_series_id
LEFT JOIN fiscal_years y ON y.id=s.fiscal_year_id
WHERE (d.locked_at IS NOT NULL OR d.status IN ('issued','partial','paid','overdue'))
AND (s.id IS NULL OR y.id IS NULL OR left(d.number,length(s.prefix||'-'||y.year::text||'-'))<>s.prefix||'-'||y.year::text||'-'
     OR substring(d.number FROM length(s.prefix||'-'||y.year::text||'-')+1) !~ '^[0-9]+$')
ORDER BY d.id;
ROLLBACK;
