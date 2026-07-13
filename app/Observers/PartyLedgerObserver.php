<?php

namespace App\Observers;

use App\Models\LedgerAccount;
use App\Models\Person;
use App\Models\Supplier;

class PartyLedgerObserver
{
    public function saved(Person|Supplier $party): void
    {
        if ($party instanceof Person && $party->category !== 'customer') {
            return;
        }
        $attribute = $party instanceof Person ? 'receivable_account_code' : 'payable_account_code';
        $prefix = $party instanceof Person ? '430' : '400';
        $code = $party->{$attribute} ?: $prefix.str_pad((string) $party->id, 5, '0', STR_PAD_LEFT);
        $name = $party instanceof Person ? ($party->company_name ?: trim($party->first_name.' '.$party->last_name)) : $party->legal_name;
        LedgerAccount::updateOrCreate(['code' => $code], ['name' => $name ?: $code, 'type' => $party instanceof Person ? 'asset' : 'liability', 'parent_code' => $prefix, 'level' => strlen($code), 'is_postable' => true, 'is_active' => true, 'source' => 'custom']);
        if ($party->{$attribute} !== $code) {
            $party->withoutEvents(fn () => $party->update([$attribute => $code]));
        }
    }
}
