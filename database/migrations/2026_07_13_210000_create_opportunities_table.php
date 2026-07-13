<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('installation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code')->nullable()->unique();
            $table->string('title');
            $table->string('stage')->default('new');
            $table->unsignedTinyInteger('probability')->default(10);
            $table->decimal('estimated_value', 15, 2)->default(0);
            $table->date('expected_close_on')->nullable();
            $table->string('source')->nullable();
            $table->text('lost_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['stage', 'expected_close_on']);
            $table->index(['owner_user_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
