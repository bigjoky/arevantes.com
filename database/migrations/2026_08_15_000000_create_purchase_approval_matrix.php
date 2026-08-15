<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_approval_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_type', 20)->nullable();
            $table->decimal('min_amount', 14, 2)->default(0);
            $table->decimal('max_amount', 14, 2)->nullable();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['is_active', 'priority'], 'purchase_approval_rule_priority_idx');
            $table->index(['supplier_id', 'is_active'], 'purchase_approval_rule_supplier_idx');
        });

        Schema::create('purchase_order_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_approval_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'requested_at'], 'purchase_approval_queue_idx');
            $table->index(['purchase_document_id', 'status'], 'purchase_document_approval_status_idx');
        });

        DB::table('purchase_documents')
            ->where('type', 'purchase_order')
            ->where('status', 'pending_approval')
            ->orderBy('id')
            ->eachById(function ($order): void {
                DB::table('purchase_order_approvals')->insert([
                    'purchase_document_id' => $order->id,
                    'purchase_approval_rule_id' => null,
                    'requested_by' => $order->created_by,
                    'decided_by' => null,
                    'status' => 'pending',
                    'reason' => 'Default order amount threshold',
                    'notes' => null,
                    'requested_at' => $order->created_at ?? now(),
                    'decided_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_approvals');
        Schema::dropIfExists('purchase_approval_rules');
    }
};
