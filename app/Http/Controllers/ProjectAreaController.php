<?php

namespace App\Http\Controllers;

use App\Models\Installation;
use App\Models\Quote;
use App\Services\Projects\ProjectWorkspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectAreaController extends Controller
{
    public function dashboard(Request $request, ProjectWorkspace $workspace): Response
    {
        return Inertia::render('execution/dashboard', $workspace->dashboard($request));
    }

    public function index(Request $request, ProjectWorkspace $workspace): Response
    {
        return Inertia::render('execution/index', $workspace->listing($request));
    }

    public function show(Request $request, Installation $installation, ProjectWorkspace $workspace): Response
    {
        return Inertia::render('execution/show', $workspace->detail($request, $installation));
    }

    public function create(Request $request): Response
    {
        return Inertia::render('execution/create', [
            'quotes' => Quote::with('customer:id,first_name,last_name,company_name')->where('status', 'accepted')->whereNull('installation_id')->latest('id')
                ->paginate(15, ['id', 'person_id', 'number', 'status', 'service_type'])->withQueryString(),
        ]);
    }
}
