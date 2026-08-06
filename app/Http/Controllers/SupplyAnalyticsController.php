<?php

namespace App\Http\Controllers;

use App\Services\Supply\SupplyAnalytics;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SupplyAnalyticsController extends Controller
{
    public function __invoke(Request $request, SupplyAnalytics $analytics): Response
    {
        $data = $request->validate(['months' => ['nullable', 'integer', Rule::in([3, 6, 12])]]);
        $months = (int) ($data['months'] ?? 12);

        return Inertia::render('purchases/analytics/index', [
            ...$analytics->dashboard($months),
            'months' => $months,
        ]);
    }
}
