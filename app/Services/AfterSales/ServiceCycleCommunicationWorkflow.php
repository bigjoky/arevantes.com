<?php

namespace App\Services\AfterSales;

use App\Models\ServiceCycleCommitment;
use App\Models\ServiceCycleCommunication;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceCycleCommunicationWorkflow
{
    public function prepare(ServiceCycleCommitment $commitment, string $type, string $subject, string $message, string $scheduledFor, User $actor): ServiceCycleCommunication
    {
        return DB::transaction(function () use ($commitment, $type, $subject, $message, $scheduledFor, $actor): ServiceCycleCommunication {
            $locked = ServiceCycleCommitment::query()->with('maintenancePlan.installation.customer')->lockForUpdate()->findOrFail($commitment->id);
            if (! in_array($locked->status, ['approved', 'completed'], true)) {
                throw ValidationException::withMessages(['communication' => __('Only approved or completed service cycles can prepare customer communications.')]);
            }
            $customer = $locked->maintenancePlan->installation?->customer;
            if (! $customer) {
                throw ValidationException::withMessages(['communication' => __('The service cycle has no customer to contact.')]);
            }

            $existing = ServiceCycleCommunication::query()
                ->where('service_cycle_commitment_id', $locked->id)
                ->where('type', $type)
                ->latest('revision')
                ->first();
            if ($existing) {
                if ($existing->status === 'draft') {
                    $existing->update([
                        'channel' => $this->preferredChannel($customer),
                        'subject' => trim($subject),
                        'message' => trim($message),
                        'scheduled_for' => $scheduledFor,
                        'created_by' => $actor->id,
                    ]);

                    return $existing;
                }

                if ($existing->scheduled_for->equalTo(Carbon::parse($scheduledFor)) && $existing->subject === trim($subject) && $existing->message === trim($message)) {
                    return $existing;
                }
            }

            return ServiceCycleCommunication::create([
                'service_cycle_commitment_id' => $locked->id,
                'person_id' => $customer->id,
                'type' => $type,
                'revision' => ($existing?->revision ?? 0) + 1,
                'supersedes_communication_id' => $existing?->id,
                'channel' => $this->preferredChannel($customer),
                'status' => 'draft',
                'subject' => trim($subject),
                'message' => trim($message),
                'scheduled_for' => $scheduledFor,
                'created_by' => $actor->id,
            ]);
        });
    }

    public function confirm(ServiceCycleCommunication $communication, User $actor): ServiceCycleCommunication
    {
        return DB::transaction(function () use ($communication, $actor): ServiceCycleCommunication {
            $locked = ServiceCycleCommunication::query()->with(['customer.portalPreference'])->lockForUpdate()->findOrFail($communication->id);
            if ($locked->superseded_at) {
                throw ValidationException::withMessages(['communication' => __('This communication was superseded by a newer revision.')]);
            }
            if ($locked->status === 'confirmed' || $locked->status === 'sent') {
                return $locked;
            }
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['communication' => __('Only draft communications can be confirmed.')]);
            }
            $customer = $locked->customer;
            if (! $customer || $customer->do_not_contact) {
                throw ValidationException::withMessages(['communication' => __('The customer does not allow proactive contact.')]);
            }
            if ($locked->channel !== $this->preferredChannel($customer)) {
                throw ValidationException::withMessages(['communication' => __('The customer contact preference changed. Revise the draft before confirmation.')]);
            }
            if ($locked->channel === 'email' && (blank($customer->email) || $customer->portalPreference && ! $customer->portalPreference->email_maintenance)) {
                throw ValidationException::withMessages(['communication' => __('Maintenance email notifications are disabled or the customer has no email address.')]);
            }
            if (in_array($locked->channel, ['phone', 'whatsapp', 'sms'], true) && blank($customer->phone)) {
                throw ValidationException::withMessages(['communication' => __('The preferred contact channel requires a customer phone number.')]);
            }

            $locked->update(['status' => 'confirmed', 'confirmed_by' => $actor->id, 'confirmed_at' => now()]);
            ServiceCycleCommunication::query()
                ->where('service_cycle_commitment_id', $locked->service_cycle_commitment_id)
                ->where('type', $locked->type)
                ->where('id', '!=', $locked->id)
                ->whereNull('superseded_at')
                ->update(['superseded_at' => now()]);

            return $locked;
        });
    }

    public function markSent(ServiceCycleCommunication $communication, string $deliveryNote, User $actor): ServiceCycleCommunication
    {
        return DB::transaction(function () use ($communication, $deliveryNote, $actor): ServiceCycleCommunication {
            $locked = ServiceCycleCommunication::query()->lockForUpdate()->findOrFail($communication->id);
            if ($locked->superseded_at) {
                throw ValidationException::withMessages(['communication' => __('This communication was superseded by a newer revision.')]);
            }
            if ($locked->status === 'sent') {
                return $locked;
            }
            if ($locked->status !== 'confirmed') {
                throw ValidationException::withMessages(['communication' => __('Only confirmed communications can be marked as sent.')]);
            }
            $locked->update([
                'status' => 'sent',
                'sent_by' => $actor->id,
                'sent_at' => now(),
                'delivery_note' => trim($deliveryNote),
            ]);

            return $locked;
        });
    }

    public function reviseMaintenanceSchedule(ServiceCycleCommitment $commitment, string $scheduledFor, string $reason, User $actor): ?ServiceCycleCommunication
    {
        $previous = ServiceCycleCommunication::query()
            ->where('service_cycle_commitment_id', $commitment->id)
            ->where('type', 'maintenance_notice')
            ->whereIn('status', ['confirmed', 'sent'])
            ->latest('revision')
            ->first();
        if (! $previous) {
            return null;
        }

        $revision = $this->prepare(
            $commitment,
            'maintenance_notice',
            $previous->subject,
            __('The service visit has been replanned. :reason', ['reason' => trim($reason)]),
            $scheduledFor,
            $actor,
        );
        $previous->update(['superseded_at' => now()]);

        return $revision;
    }

    private function preferredChannel($customer): string
    {
        if (in_array($customer->preferred_contact_channel, ['email', 'phone', 'whatsapp', 'sms'], true)) {
            return $customer->preferred_contact_channel;
        }

        return $customer->email ? 'email' : 'phone';
    }
}
