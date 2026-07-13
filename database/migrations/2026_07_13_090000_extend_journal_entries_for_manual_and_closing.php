<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->string('entry_kind', 20)->default('automatic')->after('event');
            $table->foreignId('reverses_entry_id')->nullable()->unique()->after('entry_kind')->constrained('journal_entries')->nullOnDelete();
        });

        DB::table('ledger_accounts')->insert(['code' => '129', 'name' => 'Profit or loss for the year', 'type' => 'equity', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reverses_entry_id');
            $table->dropColumn('entry_kind');
        });
        DB::table('ledger_accounts')->where('code', '129')->delete();
    }
};
