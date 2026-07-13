<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->string('parent_code', 20)->nullable()->index()->after('type');
            $table->unsignedTinyInteger('level')->default(3)->after('parent_code');
            $table->boolean('is_postable')->default(true)->after('level');
            $table->string('source', 30)->default('custom')->after('is_active');
            $table->string('source_version', 20)->nullable()->after('source');
        });

        $now = now();
        $rows = require database_path('data/pgc_2024.php');
        collect($rows)->chunk(150)->each(function ($chunk) use ($now) {
            DB::table('ledger_accounts')->upsert($chunk->map(fn ($row) => ['code' => $row[0], 'name' => $row[1], 'type' => $row[2], 'parent_code' => $row[3], 'level' => $row[4], 'is_postable' => $row[5], 'is_active' => true, 'source' => 'pgc', 'source_version' => '2024-12-21', 'created_at' => $now, 'updated_at' => $now])->all(), ['code'], ['name', 'type', 'parent_code', 'level', 'is_postable', 'source', 'source_version', 'updated_at']);
        });
    }

    public function down(): void
    {
        DB::table('ledger_accounts')->where('source', 'pgc')->whereNotIn('id', DB::table('journal_lines')->select('ledger_account_id'))->delete();
        Schema::table('ledger_accounts', fn (Blueprint $table) => $table->dropColumn(['parent_code', 'level', 'is_postable', 'source', 'source_version']));
    }
};
