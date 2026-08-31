<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->string('billing_name')->nullable()->after('billing_email');
            $table->string('billing_tax_id')->nullable()->after('billing_name');
            $table->string('billing_address_line_1')->nullable()->after('billing_tax_id');
            $table->string('billing_address_line_2')->nullable()->after('billing_address_line_1');
            $table->string('billing_city')->nullable()->after('billing_address_line_2');
            $table->string('billing_locality')->nullable()->after('billing_city');
            $table->string('billing_province_state')->nullable()->after('billing_locality');
            $table->string('billing_postal_code')->nullable()->after('billing_province_state');
            $table->string('billing_country')->nullable()->after('billing_postal_code');
            $table->unsignedSmallInteger('payment_terms_days')->nullable()->after('billing_country');
        });

        Schema::table('customer_contacts', function (Blueprint $table) {
            $table->json('roles')->nullable()->after('position');
            $table->string('document_type', 30)->nullable()->after('roles');
            $table->string('document_number')->nullable()->after('document_type');
            $table->boolean('is_authorized_signatory')->default(false)->after('is_primary');
        });

        DB::table('customer_contacts')
            ->whereNull('roles')
            ->update(['roles' => json_encode(['commercial'], JSON_THROW_ON_ERROR)]);
    }

    public function down(): void
    {
        Schema::table('customer_contacts', function (Blueprint $table) {
            $table->dropColumn(['roles', 'document_type', 'document_number', 'is_authorized_signatory']);
        });

        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn([
                'billing_name',
                'billing_tax_id',
                'billing_address_line_1',
                'billing_address_line_2',
                'billing_city',
                'billing_locality',
                'billing_province_state',
                'billing_postal_code',
                'billing_country',
                'payment_terms_days',
            ]);
        });
    }
};
