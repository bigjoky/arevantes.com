<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_rmas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installation_asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number', 30)->nullable()->unique();
            $table->string('provider')->nullable();
            $table->string('external_reference')->nullable();
            $table->string('status', 20)->default('requested');
            $table->text('reason');
            $table->text('resolution')->nullable();
            $table->dateTime('requested_at');
            $table->dateTime('shipped_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['installation_asset_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_rmas');
    }
};
