<?php

namespace App\Http\Controllers;

use App\Models\CustomerPortalEvent;
use App\Models\MaintenancePlan;
use App\Models\ServiceCycleCommitment;
use App\Models\ServiceCycleCommunication;
use App\Services\AfterSales\RecurringServiceBillingWorkflow;
use App\Services\AfterSales\ServiceCycleCommitmentWorkflow;
use App\Services\AfterSales\ServiceCycleCommunicationWorkflow;
use App\Services\AfterSales\ServiceCycleScheduleWorkflow;
use App\Services\AfterSales\ServiceCycleWorkWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceCycleCommitmentController extends Controller
{
    public function store(Request $request, MaintenancePlan $plan, ServiceCycleCommitmentWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'plan_version' => ['required', 'integer', 'min:1'],
            'planned_travel_km' => ['required', 'numeric', 'between:0,100000'],
            'approval_note' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $workflow->approve($plan, (int) $data['plan_version'], (float) $data['planned_travel_km'], $data['approval_note'], $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('Service capacity and cycle economics approved. No work order or customer commitment was created.')]);
    }

    public function prepareBilling(Request $request, ServiceCycleCommitment $commitment, RecurringServiceBillingWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'tax_percent' => ['required', 'numeric', 'between:0,100'],
            'due_days' => ['required', 'integer', 'between:0,365'],
            'generation_note' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $result = $workflow->prepare($commitment, (float) $data['tax_percent'], (int) $data['due_days'], $data['generation_note'], $request->user());

        return to_route('sales-documents.show', $result['document'])->with('toast', [
            'type' => $result['created'] ? 'success' : 'info',
            'message' => $result['created'] ? __('Recurring invoice draft prepared for financial review.') : __('The recurring invoice draft already exists.'),
        ]);
    }

    public function materialize(Request $request, ServiceCycleCommitment $commitment, ServiceCycleWorkWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'scheduled_start' => ['required', 'date', 'after_or_equal:today'],
            'planning_note' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $result = $workflow->materialize($commitment, $data['scheduled_start'], $data['planning_note'], $request->user());

        return back()->with('toast', [
            'type' => $result['created'] ? 'success' : 'info',
            'message' => $result['created'] ? __('Service ticket and work order prepared. Confirm the schedule before publishing it or syncing it to mobile.') : __('The service work order already exists.'),
        ]);
    }

    public function confirmSchedule(Request $request, ServiceCycleCommitment $commitment, ServiceCycleScheduleWorkflow $workflow): RedirectResponse
    {
        $alreadyConfirmed = $commitment->schedule_status === 'confirmed';
        $revision = $workflow->confirm($commitment, $request->user());
        if (! $alreadyConfirmed) {
            $customer = $commitment->loadMissing('maintenancePlan.installation.customer')->maintenancePlan->installation?->customer;
            if ($customer) {
                CustomerPortalEvent::record($customer, 'service_schedule_confirmed', null, $request, $revision, [
                    'scheduled_start' => $revision->scheduled_start->toIso8601String(),
                    'scheduled_end' => $revision->scheduled_end->toIso8601String(),
                    'revision' => $revision->version,
                ]);
            }
        }

        return back()->with('toast', ['type' => 'success', 'message' => __('The service date is confirmed, visible to the customer and available to mobile technicians.')]);
    }

    public function prepareCommunication(Request $request, ServiceCycleCommitment $commitment, ServiceCycleCommunicationWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['maintenance_notice', 'billing_notice'])],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'min:5', 'max:5000'],
            'scheduled_for' => ['required', 'date', 'after_or_equal:today'],
        ]);
        $communication = $workflow->prepare($commitment, $data['type'], $data['subject'], $data['message'], $data['scheduled_for'], $request->user());

        return back()->with('toast', [
            'type' => $communication->wasRecentlyCreated ? 'success' : 'info',
            'message' => $communication->wasRecentlyCreated ? __('Customer communication prepared for confirmation.') : __('This cycle communication already exists.'),
        ]);
    }

    public function confirmCommunication(Request $request, ServiceCycleCommitment $commitment, ServiceCycleCommunication $communication, ServiceCycleCommunicationWorkflow $workflow): RedirectResponse
    {
        abort_unless($communication->service_cycle_commitment_id === $commitment->id, 404);
        $alreadyConfirmed = in_array($communication->status, ['confirmed', 'sent'], true);
        $communication = $workflow->confirm($communication, $request->user());
        if (! $alreadyConfirmed) {
            CustomerPortalEvent::record($communication->customer, 'service_communication_confirmed', null, $request, $communication, ['subject' => $communication->subject, 'channel' => $communication->channel, 'scheduled_for' => $communication->scheduled_for->toIso8601String()]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => __('Customer communication confirmed and visible in the portal.')]);
    }

    public function markCommunicationSent(Request $request, ServiceCycleCommitment $commitment, ServiceCycleCommunication $communication, ServiceCycleCommunicationWorkflow $workflow): RedirectResponse
    {
        abort_unless($communication->service_cycle_commitment_id === $commitment->id, 404);
        $data = $request->validate(['delivery_note' => ['required', 'string', 'min:5', 'max:2000']]);
        $alreadySent = $communication->status === 'sent';
        $communication = $workflow->markSent($communication, $data['delivery_note'], $request->user());
        if (! $alreadySent) {
            CustomerPortalEvent::record($communication->customer, 'service_communication_sent', null, $request, $communication, ['subject' => $communication->subject, 'channel' => $communication->channel]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => __('Customer communication delivery recorded.')]);
    }
}
