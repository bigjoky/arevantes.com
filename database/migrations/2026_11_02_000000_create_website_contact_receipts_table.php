<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_contact_receipts', function (Blueprint $table): void {
            $table->id();
            $table->string('fingerprint', 64)->index();
            $table->text('payload')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('expires_at')->index();
            $table->timestampsTz();
            $table->index(['processed_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_contact_receipts');
    }
};
