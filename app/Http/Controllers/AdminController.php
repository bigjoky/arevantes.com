<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BackupRestoration;
use App\Models\Permission;
use App\Models\PermissionProfile;
use App\Models\SecurityEvent;
use App\Models\SystemHealthCheck;
use App\Models\User;
use App\Services\Security\SecurityEventRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminController extends Controller
{
    public function index(): RedirectResponse
    {
        return $this->users();
    }

    public function users(): RedirectResponse
    {
        return to_route('admin.access.index');
    }

    public function access(): Response
    {
        return Inertia::render('admin/index', [
            'section' => 'access',
            'users' => User::with(['permissionProfile:id,name', 'permissionOverrides:id,key'])->orderBy('name')->get(),
            'profiles' => PermissionProfile::with('permissions:id,key')->orderBy('name')->get(),
            'permissions' => Permission::orderBy('category')->orderBy('name')->get(),
        ]);
    }

    public function backups(): Response
    {
        $backups = collect(File::glob(storage_path('app/private/backups/*.enc')))
            ->map(fn (string $path) => ['name' => basename($path), 'size' => File::size($path), 'created_at' => date(DATE_ATOM, File::lastModified($path))])
            ->sortByDesc('created_at')->values();

        return Inertia::render('admin/index', [
            'section' => 'backups',
            'backups' => $backups,
            'restorations' => BackupRestoration::latest()->limit(20)->get(),
        ]);
    }

    public function audit(Request $request): Response
    {
        $query = $this->auditQuery($request);

        return Inertia::render('admin/index', [
            'section' => 'audit',
            'audits' => $query->paginate(25)->withQueryString(),
            'filters' => $request->only(['event', 'user_id', 'correlation_id', 'from', 'to']),
        ]);
    }

    public function health(): Response
    {
        return Inertia::render('admin/index', [
            'section' => 'health',
            'healthChecks' => SystemHealthCheck::query()->whereIn('id', SystemHealthCheck::selectRaw('max(id)')->groupBy('component'))->orderBy('component')->get(),
            'securityEvents' => SecurityEvent::with('user:id,name')->latest()->limit(30)->get(),
            'accessReviewsDue' => User::whereNotNull('access_review_due_at')->where('access_review_due_at', '<=', now())->count(),
        ]);
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        if ($request->filled('role') && ! $request->filled('permission_profile_id')) {
            $legacySlug = $request->string('role')->toString() === 'admin' ? 'administrator' : $request->string('role')->toString();
            $request->merge(['permission_profile_id' => PermissionProfile::where('slug', $legacySlug)->value('id')]);
        }
        $data = $request->validate([
            'permission_profile_id' => ['required', 'exists:permission_profiles,id'],
            'overrides' => ['sometimes', 'array'],
            'overrides.*' => ['nullable', Rule::in(['allow', 'deny', 'inherit'])],
        ]);
        $administrator = PermissionProfile::where('slug', 'administrator')->firstOrFail();

        if ($user->id === $request->user()->id && (int) $data['permission_profile_id'] !== $administrator->id && User::where('permission_profile_id', $administrator->id)->count() === 1) {
            return back()->withErrors(['role' => __('The last administrator cannot be demoted.')]);
        }

        DB::transaction(function () use ($user, $data, $request) {
            $profile = PermissionProfile::findOrFail($data['permission_profile_id']);
            $user->update([
                'permission_profile_id' => $profile->id,
                'role' => $profile->slug === 'administrator' ? 'admin' : ($profile->slug === 'technician' ? 'technician' : 'commercial'),
                'access_reviewed_at' => now(),
                'access_review_due_at' => now()->addMonths(3),
            ]);
            $sync = [];
            foreach ($data['overrides'] ?? [] as $permissionId => $choice) {
                if ($choice !== 'inherit') {
                    $sync[(int) $permissionId] = ['allowed' => $choice === 'allow', 'granted_by' => $request->user()->id];
                }
            }
            $user->permissionOverrides()->sync($sync);
        });
        app(SecurityEventRecorder::class)->record('permissions_changed', $user, ['changed_by' => $request->user()->id], 'warning', true);

        return back()->with('toast', ['type' => 'success', 'message' => __('User access updated and review renewed.')]);
    }

    public function updateProfile(Request $request, PermissionProfile $profile): RedirectResponse
    {
        $data = $request->validate(['permissions' => ['array'], 'permissions.*' => ['integer', 'exists:permissions,id']]);
        $permissionIds = $data['permissions'] ?? [];
        if ($profile->slug === 'administrator') {
            $permissionIds = collect($permissionIds)->merge(Permission::whereIn('key', ['manage-admin', 'manage-access', 'export-audit', 'view-system-health'])->pluck('id'))->unique()->all();
        }
        $profile->permissions()->sync($permissionIds);
        app(SecurityEventRecorder::class)->record('permission_profile_changed', $request->user(), ['profile_id' => $profile->id], 'warning', true);

        return back()->with('toast', ['type' => 'success', 'message' => __('Permission profile updated.')]);
    }

    public function reviewAccess(Request $request, User $user): RedirectResponse
    {
        $user->update(['access_reviewed_at' => now(), 'access_review_due_at' => now()->addMonths(3)]);
        app(SecurityEventRecorder::class)->record('access_reviewed', $user, ['reviewed_by' => $request->user()->id]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Access review recorded.')]);
    }

    public function exportAudit(Request $request): StreamedResponse
    {
        $file = 'audit-'.now()->format('Ymd-His').'.csv';
        app(SecurityEventRecorder::class)->record('audit_exported', $request->user(), ['filters' => $request->only(['event', 'user_id', 'correlation_id', 'from', 'to'])], 'warning');

        return response()->streamDownload(function () use ($request) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['timestamp', 'user', 'event', 'record_type', 'record_id', 'correlation_id', 'route', 'ip', 'old_values', 'new_values']);
            $this->auditQuery($request)->chunkById(500, function ($rows) use ($out) {
                foreach ($rows as $row) {
                    fputcsv($out, [$row->created_at?->toIso8601String(), $row->user?->name ?? 'System', $row->event, $row->auditable_type, $row->auditable_id, $row->correlation_id, $row->route, $row->ip_address, json_encode($row->old_values), json_encode($row->new_values)]);
                }
            });
            fclose($out);
        }, $file, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function auditQuery(Request $request)
    {
        return AuditLog::with('user:id,name')
            ->when($request->filled('event'), fn ($q) => $q->where('event', $request->string('event')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('correlation_id'), fn ($q) => $q->where('correlation_id', $request->string('correlation_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->latest();
    }

    public function backup(): RedirectResponse
    {
        Artisan::call('database:backup');
        app(SecurityEventRecorder::class)->record('backup_created', request()->user(), [], 'warning');

        return back()->with('toast', ['type' => 'success', 'message' => __('Encrypted backup created.')]);
    }

    public function downloadBackup(string $backup): BinaryFileResponse
    {
        $path = storage_path('app/private/backups/'.basename($backup));
        abort_unless(is_file($path), 404);

        return response()->download($path);
    }
}
