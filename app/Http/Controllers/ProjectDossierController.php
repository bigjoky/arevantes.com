<?php

namespace App\Http\Controllers;

use App\Models\Installation;
use App\Services\Projects\ProjectDossierClosure;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectDossierController extends Controller
{
    public function update(Request $request, Installation $installation, ProjectDossierClosure $closure)
    {
        $data = $request->validate(['action' => ['required', Rule::in(['close', 'reopen', 'require_technical', 'waive_technical', 'confirm_delivery', 'set_required_tasks'])], 'reason' => ['required', 'string', 'min:5', 'max:2000'], 'expected_updated_at' => ['required', 'date'], 'expected_revision' => ['required', 'integer', 'min:0'], 'economic_exception' => ['sometimes', 'boolean'], 'required_activity_ids' => ['sometimes', 'array'], 'required_activity_ids.*' => ['integer', 'distinct']]);
        $closure->record($installation, $request->user(), $data);

        return back()->with('toast', ['type' => 'success', 'message' => 'Decisión del expediente registrada.']);
    }
}
