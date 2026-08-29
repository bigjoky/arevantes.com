<?php

namespace App\Services\AfterSales;

use App\Models\ServiceCycleDeviation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecurringServiceQualityWorkflow
{
    public function review(ServiceCycleDeviation $deviation, string $action, ?int $assignedTo, ?string $dueOn, string $note, User $actor): ServiceCycleDeviation
    {
        return DB::transaction(function () use ($deviation, $action, $assignedTo, $dueOn, $note, $actor): ServiceCycleDeviation {
            $locked = ServiceCycleDeviation::query()->lockForUpdate()->findOrFail($deviation->id);
            $from = $locked->status;
            $allowed = [
                'assign' => ['open', 'confirmed'],
                'confirm' => ['open'],
                'dismiss' => ['open'],
                'resolve' => ['confirmed'],
                'reopen' => ['dismissed', 'resolved'],
            ];
            if (! in_array($from, $allowed[$action] ?? [], true)) {
                throw ValidationException::withMessages(['action' => __('This quality review action is not valid for the current status.')]);
            }

            $changes = [];
            if ($assignedTo) {
                $changes['assigned_to'] = $assignedTo;
            }
            if ($dueOn) {
                $changes['review_due_on'] = $dueOn;
            }
            $to = $from;
            if ($action === 'confirm') {
                $to = 'confirmed';
                $changes += ['status' => $to, 'confirmed_by' => $actor->id, 'confirmed_at' => now(), 'dismissed_by' => null, 'dismissed_at' => null];
            } elseif ($action === 'dismiss') {
                $to = 'dismissed';
                $changes += ['status' => $to, 'dismissed_by' => $actor->id, 'dismissed_at' => now(), 'resolution_note' => trim($note)];
            } elseif ($action === 'resolve') {
                $to = 'resolved';
                $changes += ['status' => $to, 'resolved_by' => $actor->id, 'resolved_at' => now(), 'resolution_note' => trim($note)];
            } elseif ($action === 'reopen') {
                $to = 'open';
                $changes += [
                    'status' => $to, 'confirmed_by' => null, 'confirmed_at' => null,
                    'dismissed_by' => null, 'dismissed_at' => null, 'resolved_by' => null,
                    'resolved_at' => null, 'resolution_note' => null,
                ];
            }
            $locked->update($changes);
            $locked->events()->create([
                'event' => $action, 'from_status' => $from, 'to_status' => $to,
                'note' => trim($note), 'changes' => $changes, 'actor_id' => $actor->id, 'created_at' => now(),
            ]);

            return $locked->refresh();
        });
    }
}
