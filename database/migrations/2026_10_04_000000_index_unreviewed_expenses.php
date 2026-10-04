<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX expenses_documentary_review_index ON expenses (expense_date DESC, id DESC) WHERE tax_calculation_version IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX expenses_documentary_review_index');
    }
};
