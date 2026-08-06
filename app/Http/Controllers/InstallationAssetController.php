<?php

namespace App\Http\Controllers;

use App\Models\Installation;
use App\Models\InstallationAsset;
use App\Models\SerialNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InstallationAssetController extends Controller
{
    public function store(Request $request, Installation $installation): RedirectResponse
    {
        $data = $this->validated($request);
        if (filled($data['serial_number_id'] ?? null)) {
            $serial = SerialNumber::findOrFail($data['serial_number_id']);
            abort_if($serial->installation_id && $serial->installation_id !== $installation->id, 422, __('Serial number belongs to another installation.'));
            $data['product_id'] ??= $serial->product_id;
            $data['serial_number'] ??= $serial->serial_number;
            $data['warranty_starts_on'] ??= $serial->warranty_starts_on;
            $data['warranty_ends_on'] ??= $serial->warranty_ends_on;
        }
        $asset = DB::transaction(function () use ($installation, $data, $request): InstallationAsset {
            $asset = $installation->assets()->create([...$data, 'asset_tag' => $data['asset_tag'] ?? $this->nextTag()]);
            $asset->events()->create(['recorded_by' => $request->user()->id, 'type' => 'commissioned', 'summary' => __('Asset commissioned'), 'occurred_at' => $asset->commissioned_at ?: now()]);

            return $asset;
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Asset :tag registered.', ['tag' => $asset->asset_tag])]);
    }

    public function update(Request $request, Installation $installation, InstallationAsset $asset): RedirectResponse
    {
        $this->ensureInstallation($installation, $asset);
        $asset->update($this->validated($request, true));

        return back()->with('toast', ['type' => 'success', 'message' => __('Asset updated.')]);
    }

    public function storeEvent(Request $request, Installation $installation, InstallationAsset $asset): RedirectResponse
    {
        $this->ensureInstallation($installation, $asset);
        $data = $request->validate([
            'work_order_id' => ['nullable', Rule::exists('work_orders', 'id')->where('installation_id', $installation->id)],
            'service_ticket_id' => ['nullable', Rule::exists('service_tickets', 'id')->where('installation_id', $installation->id)],
            'type' => ['required', Rule::in(['inspection', 'maintenance', 'fault', 'repair', 'replacement', 'rma', 'note'])],
            'summary' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string'],
            'occurred_at' => ['required', 'date'],
        ]);
        $asset->events()->create([...$data, 'recorded_by' => $request->user()->id]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Asset history updated.')]);
    }

    public function replace(Request $request, Installation $installation, InstallationAsset $asset): RedirectResponse
    {
        $this->ensureInstallation($installation, $asset);
        $data = $this->validated($request);
        $replacement = DB::transaction(function () use ($installation, $asset, $data, $request): InstallationAsset {
            $replacement = $installation->assets()->create([...$data, 'asset_tag' => $data['asset_tag'] ?? $this->nextTag()]);
            $asset->update(['status' => 'replaced', 'removed_at' => now(), 'replaced_by_asset_id' => $replacement->id]);
            $asset->events()->create(['recorded_by' => $request->user()->id, 'type' => 'replacement', 'summary' => __('Replaced by :tag', ['tag' => $replacement->asset_tag]), 'occurred_at' => now()]);
            $replacement->events()->create(['recorded_by' => $request->user()->id, 'type' => 'commissioned', 'summary' => __('Replacement asset commissioned'), 'occurred_at' => $replacement->commissioned_at ?: now()]);

            return $replacement;
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Replacement :tag registered.', ['tag' => $replacement->asset_tag])]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $updating = false): array
    {
        $asset = $updating ? $request->route('asset') : null;

        return $request->validate([
            'product_id' => ['nullable', 'exists:products,id'],
            'serial_number_id' => ['nullable', 'exists:serial_numbers,id', Rule::unique('installation_assets')->ignore($asset)],
            'source_purchase_document_id' => ['nullable', 'exists:purchase_documents,id'],
            'asset_tag' => ['nullable', 'string', 'max:100', Rule::unique('installation_assets')->ignore($asset)],
            'name' => [$updating ? 'sometimes' : 'required', 'string', 'max:255'],
            'category' => [$updating ? 'sometimes' : 'required', 'string', 'max:50'],
            'manufacturer' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['installed', 'active', 'faulty', 'removed', 'replaced', 'rma'])],
            'commissioned_at' => ['nullable', 'date'],
            'removed_at' => ['nullable', 'date'],
            'warranty_starts_on' => ['nullable', 'date'],
            'warranty_ends_on' => ['nullable', 'date', 'after_or_equal:warranty_starts_on'],
            'specifications' => ['nullable', 'array'],
        ]);
    }

    private function ensureInstallation(Installation $installation, InstallationAsset $asset): void
    {
        abort_unless($asset->installation_id === $installation->id, 404);
    }

    private function nextTag(): string
    {
        return 'AST-'.now()->format('Y').'-'.str_pad((string) (InstallationAsset::count() + 1), 6, '0', STR_PAD_LEFT);
    }
}
