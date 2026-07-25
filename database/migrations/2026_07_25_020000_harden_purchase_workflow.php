<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->date('expected_delivery_date')->nullable()->after('due_date')->index();
            $table->timestamp('sent_at')->nullable()->after('expected_delivery_date');
            $table->timestamp('cancelled_at')->nullable()->after('sent_at');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable()->after('cancelled_by');
            $table->timestamp('closed_at')->nullable()->after('cancellation_reason');
            $table->foreignId('closed_by')->nullable()->after('closed_at')->constrained('users')->nullOnDelete();
            $table->text('closing_reason')->nullable()->after('closed_by');
        });

        Schema::create('purchase_document_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 40);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['purchase_document_id', 'occurred_at']);
        });

        Schema::create('purchase_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('matching_amount_tolerance', 12, 2)->default(0);
            $table->decimal('matching_percent_tolerance', 5, 2)->default(0);
            $table->decimal('shipping_tax_percent', 5, 2)->default(21);
            $table->timestamps();
        });

        DB::table('purchase_settings')->insert([
            'matching_amount_tolerance' => 0,
            'matching_percent_tolerance' => 0,
            'shipping_tax_percent' => 21,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_settings');
        Schema::dropIfExists('purchase_document_events');

        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropConstrainedForeignId('closed_by');
            $table->dropIndex(['expected_delivery_date']);
            $table->dropColumn([
                'expected_delivery_date',
                'sent_at',
                'cancelled_at',
                'cancellation_reason',
                'closed_at',
                'closing_reason',
            ]);
        });
    }
};
