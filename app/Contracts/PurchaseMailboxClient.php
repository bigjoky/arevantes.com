<?php

namespace App\Contracts;

use App\Models\PurchaseMailbox;
use Carbon\CarbonInterface;

interface PurchaseMailboxClient
{
    /** @return iterable<int, array<string, mixed>> */
    public function messages(PurchaseMailbox $mailbox, CarbonInterface $since): iterable;
}
