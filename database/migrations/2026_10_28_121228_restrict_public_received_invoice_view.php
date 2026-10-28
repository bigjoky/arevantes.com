<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::transaction(function (): void {
            DB::statement("SET LOCAL lock_timeout = '2s'");
            DB::statement('REVOKE ALL PRIVILEGES ON TABLE public.received_invoice_candidates FROM PUBLIC');
            foreach (['anon', 'authenticated'] as $role) {
                if (DB::selectOne('SELECT EXISTS(SELECT 1 FROM pg_roles WHERE rolname = ?) AS present', [$role])->present) {
                    DB::statement('REVOKE ALL PRIVILEGES ON TABLE public.received_invoice_candidates FROM "'.$role.'"');
                }
            }
            // Keep OID, definition and trigger dependencies; RLS follows the querying backend.
            DB::statement('ALTER VIEW public.received_invoice_candidates SET (security_invoker = true)');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            throw new RuntimeException('Security rollback must not restore public fiscal access. Keep the restricted view and use a reviewed forward migration.');
        }
    }
};
