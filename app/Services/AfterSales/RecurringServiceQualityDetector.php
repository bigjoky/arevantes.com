<?php

namespace App\Services\AfterSales;

use App\Models\ServiceCycleCommitment;
use App\Models\ServiceCycleDeviation;
use Illuminate\Support\Collection;

class RecurringServiceQualityDetector
{
    /** @return Collection<int, ServiceCycleDeviation> */
    public function detect(ServiceCycleCommitment $commitment): Collection
    {
        $commitment->loadMissing(['maintenancePlan.installation', 'maintenancePlan.coveredAssets:id', 'serviceTicket']);
        if ($commitment->status !== 'completed' || ! $commitment->completion_snapshot) {
            return collect();
        }

        $businessLine = $commitment->maintenancePlan->installation?->service_type ?: 'other';
        $tolerances = array_replace(
            config('recurring_quality.tolerances.default', []),
            config("recurring_quality.tolerances.{$businessLine}", []),
        );
        $completion = $commitment->completion_snapshot;
        $technical = $commitment->technical_snapshot ?? [];
        $candidates = collect();

        $this->compare(
            $candidates,
            'duration',
            (float) $commitment->planned_minutes,
            (float) data_get($completion, 'time.minutes', 0),
            (float) $tolerances['duration_percent'],
            'minutes',
            'Execution duration is outside the approved tolerance.',
        );
        $this->compare(
            $candidates,
            'travel',
            (float) $commitment->planned_travel_km,
            (float) data_get($completion, 'time.travel_km', 0),
            (float) $tolerances['travel_percent'],
            'km',
            'Recorded travel is outside the approved tolerance.',
        );
        $this->compare(
            $candidates,
            'materials',
            (float) $commitment->planned_material_cost,
            (float) data_get($completion, 'materials.actual_cost', 0),
            (float) $tolerances['materials_percent'],
            'EUR',
            'Material consumption is outside the approved tolerance.',
        );

        $reopened = (int) ($commitment->serviceTicket?->reopened_count ?? 0);
        if ($reopened > (int) $tolerances['reopened_count']) {
            $candidates->push([
                'category' => 'recurrence', 'planned_value' => (float) $tolerances['reopened_count'],
                'actual_value' => (float) $reopened, 'variance_percent' => null,
                'tolerance_percent' => null, 'unit' => 'reopenings',
                'summary' => 'The service required an unplanned reopening.',
                'context' => ['ticket_id' => $commitment->service_ticket_id],
                'severity' => $reopened > 1 ? 'critical' : 'high',
            ]);
        }

        $requiredEvidence = collect(data_get($technical, 'template.required_evidence', []))
            ->filter(fn ($item) => ! is_array($item) || ($item['required'] ?? true))
            ->map(fn ($item, $index) => is_array($item) ? ($item['key'] ?? "evidence-{$index}") : (string) $item)
            ->values();
        $capturedEvidence = collect(data_get($completion, 'evidence.items', []))->pluck('evidence_key')->filter()->unique();
        $missingEvidence = $requiredEvidence->diff($capturedEvidence)->values();
        if ($missingEvidence->isNotEmpty()) {
            $candidates->push([
                'category' => 'evidence', 'planned_value' => (float) $requiredEvidence->count(),
                'actual_value' => (float) ($requiredEvidence->count() - $missingEvidence->count()),
                'variance_percent' => round(($missingEvidence->count() / max(1, $requiredEvidence->count())) * 100, 2),
                'tolerance_percent' => 0.0, 'unit' => 'items',
                'summary' => 'Required service evidence is incomplete.',
                'context' => ['missing_keys' => $missingEvidence->all()],
                'severity' => $missingEvidence->count() === $requiredEvidence->count() ? 'critical' : 'high',
            ]);
        }

        $requiredDocuments = collect(data_get($technical, 'template.handover_documents', []))
            ->filter(fn ($item) => ! is_array($item) || ($item['required'] ?? true));
        $publishedDocuments = (int) data_get($completion, 'evidence.published_documents', 0);
        $pendingDocuments = (int) data_get($completion, 'evidence.pending_documents', 0);
        if ($publishedDocuments < $requiredDocuments->count() || $pendingDocuments > 0) {
            $candidates->push([
                'category' => 'documents', 'planned_value' => (float) $requiredDocuments->count(),
                'actual_value' => (float) $publishedDocuments, 'variance_percent' => null,
                'tolerance_percent' => 0.0, 'unit' => 'documents',
                'summary' => 'Required handover documents are missing or pending review.',
                'context' => ['pending_documents' => $pendingDocuments],
                'severity' => $requiredDocuments->isNotEmpty() && $publishedDocuments === 0 ? 'critical' : 'high',
            ]);
        }

        $assetIds = collect(data_get($technical, 'plan.covered_asset_ids', []))
            ->merge($commitment->maintenancePlan->coveredAssets->modelKeys())
            ->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();

        return $candidates->map(function (array $candidate) use ($commitment, $businessLine, $assetIds): ServiceCycleDeviation {
            $deviation = ServiceCycleDeviation::firstOrCreate(
                ['service_cycle_commitment_id' => $commitment->id, 'category' => $candidate['category']],
                [
                    'business_line' => $businessLine, 'severity' => $candidate['severity'], 'status' => 'open',
                    'planned_value' => $candidate['planned_value'], 'actual_value' => $candidate['actual_value'],
                    'variance_percent' => $candidate['variance_percent'], 'tolerance_percent' => $candidate['tolerance_percent'],
                    'unit' => $candidate['unit'], 'summary' => $candidate['summary'],
                    'detection_context' => $candidate['context'] ?? [],
                    'assigned_to' => $commitment->maintenancePlan->assigned_user_id,
                    'review_due_on' => today()->addWeekdays((int) config('recurring_quality.review_due_weekdays', 3)),
                    'detected_at' => now(),
                ],
            );
            if ($deviation->wasRecentlyCreated) {
                $deviation->events()->create([
                    'event' => 'detected', 'to_status' => 'open',
                    'changes' => ['severity' => $deviation->severity, 'category' => $deviation->category],
                    'created_at' => now(),
                ]);
            } elseif ($deviation->status === 'open') {
                $deviation->update([
                    'severity' => $candidate['severity'], 'planned_value' => $candidate['planned_value'],
                    'actual_value' => $candidate['actual_value'], 'variance_percent' => $candidate['variance_percent'],
                    'tolerance_percent' => $candidate['tolerance_percent'], 'summary' => $candidate['summary'],
                    'detection_context' => $candidate['context'] ?? [],
                ]);
            }
            $deviation->assets()->syncWithoutDetaching($assetIds);

            return $deviation;
        });
    }

    /** @param Collection<int, array<string, mixed>> $candidates */
    private function compare(Collection $candidates, string $category, float $planned, float $actual, float $tolerance, string $unit, string $summary): void
    {
        $variance = $planned == 0.0 ? ($actual == 0.0 ? 0.0 : 100.0) : abs(($actual - $planned) / $planned) * 100;
        if ($variance <= $tolerance) {
            return;
        }

        $ratio = $tolerance > 0 ? $variance / $tolerance : 3;
        $candidates->push([
            'category' => $category, 'planned_value' => $planned, 'actual_value' => $actual,
            'variance_percent' => round($variance, 2), 'tolerance_percent' => $tolerance, 'unit' => $unit,
            'summary' => $summary, 'context' => ['direction' => $actual >= $planned ? 'over' : 'under'],
            'severity' => $ratio >= 3 ? 'critical' : ($ratio >= 2 ? 'high' : 'medium'),
        ]);
    }
}
