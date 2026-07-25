<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->date('next_follow_up_date')->nullable()->after('last_follow_up_note')->index();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->dropIndex(['next_follow_up_date']);
            $table->dropColumn('next_follow_up_date');
        });
    }
};
