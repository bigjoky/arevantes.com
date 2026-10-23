<?php

use App\Support\Database\BackendTableSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_census_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_profile_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('status', 16);
            $table->json('settings');
            $table->json('company_snapshot');
            $table->string('company_fingerprint', 64);
            $table->foreignId('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->string('author_name');
            $table->text('source_reference')->nullable();
            $table->text('reason');
            $table->timestamps();
            $table->index(['company_profile_id', 'year', 'id'], 'company_census_latest_index');
        });
        BackendTableSecurity::restrict('company_census_versions');
    }

    public function down(): void
    {
        Schema::dropIfExists('company_census_versions');
    }
};
