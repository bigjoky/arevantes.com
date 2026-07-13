<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_documents', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('content_hash');
            $table->string('embedding_model')->nullable()->after('version');
            $table->timestamp('indexed_at')->nullable()->after('embedding_model');
        });

        Schema::create('ai_generations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('installation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('queued')->index();
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->string('prompt_version')->default('installation-briefing-v1');
            $table->longText('prompt')->nullable();
            $table->json('structured_response')->nullable();
            $table->longText('raw_response')->nullable();
            $table->json('sources')->nullable();
            $table->json('usage')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_generations');
        Schema::table('knowledge_documents', function (Blueprint $table) {
            $table->dropColumn(['version', 'embedding_model', 'indexed_at']);
        });
    }
};
