<?php

namespace App\Http\Controllers;

use App\Services\Accounting\AccountingWorkspace;
use App\Support\WorkspaceNavigation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceModuleController extends Controller
{
    public function __invoke(Request $request, WorkspaceNavigation $navigation, string $module, string $section): Response
    {
        if ($module === 'accounting' && $section === 'books') {
            return app(AccountingWorkspaceController::class)->index($request, app(AccountingWorkspace::class));
        }
        $area = collect($navigation->for($request->user()))->firstWhere('id', $module);
        abort_unless($area, 403);
        $item = collect($area['children'] ?? [])->firstWhere('id', $module.'.'.$section);
        abort_unless($item, 403);

        return Inertia::render('workspace/module', ['module' => $area['title'], 'section' => $item, 'year' => (int) ($request->validate(['year' => 'nullable|integer|between:2000,2100'])['year'] ?? now()->year)]);
    }
}
