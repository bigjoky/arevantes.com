<?php

namespace App\Services\Crm;

use App\Models\User;

class LeadAssignment
{
    public function nextOwner(): ?User
    {
        return User::query()
            ->where('role', 'commercial')
            ->withCount([
                'ownedOpportunities as open_opportunities_count' => fn ($query) => $query
                    ->whereNotIn('stage', ['won', 'lost']),
            ])
            ->orderBy('open_opportunities_count')
            ->orderBy('id')
            ->lockForUpdate()
            ->first()
            ?? User::query()->where('role', 'admin')->orderBy('id')->lockForUpdate()->first();
    }
}
