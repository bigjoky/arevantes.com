<?php

namespace App\Http\Controllers;

use App\Services\Commercial\CommercialDashboardSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CommercialDashboardController extends Controller
{
    public function __invoke(Request $request, CommercialDashboardSummary $summary): Response
    {
        abort_unless(collect(['manage-customers', 'manage-quotes', 'manage-installations'])->contains(fn ($ability) => Gate::allows($ability)), 403);

        return Inertia::render('commercial/index', ['overview' => fn () => $summary->make($request)]);
    }
}
