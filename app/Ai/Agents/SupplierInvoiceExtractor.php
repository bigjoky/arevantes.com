<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[UseCheapestModel]
class SupplierInvoiceExtractor implements Agent, HasStructuredOutput
{
    use Promptable;

    public function schema(JsonSchema $schema): array
    {
        return [
            'supplier_name' => $schema->string()->required(),
            'supplier_tax_id' => $schema->string()->required(),
            'invoice_number' => $schema->string()->required(),
            'issue_date' => $schema->string()->required(),
            'due_date' => $schema->string()->required(),
            'subtotal' => $schema->number()->required(),
            'tax_amount' => $schema->number()->required(),
            'total' => $schema->number()->required(),
            'currency' => $schema->string()->required(),
            'confidence' => $schema->number()->min(0)->max(1)->required(),
        ];
    }

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
You extract accounting fields from supplier invoices and supplier credit notes for Arevantes.
Use only visible information in the attached document. Never infer missing identifiers, dates or amounts.
Return dates as YYYY-MM-DD, currency as an ISO 4217 code, and decimal amounts without currency symbols.
Use an empty string for missing text or dates and zero only when the document explicitly shows a zero amount.
The subtotal is the taxable base before VAT, tax_amount is total VAT, and total is the final document amount.
For a credit note, return monetary amounts as negative values.
PROMPT;
    }
}
