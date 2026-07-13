<?php

namespace App\Http\Controllers;

use App\Models\PurchaseMailbox;
use App\Services\Mailbox\PurchaseMailboxSynchronizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class PurchaseMailboxController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $mailbox = PurchaseMailbox::first();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'encryption' => ['required', Rule::in(['ssl', 'tls', 'none'])],
            'username' => ['required', 'string', 'max:255'],
            'password' => [Rule::requiredIf(! $mailbox), 'nullable', 'string', 'max:1000'],
            'folder' => ['required', 'string', 'max:255'],
            'lookback_days' => ['required', 'integer', 'between:1,365'],
        ]);
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        $data += [
            'validate_certificate' => $request->boolean('validate_certificate'),
            'auto_extract' => $request->boolean('auto_extract'),
            'is_active' => $request->boolean('is_active'),
        ];
        PurchaseMailbox::updateOrCreate(['id' => $mailbox?->id], $data);

        return back()->with('toast', ['type' => 'success', 'message' => __('Purchase mailbox settings saved.')]);
    }

    public function sync(PurchaseMailboxSynchronizer $synchronizer): RedirectResponse
    {
        $mailbox = PurchaseMailbox::where('is_active', true)->first();
        abort_unless($mailbox, 422, __('Configure and enable the purchase mailbox first.'));
        try {
            $stats = $synchronizer->sync($mailbox);

            return back()->with('toast', ['type' => 'success', 'message' => trans_choice(':count supplier document imported.|:count supplier documents imported.', $stats['documents'], ['count' => $stats['documents']])]);
        } catch (Throwable) {
            return back()->with('toast', ['type' => 'error', 'message' => __('Mailbox synchronization failed. Review the mailbox status and settings.')]);
        }
    }
}
