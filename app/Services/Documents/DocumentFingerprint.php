<?php

namespace App\Services\Documents;

use App\Models\Quote;
use App\Models\SalesDocument;

class DocumentFingerprint
{
    /** @return array{version: string, hash: string} */
    public function for(Quote|SalesDocument $document): array
    {
        $document instanceof Quote
            ? $document->loadMissing('lines.components')
            : $document->loadMissing('lines');
        $payload = [
            'document' => $document->only(['id', 'number', 'issue_date', 'due_date', 'valid_until', 'subtotal', 'discount_amount', 'tax_amount', 'amount', 'customer_snapshot', 'notes']),
            'lines' => $document->lines->map(function ($line) use ($document): array {
                $data = $line->only(['parent_line_id', 'depth', 'position', 'selection_mode', 'option_group', 'is_selected', 'description', 'quantity', 'unit_price', 'discount_percent', 'tax_percent', 'subtotal', 'discount_amount', 'tax_amount', 'total']);
                if ($document instanceof Quote) {
                    $data['components'] = $line->components->map->only(['parent_component_id', 'depth', 'position', 'kind', 'fulfillment_kind', 'visibility', 'description', 'quantity', 'planned_unit_cost', 'interval_months'])->values()->all();
                }

                return $data;
            })->values()->all(),
        ];
        $hash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
        $version = $document instanceof Quote
            ? 'quote-v'.$document->version.'-'.substr($hash, 0, 12)
            : 'document-'.substr($hash, 0, 16);

        return ['version' => $version, 'hash' => $hash];
    }
}
