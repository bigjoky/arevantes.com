<?php

namespace App\Http\Controllers;

use App\Support\WorkspaceShortcuts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceShortcutController extends Controller
{
    public function update(Request $request, WorkspaceShortcuts $shortcuts): RedirectResponse
    {
        $data = $request->validate(['href' => ['required', 'string', 'max:255'], 'enabled' => ['required', 'boolean']]);
        $shortcuts->for($request->user(), favorite: $data['href'], enabled: $data['enabled']);

        return back();
    }
}
