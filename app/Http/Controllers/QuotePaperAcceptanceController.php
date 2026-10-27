<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Models\QuoteStatusHistory;
use App\Rules\SafeUpload;
use App\Services\Quotes\QuoteStatusWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class QuotePaperAcceptanceController extends Controller
{
    public function store(Request $request, Quote $quote, QuoteStatusWorkflow $workflow)
    {
        $data = $request->validate([
            'accepted_at' => ['required', 'date', 'before_or_equal:now', 'after_or_equal:'.($quote->issue_date?->toDateString() ?? '1900-01-01')],
            'signed_by_name' => ['required', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'proof' => ['required', 'file', new SafeUpload(['pdf', 'jpg', 'jpeg', 'png'], 10240)],
        ]);
        $path = null;
        try {
            DB::transaction(function () use ($quote, $request, $workflow, $data, &$path) {
                $locked = Quote::lockForUpdate()->findOrFail($quote->id);
                // Repeated submissions preserve the original proof and audit record.
                if ($locked->status === 'accepted' && $locked->statusHistory()->where('channel', 'paper')->exists()) {
                    return;
                }
                abort_if($locked->status === 'accepted', 422, 'La aceptación ya está registrada por otro canal. No se sobrescribe.');
                $path = $request->file('proof')->store('quote-paper-acceptances/'.$quote->id, config('filesystems.private'));
                if (! $path) {
                    throw new \RuntimeException('No se pudo archivar el justificante.');
                }
                $workflow->apply($locked, 'accepted', $request->user(), 'paper', $data['reason'] ?? null, Carbon::parse($data['accepted_at']));
                $locked->update(['accepted_at' => $data['accepted_at']]);
                $locked->statusHistory()->where('to_status', 'accepted')->where('channel', 'paper')->latest('id')->firstOrFail()->update([
                    'changed_at' => $data['accepted_at'], 'signed_by_name' => $data['signed_by_name'],
                    'proof_path' => $path, 'proof_name' => $request->file('proof')->getClientOriginalName(), 'document_version' => $locked->version,
                ]);
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk(config('filesystems.private'))->delete($path);
            }
            throw $e;
        }

        return back()->with('toast', ['type' => 'success', 'message' => 'Aceptación en papel registrada. El proyecto y la factura no se emiten automáticamente.']);
    }

    public function download(Quote $quote, QuoteStatusHistory $history)
    {
        abort_unless($history->quote_id === $quote->id && $history->channel === 'paper' && $history->proof_path, 404);

        return Storage::disk(config('filesystems.private'))->download($history->proof_path, $history->proof_name);
    }
}
