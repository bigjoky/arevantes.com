<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_service_billings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_cycle_commitment_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('sales_document_id')->nullable()->unique()->constrained('sales_documents')->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->decimal('net_amount', 14, 2);
            $table->decimal('tax_percent', 5, 2)->default(21);
            $table->decimal('tax_amount', 14, 2);
            $table->decimal('gross_amount', 14, 2);
            $table->date('due_on');
            $table->text('generation_note');
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('prepared_at');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'due_on'], 'recurring_service_billing_status_due_idx');
            $table->index('prepared_by');
            $table->index('issued_by');
        });

        Schema::create('service_cycle_communications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_cycle_commitment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('channel', 20);
            $table->string('status', 20)->default('draft');
            $table->string('subject');
            $table->text('message');
            $table->timestampTz('scheduled_for');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->text('delivery_note')->nullable();
            $table->timestamps();
            $table->unique(['service_cycle_commitment_id', 'type'], 'service_cycle_communication_unique');
            $table->index(['person_id', 'status', 'scheduled_for'], 'service_cycle_communication_portal_idx');
            $table->index('created_by');
            $table->index('confirmed_by');
            $table->index('sent_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_cycle_communications');
        Schema::dropIfExists('recurring_service_billings');
    }
};
