<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HistoricalDocumentDate
{
    public static function fromRequest(Request $request): string
    {
        $data = $request->validate([
            'issue_date' => [Rule::prohibitedIf(! $request->user()->hasRole('admin')), 'nullable', 'date_format:Y-m-d'],
        ]);

        return $data['issue_date'] ?? now()->toDateString();
    }
}
