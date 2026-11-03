<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_contact_mail', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('receipt_id')->constrained('website_contact_receipts')->restrictOnDelete();
            $table->foreignId('opportunity_id')->constrained()->restrictOnDelete();
            $table->string('kind', 20);
            $table->string('recipient_hash', 64);
            $table->text('recipient');
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestampTz('attempted_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->string('error_class')->nullable();
            $table->timestampsTz();
            $table->unique(['receipt_id', 'kind', 'recipient_hash']);
            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_contact_mail');
    }
};
