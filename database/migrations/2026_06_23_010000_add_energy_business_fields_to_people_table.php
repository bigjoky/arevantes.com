<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->string('customer_type')->nullable()->after('category');
            $table->string('company_name')->nullable()->after('last_name');
            $table->string('tax_id')->nullable()->after('company_name');
            $table->string('staff_role')->nullable()->after('tax_id');
            $table->json('service_interests')->nullable()->after('preferred_language');
            $table->string('lead_source')->nullable()->after('service_interests');
            $table->string('billing_email')->nullable()->after('lead_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn([
                'customer_type',
                'company_name',
                'tax_id',
                'staff_role',
                'service_interests',
                'lead_source',
                'billing_email',
            ]);
        });
    }
};
