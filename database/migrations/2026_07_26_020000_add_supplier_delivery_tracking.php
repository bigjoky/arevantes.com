<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->date('supplier_confirmed_delivery_date')->nullable()->after('expected_delivery_date')->index();
            $table->timestamp('supplier_confirmed_at')->nullable()->after('supplier_confirmed_delivery_date');
            $table->foreignId('supplier_confirmed_by')->nullable()->after('supplier_confirmed_at')->constrained('users')->nullOnDelete();
            $table->string('supplier_confirmation_reference')->nullable()->after('supplier_confirmed_by');
            $table->timestamp('last_followed_up_at')->nullable()->after('supplier_confirmation_reference');
            $table->foreignId('last_followed_up_by')->nullable()->after('last_followed_up_at')->constrained('users')->nullOnDelete();
            $table->text('last_follow_up_note')->nullable()->after('last_followed_up_by');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_confirmed_by');
            $table->dropConstrainedForeignId('last_followed_up_by');
            $table->dropIndex(['supplier_confirmed_delivery_date']);
            $table->dropColumn([
                'supplier_confirmed_delivery_date',
                'supplier_confirmed_at',
                'supplier_confirmation_reference',
                'last_followed_up_at',
                'last_follow_up_note',
            ]);
        });
    }
};
