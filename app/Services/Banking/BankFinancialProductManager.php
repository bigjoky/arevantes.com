<?php

namespace App\Services\Banking;

use App\Models\BankFinancialProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BankFinancialProductManager
{
    public function create(array $data, int $userId): BankFinancialProduct
    {
        return DB::transaction(fn () => $this->save(new BankFinancialProduct, [...$data, 'created_by' => $userId]));
    }

    public function update(BankFinancialProduct $product, array $data): BankFinancialProduct
    {
        return DB::transaction(fn () => $this->save(BankFinancialProduct::lockForUpdate()->findOrFail($product->id), $data));
    }

    private function save(BankFinancialProduct $product, array $data): BankFinancialProduct
    {
        $previousFacilityId = $product->shared_credit_facility_id;
        $facilityId = $data['type'] === 'credit_card' ? ($data['shared_credit_facility_id'] ?? null) : null;

        if ($product->exists && $product->linkedCards()->exists() && $data['type'] !== 'credit_facility') {
            throw ValidationException::withMessages(['type' => __('A facility linked to cards cannot be changed into another product type.')]);
        }
        if ($product->exists && $product->linkedCards()->exists() && ! $data['is_active']) {
            throw ValidationException::withMessages(['is_active' => __('A facility with linked cards cannot be deactivated.')]);
        }

        if ($facilityId) {
            if ($product->exists && (int) $facilityId === $product->id) {
                throw ValidationException::withMessages(['shared_credit_facility_id' => __('A product cannot finance itself.')]);
            }
            $facility = BankFinancialProduct::whereKey($facilityId)->lockForUpdate()->firstOrFail();
            if ($facility->type !== 'credit_facility' || ! $facility->is_active) {
                throw ValidationException::withMessages(['shared_credit_facility_id' => __('Select an active credit facility.')]);
            }
            if ($facility->currency !== $data['currency']) {
                throw ValidationException::withMessages(['currency' => __('The card and shared facility must use the same currency.')]);
            }
            $otherCardsUsed = (float) $facility->linkedCards()
                ->when($product->exists, fn ($query) => $query->where('id', '!=', $product->id))
                ->sum('used_amount');
            if ($otherCardsUsed + (float) $data['used_amount'] > (float) $facility->credit_limit + .005) {
                throw ValidationException::withMessages(['used_amount' => __('The linked cards exceed the shared credit limit.')]);
            }
            $data['credit_limit'] = $facility->credit_limit;
        } elseif (! isset($data['credit_limit']) || (float) $data['credit_limit'] <= 0) {
            throw ValidationException::withMessages(['credit_limit' => __('An independent product requires its own credit limit.')]);
        } elseif ((float) $data['used_amount'] > (float) $data['credit_limit'] + .005) {
            throw ValidationException::withMessages(['used_amount' => __('The used amount cannot exceed the credit limit.')]);
        }

        if ($data['type'] === 'credit_facility' && $product->exists) {
            $linkedCardsUsed = (float) $product->linkedCards()->sum('used_amount');
            if ($product->linkedCards()->where('currency', '!=', $data['currency'])->exists()) {
                throw ValidationException::withMessages(['currency' => __('A shared facility must use the same currency as all linked cards.')]);
            }
            if ($linkedCardsUsed > (float) $data['credit_limit'] + .005) {
                throw ValidationException::withMessages(['credit_limit' => __('The limit cannot be lower than the balance used by linked cards.')]);
            }
            if ($product->linkedCards()->exists()) {
                $data['used_amount'] = $linkedCardsUsed;
            }
        }

        $data['shared_credit_facility_id'] = $facilityId;
        $product->fill($data)->save();

        foreach (array_unique(array_filter([$previousFacilityId, $facilityId])) as $affectedFacilityId) {
            $this->syncFacility((int) $affectedFacilityId);
        }

        return $product->refresh();
    }

    private function syncFacility(int $facilityId): void
    {
        $facility = BankFinancialProduct::whereKey($facilityId)->lockForUpdate()->first();
        if ($facility) {
            $facility->update(['used_amount' => $facility->linkedCards()->sum('used_amount')]);
        }
    }
}
