<?php

namespace App\Services\Crm;

use App\Models\Opportunity;
use Illuminate\Support\Collection;

class OpportunityDossier
{
    /** @return Collection<int, array<string, mixed>> */
    public function timeline(Opportunity $opportunity): Collection
    {
        $items = collect([[
            'id' => 'created-'.$opportunity->id,
            'type' => 'created',
            'title' => __('Opportunity created'),
            'description' => null,
            'note' => null,
            'occurred_at' => $opportunity->created_at?->toIso8601String(),
            'actor' => $opportunity->creator?->only(['id', 'name']),
            'href' => null,
            'changes' => null,
        ]]);

        $opportunity->events->each(fn ($event) => $items->push([
            'id' => 'event-'.$event->id,
            'type' => $event->event_type,
            'title' => $event->summary,
            'description' => null,
            'note' => $event->note,
            'occurred_at' => $event->occurred_at->toIso8601String(),
            'actor' => $event->actor?->only(['id', 'name']),
            'href' => null,
            'changes' => $event->changes,
        ]));

        $opportunity->stageHistory->each(fn ($history) => $items->push([
            'id' => 'stage-'.$history->id,
            'type' => 'stage_changed',
            'title' => $history->from_stage ? __('Stage changed') : __('Initial stage recorded'),
            'description' => $history->from_stage
                ? $history->from_stage.' → '.$history->to_stage
                : $history->to_stage,
            'note' => $history->reason,
            'occurred_at' => $history->changed_at->toIso8601String(),
            'actor' => $history->changedBy?->only(['id', 'name']),
            'href' => null,
            'changes' => null,
        ]));

        $opportunity->activities->each(fn ($activity) => $items->push([
            'id' => 'activity-'.$activity->id,
            'type' => 'activity',
            'title' => $activity->title,
            'description' => collect([$activity->type, $activity->status, $activity->due_at?->format('d/m/Y H:i')])->filter()->join(' · '),
            'note' => $activity->notes,
            'occurred_at' => $activity->created_at->toIso8601String(),
            'actor' => $activity->creator?->only(['id', 'name']),
            'href' => null,
            'changes' => null,
        ]));

        if ($opportunity->quote) {
            $opportunity->quote->versions->each(fn ($version) => $items->push([
                'id' => 'quote-version-'.$version->id,
                'type' => 'quote_version',
                'title' => __('Quote :number version :version', ['number' => $opportunity->quote->number, 'version' => $version->version]),
                'description' => number_format((float) $version->amount, 2, ',', '.').' €',
                'note' => $version->change_note,
                'occurred_at' => $version->created_at->toIso8601String(),
                'actor' => $version->createdBy?->only(['id', 'name']),
                'href' => route('quotes.show', $opportunity->quote, false),
                'changes' => null,
            ]));
            $opportunity->quote->statusHistory->each(fn ($history) => $items->push([
                'id' => 'quote-status-'.$history->id,
                'type' => 'quote_status',
                'title' => __('Quote status changed'),
                'description' => ($history->from_status ? $history->from_status.' → ' : '').$history->to_status,
                'note' => $history->reason,
                'occurred_at' => $history->changed_at->toIso8601String(),
                'actor' => $history->changedBy?->only(['id', 'name']),
                'href' => route('quotes.show', $opportunity->quote, false),
                'changes' => null,
            ]));
        }

        return $items->filter(fn (array $item): bool => filled($item['occurred_at']))->sortByDesc('occurred_at')->values();
    }
}
