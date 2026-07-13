<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminController extends Controller
{
    public function index(): RedirectResponse
    {
        return $this->users();
    }

    public function users(): RedirectResponse
    {
        return to_route('staff.index');
    }

    public function backups(): Response
    {
        $backups = collect(File::glob(storage_path('app/private/backups/*.enc')))
            ->map(fn (string $path) => ['name' => basename($path), 'size' => File::size($path), 'created_at' => date(DATE_ATOM, File::lastModified($path))])
            ->sortByDesc('created_at')->values();

        return Inertia::render('admin/index', [
            'section' => 'backups',
            'backups' => $backups,
        ]);
    }

    public function audit(): Response
    {
        return Inertia::render('admin/index', [
            'section' => 'audit',
            'audits' => AuditLog::with('user:id,name')->latest()->limit(100)->get(),
        ]);
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', Rule::in(['admin', 'commercial', 'technician'])]]);

        if ($user->id === $request->user()->id && $data['role'] !== 'admin' && User::where('role', 'admin')->count() === 1) {
            return back()->withErrors(['role' => __('The last administrator cannot be demoted.')]);
        }

        $user->update($data);

        return back()->with('toast', ['type' => 'success', 'message' => __('User role updated.')]);
    }

    public function backup(): RedirectResponse
    {
        Artisan::call('database:backup');

        return back()->with('toast', ['type' => 'success', 'message' => __('Encrypted backup created.')]);
    }

    public function downloadBackup(string $backup): BinaryFileResponse
    {
        $path = storage_path('app/private/backups/'.basename($backup));
        abort_unless(is_file($path), 404);

        return response()->download($path);
    }
}
