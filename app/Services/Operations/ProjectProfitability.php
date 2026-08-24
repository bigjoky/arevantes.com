<?php

namespace App\Services\Operations;

use App\Models\Installation;
use App\Models\Quote;
use Illuminate\Support\Collection;

class ProjectProfitability
{
    public function __construct(private ProjectMarginPortfolio $portfolio) {}

    /** @return array<string, mixed> */
    public function for(Installation $installation, ?Quote $quote = null): array
    {
        $overrides = $quote && (int) $quote->installation_id === (int) $installation->id ? [$installation->id => $quote] : [];

        return $this->portfolio->analyze(new Collection([$installation]), $overrides)->firstOrFail();
    }
}
