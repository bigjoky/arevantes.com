<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('symbol', 20);
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('rate', 5, 2);
            $table->string('kind', 20)->default('vat');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('legal_name');
            $table->string('trade_name')->nullable();
            $table->string('tax_id', 30)->nullable()->index();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('country', 2)->default('ES');
            $table->unsignedSmallInteger('payment_terms_days')->default(30);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('sku', 60)->nullable()->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('tax_rate_id')->constrained()->restrictOnDelete();
            $table->decimal('sale_price', 12, 2)->default(0);
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->boolean('track_stock')->default(false);
            $table->boolean('track_serials')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });

        Schema::create('document_series', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 30);
            $table->string('name');
            $table->string('prefix', 20);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->unsignedSmallInteger('fiscal_year')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['document_type', 'prefix', 'fiscal_year']);
        });

        $now = now();
        DB::table('units')->insert([
            ['code' => 'unit', 'name' => 'Unit', 'symbol' => 'ud', 'decimal_places' => 0, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'hour', 'name' => 'Hour', 'symbol' => 'h', 'decimal_places' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'meter', 'name' => 'Metre', 'symbol' => 'm', 'decimal_places' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'sqm', 'name' => 'Square metre', 'symbol' => 'm2', 'decimal_places' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'kwp', 'name' => 'Kilowatt peak', 'symbol' => 'kWp', 'decimal_places' => 3, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('tax_rates')->insert([
            ['name' => 'IVA general', 'rate' => 21, 'kind' => 'vat', 'is_default' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'IVA reducido', 'rate' => 10, 'kind' => 'vat', 'is_default' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'IVA superreducido', 'rate' => 4, 'kind' => 'vat', 'is_default' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Exento', 'rate' => 0, 'kind' => 'exempt', 'is_default' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
        foreach (['quote' => 'PRE', 'order' => 'PED', 'delivery_note' => 'ALB', 'invoice' => 'FAC', 'credit_note' => 'REC', 'purchase_order' => 'PC', 'supplier_invoice' => 'FR'] as $type => $prefix) {
            DB::table('document_series')->insert(['document_type' => $type, 'name' => $prefix.' '.now()->year, 'prefix' => $prefix, 'next_number' => 1, 'fiscal_year' => now()->year, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_series');
        Schema::dropIfExists('products');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('units');
    }
};
