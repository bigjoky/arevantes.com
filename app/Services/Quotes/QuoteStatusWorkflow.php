<?php

namespace App\Services\Quotes;

use App\Models\Quote;
use App\Models\User;
use App\Services\Inventory\ProjectMaterialPlanner;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuoteStatusWorkflow
{
    private const TRANSITIONS = [
        'draft' => ['sent', 'accepted', 'rejected', 'expired'],
        'sent' => ['accepted', 'rejected', 'expired'],
        'accepted' => [],
        'rejected' => [],
        'expired' => [],
    ];

    public function __construct(private readonly ProjectMaterialPlanner $materials, private readonly QuoteOperationalPlanner $operations) {}

    public function assertCanTransition(Quote $quote, string $status): void
    {
        if (in_array($status, ['sent', 'accepted'], true) && ! in_array($quote->approval_status, ['not_required', 'approved'], true)) {
            throw ValidationException::withMessages([
                'status' => $quote->approval_status === 'rejected'
                    ? __('This quote was rejected during commercial approval and must be revised before it can continue.')
                    : __('This quote requires commercial approval before it can be sent or accepted.'),
            ]);
        }

        if ($quote->status === $status) {
            return;
        }

        if (! in_array($status, self::TRANSITIONS[$quote->status] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => __('The quote cannot move from :from to :to.', ['from' => $quote->status, 'to' => $status]),
            ]);
        }

    }

    /** @return array<int, string> */
    public function availableTransitions(Quote $quote): array
    {
        return collect(self::TRANSITIONS[$quote->status] ?? [])
            ->reject(fn (string $status) => in_array($status, ['sent', 'accepted'], true)
                && ! in_array($quote->approval_status, ['not_required', 'approved'], true))
            ->values()
            ->all();
    }

    public function apply(Quote $quote, string $status, ?User $actor = null, string $channel = 'internal', ?string $reason = null): void
    {
        $this->assertCanTransition($quote, $status);
        if ($quote->status === $status) {
            return;
        }

        DB::transaction(function () use ($quote, $status, $actor, $channel, $reason): void {
            $from = $quote->status;
            $quote->update([
                'status' => $status,
                'sent_at' => $status === 'sent' && ! $quote->sent_at ? now() : $quote->sent_at,
                'accepted_at' => $status === 'accepted' ? now() : null,
            ]);
            $quote->statusHistory()->create([
                'changed_by' => $actor?->id,
                'from_status' => $from,
                'to_status' => $status,
                'channel' => $channel,
                'reason' => $reason,
                'changed_at' => now(),
            ]);

            if ($status === 'accepted' && $quote->installation) {
                $quote->installation->update(['status' => 'approved', 'estimated_budget' => $quote->amount]);
                $this->materials->syncFromQuote($quote, $quote->installation);
                $this->operations->sync($quote, $quote->installation, $actor?->id);
            }

            if (! $quote->opportunity) {
                return;
            }

            if ($status === 'accepted') {
                $quote->opportunity->update(['stage' => 'won', 'probability' => 100, 'lost_reason' => null, 'lost_reason_detail' => null]);
            } elseif ($status === 'rejected') {
                $quote->opportunity->update([
                    'stage' => 'lost',
                    'probability' => 0,
                    'lost_reason' => 'other',
                    'lost_reason_detail' => __('Quote :number rejected.', ['number' => $quote->number]),
                ]);
            }
        });
    }

    public function recordInitial(Quote $quote, ?User $actor = null): void
    {
        $quote->refresh();
        $quote->statusHistory()->firstOrCreate(
            ['from_status' => null, 'to_status' => $quote->status],
            ['changed_by' => $actor?->id, 'channel' => 'internal', 'changed_at' => $quote->created_at ?? now()],
        );
    }
}
