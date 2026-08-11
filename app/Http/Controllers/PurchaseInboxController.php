<?php

namespace App\Http\Controllers;

use App\Models\PurchaseDocument;
use App\Models\PurchaseDocumentAttachment;
use App\Models\PurchaseInboxDocument;
use App\Models\PurchaseMailbox;
use App\Models\Supplier;
use App\Rules\SafeUpload;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\Documents\DocumentNumber;
use App\Services\Documents\PurchaseInboxAutomation;
use App\Services\Documents\PurchaseInvoiceMatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseInboxController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();
        $documents = PurchaseInboxDocument::with(['supplier:id,legal_name,trade_name', 'purchaseOrder:id,number', 'purchaseDocument:id,number'])
            ->when($status && $status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest()
            ->get();

        return Inertia::render('purchases/inbox/index', [
            'documents' => $documents,
            'counts' => PurchaseInboxDocument::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'activeStatus' => $status ?: 'all',
            'mailbox' => $this->mailboxData(),
            'canConfigureMailbox' => Gate::allows('manage-staff'),
        ]);
    }

    public function show(PurchaseInboxDocument $inboxDocument): Response
    {
        return Inertia::render('purchases/inbox/show', [
            'inboxDocument' => $inboxDocument->load(['supplier:id,legal_name,trade_name', 'purchaseOrder:id,number,supplier_id', 'purchaseDocument:id,number', 'uploader:id,name', 'processor:id,name', 'mailboxMessage:id,purchase_mailbox_id,subject,from_name,from_email,received_at']),
            'suppliers' => Supplier::where('is_active', true)->orderBy('legal_name')->get(['id', 'legal_name', 'trade_name', 'tax_id']),
            'purchaseOrders' => PurchaseDocument::with('supplier:id,legal_name,trade_name')->where('type', 'purchase_order')->whereIn('status', ['ordered', 'partially_received', 'received'])->latest('issue_date')->get(['id', 'number', 'supplier_id', 'issue_date', 'amount', 'invoicing_status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $files = $request->validate(['documents' => ['required', 'array', 'min:1', 'max:10'], 'documents.*' => ['required', 'file', new SafeUpload(['pdf', 'jpg', 'jpeg', 'png', 'webp'])]])['documents'];
        $prepared = collect($files)->map(function ($file) {
            $hash = hash_file('sha256', $file->getRealPath());
            if (PurchaseInboxDocument::where('file_hash', $hash)->exists() || PurchaseDocumentAttachment::where('file_hash', $hash)->exists()) {
                throw ValidationException::withMessages(['documents' => __('The file :name already exists in the purchasing system.', ['name' => $file->getClientOriginalName()])]);
            }

            return compact('file', 'hash');
        });
        if ($prepared->pluck('hash')->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['documents' => __('The upload contains duplicate files.')]);
        }

        $created = [];
        $stored = [];
        try {
            foreach ($prepared as ['file' => $file, 'hash' => $hash]) {
                $path = $file->store('purchase-inbox/'.now()->format('Y/m'), config('filesystems.private'));
                $stored[] = $path;
                $created[] = PurchaseInboxDocument::create([
                    'uploaded_by' => $request->user()->id,
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'size' => $file->getSize(),
                    'file_hash' => $hash,
                ]);
            }
        } catch (\Throwable $exception) {
            foreach ($stored as $path) {
                Storage::disk(config('filesystems.private'))->delete($path);
            }
            foreach ($created as $document) {
                $document->delete();
            }
            throw $exception;
        }

        $first = $created[0];

        return to_route('purchase-inbox.show', $first)->with('toast', ['type' => 'success', 'message' => trans_choice(':count document added to the inbox.|:count documents added to the inbox.', count($created), ['count' => count($created)])]);
    }

    public function download(PurchaseInboxDocument $inboxDocument): StreamedResponse
    {
        abort_unless(Storage::disk(config('filesystems.private'))->exists($inboxDocument->path), 404);

        return Storage::disk(config('filesystems.private'))->download($inboxDocument->path, $inboxDocument->original_name, ['Content-Type' => $inboxDocument->mime_type]);
    }

    public function extract(PurchaseInboxDocument $inboxDocument, PurchaseInboxAutomation $automation): RedirectResponse
    {
        abort_if(in_array($inboxDocument->status, ['converted', 'rejected'], true), 422);
        $success = $automation->extractAndClassify($inboxDocument);

        return back()->with('toast', ['type' => $success ? 'success' : 'error', 'message' => $success ? __('Document data extracted. Review it before conversion.') : __('The document could not be extracted automatically.')]);
    }

    public function update(Request $request, PurchaseInboxDocument $inboxDocument): RedirectResponse
    {
        abort_if(in_array($inboxDocument->status, ['converted', 'rejected'], true), 422);
        $this->normalizeOptionalRelations($request);
        $data = $request->validate([
            'classification' => ['required', Rule::in(['unknown', 'supplier_invoice', 'supplier_credit_note'])],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'purchase_order_id' => ['nullable', 'exists:purchase_documents,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $order = empty($data['purchase_order_id']) ? null : PurchaseDocument::findOrFail($data['purchase_order_id']);
        $this->validateOrder($order, $data['supplier_id'] ?? null, $data['classification']);
        $ready = $data['classification'] !== 'unknown' && ! empty($data['supplier_id']);
        $inboxDocument->update([...$data, 'status' => $ready ? 'ready' : 'pending_review']);

        return back()->with('toast', ['type' => 'success', 'message' => __('Inbox document assignment saved.')]);
    }

    public function convert(Request $request, PurchaseInboxDocument $inboxDocument, PurchaseInvoiceMatcher $matcher, AccountingSynchronizer $accounting): RedirectResponse
    {
        abort_if(in_array($inboxDocument->status, ['converted', 'rejected'], true), 422);
        $this->normalizeOptionalRelations($request);
        $data = $request->validate([
            'classification' => ['required', Rule::in(['supplier_invoice', 'supplier_credit_note'])],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'purchase_order_id' => ['nullable', 'exists:purchase_documents,id'],
            'supplier_number' => ['required', 'string', 'max:255'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['required', 'numeric', 'min:0'],
            'total' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $order = empty($data['purchase_order_id']) ? null : PurchaseDocument::findOrFail($data['purchase_order_id']);
        $this->validateOrder($order, (int) $data['supplier_id'], $data['classification']);
        if (abs(round((float) $data['subtotal'] + (float) $data['tax_amount'], 2) - (float) $data['total']) > 0.01) {
            throw ValidationException::withMessages(['total' => __('Total must equal subtotal plus tax.')]);
        }
        if ((float) ($data['shipping_cost'] ?? 0) > (float) $data['subtotal'] + 0.005) {
            throw ValidationException::withMessages(['shipping_cost' => __('Transport cannot exceed the document subtotal.')]);
        }
        if (PurchaseDocument::where('supplier_id', $data['supplier_id'])->where('type', $data['classification'])->where('supplier_number', $data['supplier_number'])->exists()) {
            throw ValidationException::withMessages(['supplier_number' => __('This supplier document number is already registered.')]);
        }

        $document = DB::transaction(function () use ($request, $inboxDocument, $matcher, $accounting, $data, $order) {
            $shippingCost = round((float) ($data['shipping_cost'] ?? 0), 2);
            $match = $order ? $matcher->match($order, [], $shippingCost) : null;
            if ($match && abs((float) $match['totals']['amount'] - (float) $data['total']) > 0.01) {
                throw ValidationException::withMessages(['total' => __('The document total does not match the pending lines of the selected purchase order.')]);
            }
            $credit = $data['classification'] === 'supplier_credit_note';
            $issued = app(DocumentNumber::class)->next($data['classification'], $data['issue_date']);
            $document = PurchaseDocument::create([
                'type' => $data['classification'],
                'source_document_id' => $order?->id,
                'supplier_id' => $data['supplier_id'],
                'document_series_id' => $issued['series']->id,
                'created_by' => $request->user()->id,
                'number' => $issued['number'],
                'supplier_number' => $data['supplier_number'],
                'status' => 'registered',
                'matching_status' => $order ? 'matched' : null,
                'match_variance_amount' => 0,
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'] ?? null,
                'subtotal' => ($credit ? -1 : 1) * (float) $data['subtotal'],
                'shipping_cost' => ($credit ? -1 : 1) * $shippingCost,
                'tax_amount' => ($credit ? -1 : 1) * (float) $data['tax_amount'],
                'amount' => ($credit ? -1 : 1) * (float) $data['total'],
                'notes' => $data['notes'] ?? null,
                'locked_at' => now(),
            ]);
            if ($match) {
                $document->lines()->createMany($match['lines']);
            }
            $document->attachment()->create([
                'uploaded_by' => $inboxDocument->uploaded_by,
                'verified_by' => $request->user()->id,
                'original_name' => $inboxDocument->original_name,
                'path' => $inboxDocument->path,
                'mime_type' => $inboxDocument->mime_type,
                'size' => $inboxDocument->size,
                'file_hash' => $inboxDocument->file_hash,
                'extraction_status' => 'verified',
                'extracted_data' => $inboxDocument->extracted_data,
                'extracted_at' => $inboxDocument->extracted_at,
                'verified_at' => now(),
            ]);
            $accounting->syncPurchaseDocument($document, $request->user()->id);
            $inboxDocument->update(['supplier_id' => $data['supplier_id'], 'purchase_order_id' => $order?->id, 'purchase_document_id' => $document->id, 'classification' => $data['classification'], 'status' => 'converted', 'processed_by' => $request->user()->id, 'processed_at' => now(), 'notes' => $data['notes'] ?? null]);
            if ($order) {
                $matcher->refresh($order);
            }

            return $document;
        });

        return to_route('purchases.show', $document)->with('toast', ['type' => 'success', 'message' => __('Inbox document converted and posted.')]);
    }

    public function reject(Request $request, PurchaseInboxDocument $inboxDocument): RedirectResponse
    {
        abort_if($inboxDocument->status === 'converted', 422);
        $data = $request->validate(['notes' => ['required', 'string', 'max:2000']]);
        $inboxDocument->update(['status' => 'rejected', 'notes' => $data['notes'], 'processed_by' => $request->user()->id, 'processed_at' => now()]);

        return to_route('purchase-inbox.index')->with('toast', ['type' => 'success', 'message' => __('Inbox document rejected and retained for audit.')]);
    }

    private function validateOrder(?PurchaseDocument $order, ?int $supplierId, string $classification): void
    {
        if (! $order) {
            return;
        }
        if ($classification !== 'supplier_invoice' || $order->type !== 'purchase_order') {
            throw ValidationException::withMessages(['purchase_order_id' => __('Only supplier invoices can be linked to purchase orders.')]);
        }
        if ($supplierId && $order->supplier_id !== $supplierId) {
            throw ValidationException::withMessages(['purchase_order_id' => __('The purchase order belongs to a different supplier.')]);
        }
    }

    private function normalizeOptionalRelations(Request $request): void
    {
        $request->merge([
            'supplier_id' => $request->input('supplier_id') === 'none' ? null : $request->input('supplier_id'),
            'purchase_order_id' => $request->input('purchase_order_id') === 'none' ? null : $request->input('purchase_order_id'),
        ]);
    }

    private function mailboxData(): ?array
    {
        $mailbox = PurchaseMailbox::withCount([
            'messages',
            'messages as imported_messages_count' => fn ($query) => $query->where('status', 'imported'),
            'messages as failed_messages_count' => fn ($query) => $query->where('status', 'failed'),
        ])->first();
        if (! $mailbox) {
            return null;
        }
        $data = $mailbox->only(['id', 'name', 'email', 'is_active', 'auto_extract', 'last_sync_attempt_at', 'last_synced_at', 'last_error', 'messages_count', 'imported_messages_count', 'failed_messages_count']);
        if (Gate::allows('manage-staff')) {
            $data += $mailbox->only(['host', 'port', 'encryption', 'validate_certificate', 'username', 'folder', 'lookback_days']);
        }

        return $data;
    }
}
