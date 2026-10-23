<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CompanyDocument;
use App\Models\CompanyDocumentVersion;
use App\Models\CompanyProfile;
use App\Rules\SafeUpload;
use App\Services\Company\CompanyWorkspace;
use App\Services\Security\SecurityEventRecorder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CompanyDocumentController extends Controller
{
    private function disk()
    {
        $name = (string) config('filesystems.private', 'local');
        abort_if($name === 'public' || config('filesystems.disks.'.$name.'.visibility') === 'public', 503, __('Private document storage is not configured.'));

        return Storage::disk($name);
    }

    private function audit(CompanyDocument $doc, string $event, array $fields): void
    {
        AuditLog::create(['user_id' => auth()->id(), 'event' => $event, 'auditable_type' => $doc->getMorphClass(), 'auditable_id' => $doc->id,
            'new_values' => ['fields' => $fields, 'version' => $doc->version, 'revision' => $doc->revision], 'route' => request()->route()?->getName(), 'method' => request()->method()]);
    }

    private function uploadRules(): array
    {
        return ['required', 'file', new SafeUpload(['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'], 12288), function ($attribute, $file, $fail) {
            if (! $file instanceof UploadedFile) {
                return;
            }
            $types = ['pdf' => ['application/pdf'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'doc' => ['application/msword', 'application/x-ole-storage'], 'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip']];
            if (! in_array($file->getMimeType(), $types[strtolower($file->getClientOriginalExtension())] ?? [], true)) {
                $fail(__('The document contents do not match its extension.'));
            }
        }];
    }

    private function metadata(Request $request): array
    {
        return $request->validate(['title' => 'required|string|max:180', 'description' => 'nullable|string|max:3000', 'category' => ['required', Rule::in(CompanyWorkspace::CATEGORIES)],
            'visibility' => ['required', Rule::in(['restricted', 'staff'])], 'expires_at' => 'nullable|date_format:Y-m-d']);
    }

    public function store(Request $request)
    {
        Gate::authorize('manage-admin');
        $data = $this->metadata($request);
        $request->validate(['upload_token' => 'required|uuid', 'file' => $this->uploadRules()]);
        $company = CompanyProfile::firstOrFail();
        $file = $request->file('file');
        $hash = hash_file('sha256', $file->getRealPath());
        $fingerprint = hash('sha256', json_encode([$data, $hash], JSON_THROW_ON_ERROR));
        $disk = $this->disk();
        $path = null;
        try {
            $doc = DB::transaction(function () use ($request, $company, $data, $hash, $fingerprint, $file, &$path) {
                CompanyProfile::whereKey($company->id)->lockForUpdate()->firstOrFail();
                $existing = CompanyDocument::where('company_profile_id', $company->id)->where('upload_token', $request->input('upload_token'))->first();
                if ($existing) {
                    abort_unless($existing->created_by === $request->user()->id && hash_equals($existing->request_fingerprint, $fingerprint), 409, __('The upload request has changed. Start a new upload.'));

                    return $existing;
                }
                $doc = CompanyDocument::create([...$data, 'company_profile_id' => $company->id, 'created_by' => $request->user()->id, 'version' => 1, 'revision' => 1, 'upload_token' => $request->input('upload_token'), 'request_fingerprint' => $fingerprint]);
                $path = $file->store('company-documents/'.$company->id, config('filesystems.private'));
                abort_unless($path, 503);
                $doc->versions()->create(['uploaded_by' => $request->user()->id, 'version' => 1, 'path' => $path, 'original_name' => $this->filename($file->getClientOriginalName()), 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'sha256' => $hash]);
                $this->audit($doc, 'created', array_keys($data));

                return $doc;
            });
        } catch (\Throwable $e) {
            if ($path) {
                $disk->delete($path);
            }
            throw $e;
        }

        return to_route('company.documents.show', $doc);
    }

    public function version(Request $request, CompanyDocument $document, CompanyWorkspace $workspace)
    {
        Gate::authorize('manage-admin');
        $workspace->documents()->findOrFail($document->id);
        $request->validate(['expected_revision' => 'required|integer|min:1', 'file' => $this->uploadRules()]);
        $disk = $this->disk();
        $path = null;
        try {
            DB::transaction(function () use ($request, $document, &$path) {
                $doc = CompanyDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
                abort_unless($doc->revision === $request->integer('expected_revision') && $doc->status === 'active', 409, __('The document changed. Reload it before saving.'));
                $file = $request->file('file');
                $path = $file->store('company-documents/'.$doc->company_profile_id, config('filesystems.private'));
                abort_unless($path, 503);
                $number = $doc->version + 1;
                $doc->versions()->create(['uploaded_by' => $request->user()->id, 'version' => $number, 'path' => $path, 'original_name' => $this->filename($file->getClientOriginalName()), 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath())]);
                $doc->update(['version' => $number, 'revision' => $doc->revision + 1]);
                $this->audit($doc, 'version_created', ['file']);
            });
        } catch (\Throwable $e) {
            if ($path) {
                $disk->delete($path);
            }
            throw $e;
        }

        return back();
    }

    public function update(Request $request, CompanyDocument $document, CompanyWorkspace $workspace)
    {
        Gate::authorize('manage-admin');
        $workspace->documents()->findOrFail($document->id);
        $data = $this->metadata($request);
        $request->validate(['expected_revision' => 'required|integer|min:1', 'status' => ['required', Rule::in(['active', 'archived'])]]);
        DB::transaction(function () use ($request, $document, $data) {
            $doc = CompanyDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            abort_unless($doc->revision === $request->integer('expected_revision'), 409, __('The document changed. Reload it before saving.'));
            $doc->fill([...$data, 'status' => $request->input('status'), 'revision' => $doc->revision + 1])->save();
            $this->audit($doc, 'updated', [...array_keys($data), 'status']);
        });

        return back();
    }

    public function show(Request $request, CompanyDocument $document, CompanyWorkspace $workspace)
    {
        $return = $request->validate(['return_search' => 'nullable|string|max:150', 'return_category' => ['nullable', Rule::in(CompanyWorkspace::CATEGORIES)], 'return_state' => ['nullable', Rule::in(['active', 'archived'])], 'return_sort' => ['nullable', Rule::in(['name', 'recent', 'oldest', 'expiry'])], 'return_page' => 'nullable|integer|min:1']);
        $doc = $workspace->documents()->with('latestVersion')->findOrFail($document->id);

        return Inertia::render('company/document', ['document' => $workspace->documentRow($doc), 'returnFilters' => $return, 'canManage' => Gate::allows('manage-admin'),
            'versions' => $doc->versions()->with('uploadedBy:id,name')->latest('version')->paginate(10)->withQueryString()->through(fn ($v) => ['id' => $v->id, 'version' => $v->version, 'name' => $v->original_name, 'mime' => $v->mime_type, 'size' => $v->size, 'author' => $v->uploadedBy?->name, 'created' => $v->created_at->toIso8601String(), 'href' => '/company/documents/'.$doc->id.'/versions/'.$v->id.'/download']),
            'history' => AuditLog::where('auditable_type', $doc->getMorphClass())->where('auditable_id', $doc->id)->latest('id')->select(['id', 'event', 'user_id', 'created_at'])->paginate(10, ['*'], 'history_page')->withQueryString()]);
    }

    public function download(CompanyDocument $document, CompanyDocumentVersion $version, CompanyWorkspace $workspace, SecurityEventRecorder $events)
    {
        $doc = $workspace->documents()->findOrFail($document->id);
        abort_unless($version->company_document_id === $doc->id, 404);
        $disk = $this->disk();
        abort_unless($disk->exists($version->path), 404);
        $stream = $disk->readStream($version->path);
        abort_unless(is_resource($stream), 503);
        try {
            $hash = hash_init('sha256');
            hash_update_stream($hash, $stream);
            $actual = hash_final($hash);
        } finally {
            fclose($stream);
        }
        abort_unless(hash_equals($version->sha256, $actual), 409, __('The document integrity check failed.'));
        $events->record('company_document_downloaded', request()->user(), ['document_id' => $doc->id, 'version_id' => $version->id]);

        return $disk->download($version->path, $version->original_name, ['Content-Type' => $version->mime_type, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function filename(string $name): string
    {
        return mb_substr(preg_replace('/[\x00-\x1f\x7f]/u', '', basename(str_replace('\\', '/', $name))) ?: 'document', 0, 180);
    }
}
