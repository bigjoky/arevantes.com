<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('legal_name');
            $table->string('trade_name')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('address')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('country')->default('Spain');
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('website')->nullable();
            $table->string('iban', 34)->nullable();
            $table->string('bic', 11)->nullable();
            $table->string('commercial_registry')->nullable();
            $table->text('invoice_footer')->nullable();
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });

        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 20)->default('open');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('document_series', function (Blueprint $table) {
            $table->foreignId('fiscal_year_id')->nullable()->after('fiscal_year')->constrained()->nullOnDelete();
            $table->timestamp('last_issued_at')->nullable()->after('is_active');
        });

        $now = now();
        $yearId = DB::table('fiscal_years')->insertGetId(['year' => now()->year, 'starts_on' => now()->startOfYear()->toDateString(), 'ends_on' => now()->endOfYear()->toDateString(), 'status' => 'open', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('document_series')->where('fiscal_year', now()->year)->update(['fiscal_year_id' => $yearId]);
        DB::table('company_profiles')->insert(['legal_name' => config('company.legal_name'), 'tax_id' => config('company.tax_id'), 'address' => config('company.address'), 'postal_code' => config('company.postal_code'), 'city' => config('company.city'), 'province' => config('company.province'), 'country' => config('company.country'), 'email' => config('company.email'), 'phone' => config('company.phone'), 'created_at' => $now, 'updated_at' => $now]);
    }

    public function down(): void
    {
        Schema::table('document_series', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fiscal_year_id');
            $table->dropColumn('last_issued_at');
        });
        Schema::dropIfExists('fiscal_years');
        Schema::dropIfExists('company_profiles');
    }
};
