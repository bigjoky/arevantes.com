<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_action_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_action_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 30);
            $table->string('status_from', 20)->nullable();
            $table->string('status_to', 20)->nullable();
            $table->date('promised_on')->nullable();
            $table->decimal('promised_amount', 14, 2)->nullable();
            $table->date('next_action_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['collection_action_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_action_events');
    }
};
