<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_reclassification_batches', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->string('request_hash', 64);
            $t->string('preview_revision', 64);
            $t->date('correction_date');
            $t->text('reason');
            $t->json('selection');
            $t->jsonb('results');
            $t->timestampTz('recorded_at_utc');
            $t->index(['recorded_at_utc', 'id']);
        });
        // Link through the batch's preserved correction IDs; no historical correction row is rewritten.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE INDEX accounting_reclassification_entries_idx ON accounting_reclassification_batches USING GIN ((results->'entries'))");
            DB::statement('ALTER TABLE accounting_reclassification_batches ENABLE ROW LEVEL SECURITY');
            DB::statement('REVOKE ALL ON TABLE accounting_reclassification_batches FROM PUBLIC');
            foreach (['anon', 'authenticated'] as $r) {
                if (DB::selectOne('select 1 from pg_roles where rolname=?', [$r])) {
                    DB::statement("REVOKE ALL ON TABLE accounting_reclassification_batches FROM $r");
                }
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Preserve accounting reclassification history; use a reviewed forward migration.');
    }
};
