<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE INDEX expenses_issuer_lookup_index ON expenses (upper(replace(replace(replace(issuer_tax_id, ' ', ''), '-', ''), '.', '')))");
        DB::statement("CREATE INDEX suppliers_issuer_lookup_index ON suppliers (upper(replace(replace(replace(tax_id, ' ', ''), '-', ''), '.', '')))");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX suppliers_issuer_lookup_index');
        DB::statement('DROP INDEX expenses_issuer_lookup_index');
    }
};
