<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installations', function (Blueprint $table) {
            $table->timestamp('dossier_delivery_confirmed_at')->nullable();
            $table->foreignId('dossier_delivery_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::table('crm_activities', fn (Blueprint $table) => $table->boolean('required_for_closure')->default(false));
    }

    public function down(): void
    {
        Schema::table('crm_activities', fn (Blueprint $table) => $table->dropColumn('required_for_closure'));
        Schema::table('installations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dossier_delivery_confirmed_by');
            $table->dropColumn('dossier_delivery_confirmed_at');
        });
    }
};
