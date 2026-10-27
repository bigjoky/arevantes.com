<?php

namespace App\Console\Commands;

use App\Services\Finance\ReceivedInvoiceAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditReceivedInvoices extends Command
{
    protected $signature = 'received-invoices:audit {--strict : Fail when deployment findings require review} {--allow-production : Requires explicit prior human authorization for a production read-only audit}';

    protected $description = 'Read-only fiscal identity and original-document custody diagnostic; never merges or repairs records';

    public function handle(ReceivedInvoiceAudit $audit): int
    {
        if (app()->environment('production') && ! $this->option('allow-production')) {
            $this->error('Production audit requires explicit authorization. No data has been inspected.');

            return self::FAILURE;
        }
        if (DB::transactionLevel()) {
            $this->error('Run the diagnostic outside an existing transaction.');

            return self::FAILURE;
        }
        $sqlite = DB::getDriverName() === 'sqlite';
        $previous = $sqlite ? DB::selectOne('PRAGMA query_only')->query_only : null;
        try {
            if ($sqlite) {
                DB::statement('PRAGMA query_only = ON');
            }
            $report = DB::transaction(function () use ($audit) {
                if (DB::getDriverName() === 'pgsql') {
                    DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
                }

                return $audit->report();
            });
            $this->line(json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        } finally {
            if ($sqlite) {
                DB::statement('PRAGMA query_only = '.($previous ? 'ON' : 'OFF'));
            }
        }

        return $this->option('strict') && $report['deployment_review_required'] ? self::FAILURE : self::SUCCESS;
    }
}
