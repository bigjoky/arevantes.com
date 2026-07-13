<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('action_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 30);
            $table->date('reminder_on');
            $table->unsignedInteger('item_count');
            $table->timestamps();

            $table->unique(['user_id', 'category', 'reminder_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_reminders');
    }
};
