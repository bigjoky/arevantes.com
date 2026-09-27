<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('event', 255)->change();
        });
    }

    public function down(): void
    {
        // Never truncate the audit trail when rolling back a release.
        if (DB::table('audit_logs')->whereRaw('length(event) > 20')->exists()) {
            throw new RuntimeException('Cannot shorten audit event names while longer events exist.');
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('event', 20)->change();
        });
    }
};
