<?php

namespace App\Services\AfterSales;

use App\Models\ServiceTicket;
use Illuminate\Support\Facades\DB;

class ServiceTicketFactory
{
    /** @param array<string, mixed> $data */
    public function create(array $data, ?int $createdBy = null): ServiceTicket
    {
        return DB::transaction(function () use ($data, $createdBy): ServiceTicket {
            $ticket = ServiceTicket::create([
                ...$data,
                'created_by' => $createdBy,
                'number' => 'TMP-'.str()->uuid(),
            ]);
            $ticket->update([
                'number' => 'SAT-'.now()->format('Y').'-'.str_pad((string) $ticket->id, 5, '0', STR_PAD_LEFT),
            ]);

            return $ticket;
        });
    }
}
