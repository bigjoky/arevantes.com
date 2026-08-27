<?php

namespace App\Services\AfterSales;

use App\Models\RecurringServiceBilling;
use App\Models\SalesDocument;
use App\Models\ServiceCycleCommitment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecurringServiceBillingWorkflow
{
    /** @return array{document: SalesDocument, created: bool} */
    public function prepare(ServiceCycleCommitment $commitment, float $taxPercent, int $dueDays, string $note, User $actor): array
    {
        return DB::transaction(function () use ($commitment, $taxPercent, $dueDays, $note, $actor): array {
            $locked = ServiceCycleCommitment::query()
                ->with(['maintenancePlan.installation.customer'])
                ->lockForUpdate()
                ->findOrFail($commitment->id);
            if (! in_array($locked->status, ['approved', 'completed'], true)) {
                throw ValidationException::withMessages(['billing' => __('Only approved or completed service cycles can be invoiced.')]);
            }

            $plan = $locked->maintenancePlan;
            $installation = $plan->installation;
            $customer = $installation?->customer;
            if (! $customer) {
                throw ValidationException::withMessages(['billing' => __('The service cycle requires an installation customer before billing can be prepared.')]);
            }
            if ((float) $locked->planned_revenue <= 0) {
                throw ValidationException::withMessages(['billing' => __('The approved service cycle has no billable amount.')]);
            }

            $billing = RecurringServiceBilling::query()->where('service_cycle_commitment_id', $locked->id)->first();
            if ($billing?->invoice) {
                return ['document' => $billing->invoice, 'created' => false];
            }

            $net = $billing ? (float) $billing->net_amount : round((float) $locked->planned_revenue, 2);
            $tax = $billing ? (float) $billing->tax_percent : round($taxPercent, 2);
            $taxAmount = round($net * $tax / 100, 2);
            $gross = round($net + $taxAmount, 2);
            if ($gross > 9999999999.99) {
                throw ValidationException::withMessages(['billing' => __('The recurring invoice total exceeds the supported sales document amount.')]);
            }
            $dueOn = $billing?->due_on ?: today()->addDays($dueDays);
            $generationNote = $billing?->generation_note ?: trim($note);
            $description = __('Recurring service :plan · cycle :from to :to · contract version :version', [
                'plan' => $plan->name,
                'from' => $locked->period_starts_on->format('d/m/Y'),
                'to' => $locked->period_ends_on->format('d/m/Y'),
                'version' => $locked->maintenance_plan_version,
            ]);
            $document = SalesDocument::create([
                'type' => 'invoice',
                'person_id' => $customer->id,
                'installation_id' => $installation->id,
                'created_by' => $actor->id,
                'number' => 'DRAFT-RSI-'.Str::upper(Str::random(8)),
                'status' => 'draft',
                'issue_date' => today(),
                'due_date' => $dueOn,
                'billing_label' => __('Recurring service cycle'),
                'subtotal' => $net,
                'discount_amount' => 0,
                'tax_amount' => $taxAmount,
                'amount' => $gross,
                'paid_amount' => 0,
                'customer_snapshot' => $customer->only(['first_name', 'last_name', 'company_name', 'tax_id', 'document_number', 'billing_email', 'email', 'address_line_1', 'address_line_2', 'postal_code', 'city', 'province_state', 'country']),
                'notes' => $generationNote,
            ]);
            $document->lines()->create([
                'position' => 1,
                'description' => $description,
                'quantity' => 1,
                'unit_price' => $net,
                'discount_percent' => 0,
                'tax_percent' => $tax,
                'subtotal' => $net,
                'discount_amount' => 0,
                'tax_amount' => $taxAmount,
                'total' => $gross,
            ]);

            if ($billing) {
                $billing->update(['sales_document_id' => $document->id]);
            } else {
                RecurringServiceBilling::create([
                    'service_cycle_commitment_id' => $locked->id,
                    'sales_document_id' => $document->id,
                    'status' => 'draft',
                    'net_amount' => $net,
                    'tax_percent' => $tax,
                    'tax_amount' => $taxAmount,
                    'gross_amount' => $gross,
                    'due_on' => $dueOn,
                    'generation_note' => $generationNote,
                    'prepared_by' => $actor->id,
                    'prepared_at' => now(),
                ]);
            }

            return ['document' => $document, 'created' => true];
        });
    }
}
