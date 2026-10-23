<?php

namespace App\Services\Work;

use App\Models\WorkOrder;

class WorkOrderEvidenceRequirements
{
    /** @return array<int, array<string, mixed>> */
    public function progress(WorkOrder $order): array
    {
        $counts = $order->relationLoaded('attachments')
            ? $order->attachments->whereNotNull('evidence_key')->countBy('evidence_key')
            : $order->attachments()->reorder()->whereNotNull('evidence_key')
                ->selectRaw('evidence_key, count(*) as aggregate')->groupBy('evidence_key')
                ->pluck('aggregate', 'evidence_key');

        return collect($order->required_evidence ?? [])->map(function (array $requirement) use ($counts): array {
            $minimum = max(1, (int) ($requirement['min_count'] ?? 1));
            $captured = (int) ($counts[$requirement['key'] ?? ''] ?? 0);

            return [...$requirement, 'kind' => $requirement['kind'] ?? 'photo', 'min_count' => $minimum,
                'captured_count' => $captured, 'is_complete' => $captured >= $minimum];
        })->values()->all();
    }

    /** @return array<int, string> */
    public function allowedKeys(WorkOrder $order): array
    {
        return collect($order->required_evidence ?? [])->merge($order->handover_documents ?? [])
            ->pluck('key')->filter()->unique()->values()->all();
    }
}
