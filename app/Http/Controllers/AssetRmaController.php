<?php

namespace App\Http\Controllers;

use App\Models\AssetRma;
use App\Models\Installation;
use App\Models\InstallationAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AssetRmaController extends Controller
{
    private const TRANSITIONS = [
        'requested' => ['approved', 'rejected', 'cancelled'],
        'approved' => ['shipped', 'cancelled'],
        'shipped' => ['received', 'cancelled'],
        'received' => ['resolved'],
    ];

    public function store(Request $request, Installation $installation, InstallationAsset $asset): RedirectResponse
    {
        $this->ensureInstallation($installation, $asset);
        $data = $request->validate([
            'work_order_id' => ['nullable', Rule::exists('work_orders', 'id')->where('installation_id', $installation->id)],
            'service_ticket_id' => ['nullable', Rule::exists('service_tickets', 'id')->where('installation_id', $installation->id)],
            'provider' => ['nullable', 'string', 'max:255'],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'reason' => ['required', 'string'],
        ]);

        $rma = DB::transaction(function () use ($asset, $data, $request): AssetRma {
            $lockedAsset = InstallationAsset::lockForUpdate()->findOrFail($asset->id);
            abort_if(in_array($lockedAsset->status, ['removed', 'replaced'], true), 422, __('Removed or replaced assets cannot start an RMA.'));
            abort_if($lockedAsset->rmas()->whereIn('status', AssetRma::ACTIVE_STATUSES)->exists(), 422, __('This asset already has an active RMA.'));

            $rma = $lockedAsset->rmas()->create([
                ...$data,
                'opened_by' => $request->user()->id,
                'status' => 'requested',
                'requested_at' => now(),
            ]);
            $rma->update(['number' => 'RMA-'.now()->format('Y').'-'.str_pad((string) $rma->id, 5, '0', STR_PAD_LEFT)]);
            $lockedAsset->update(['status' => 'rma']);
            $lockedAsset->events()->create([
                'work_order_id' => $data['work_order_id'] ?? null,
                'service_ticket_id' => $data['service_ticket_id'] ?? null,
                'recorded_by' => $request->user()->id,
                'type' => 'rma',
                'summary' => __('RMA :number requested', ['number' => $rma->number]),
                'details' => $data['reason'],
                'occurred_at' => now(),
            ]);

            return $rma;
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('RMA :number opened.', ['number' => $rma->number])]);
    }

    public function update(Request $request, Installation $installation, InstallationAsset $asset, AssetRma $rma): RedirectResponse
    {
        $this->ensureInstallation($installation, $asset);
        abort_unless($rma->installation_asset_id === $asset->id, 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'shipped', 'received', 'resolved', 'rejected', 'cancelled'])],
            'provider' => ['nullable', 'string', 'max:255'],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'resolution' => [Rule::requiredIf(in_array($request->string('status')->toString(), ['resolved', 'rejected'], true)), 'nullable', 'string'],
        ]);

        DB::transaction(function () use ($asset, $rma, $data, $request): void {
            $lockedRma = AssetRma::lockForUpdate()->findOrFail($rma->id);
            abort_unless(in_array($data['status'], self::TRANSITIONS[$lockedRma->status] ?? [], true), 422, __('This RMA status transition is not allowed.'));

            $updates = $data;
            if ($data['status'] === 'shipped') {
                $updates['shipped_at'] = now();
            }
            if (in_array($data['status'], ['resolved', 'rejected', 'cancelled'], true)) {
                $updates['resolved_at'] = now();
                $updates['closed_by'] = $request->user()->id;
            }
            $lockedRma->update($updates);
            $asset->events()->create([
                'work_order_id' => $lockedRma->work_order_id,
                'service_ticket_id' => $lockedRma->service_ticket_id,
                'recorded_by' => $request->user()->id,
                'type' => 'rma',
                'summary' => __('RMA :number changed to :status', ['number' => $lockedRma->number, 'status' => $data['status']]),
                'details' => $data['resolution'] ?? null,
                'occurred_at' => now(),
            ]);
            if (in_array($data['status'], ['resolved', 'rejected', 'cancelled'], true) && $asset->status === 'rma') {
                $asset->update(['status' => 'active']);
            }
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('RMA updated.')]);
    }

    private function ensureInstallation(Installation $installation, InstallationAsset $asset): void
    {
        abort_unless($asset->installation_id === $installation->id, 404);
    }
}
