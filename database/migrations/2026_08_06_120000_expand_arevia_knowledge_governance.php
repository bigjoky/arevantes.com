<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_documents', function (Blueprint $table) {
            $table->string('area')->default('general')->after('title')->index();
            $table->json('allowed_roles')->nullable()->after('area');
            $table->string('confidentiality')->default('internal')->after('allowed_roles');
            $table->string('status')->default('indexed')->after('confidentiality')->index();
            $table->text('failure_reason')->nullable()->after('status');
            $table->timestamp('stale_at')->nullable()->after('indexed_at')->index();
        });
        DB::table('knowledge_documents')->whereNotNull('indexed_at')->update(['stale_at' => now()->addDays(30)]);

        Schema::create('knowledge_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('content_hash', 64);
            $table->longText('content');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();
            $table->unique(['knowledge_document_id', 'version']);
        });

        Schema::table('ai_messages', function (Blueprint $table) {
            $table->string('context_module')->default('general')->after('page_url')->index();
            $table->longText('prompt')->nullable()->after('context_module');
            $table->json('source_context')->nullable()->after('sources');
            $table->json('usage')->nullable()->after('model');
            $table->unsignedBigInteger('cost_micros')->nullable()->after('usage');
            $table->unsignedTinyInteger('automated_score')->nullable()->after('cost_micros');
            $table->unsignedTinyInteger('human_score')->nullable()->after('automated_score');
            $table->foreignId('reviewed_by')->nullable()->after('human_score')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });

        Schema::create('ai_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_message_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event')->index();
            $table->string('context_module')->nullable()->index();
            $table->longText('prompt')->nullable();
            $table->json('sources')->nullable();
            $table->longText('response')->nullable();
            $table->string('action')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_audit_logs');
        Schema::table('ai_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['context_module', 'prompt', 'source_context', 'usage', 'cost_micros', 'automated_score', 'human_score', 'reviewed_at']);
        });
        Schema::dropIfExists('knowledge_document_versions');
        Schema::table('knowledge_documents', function (Blueprint $table) {
            $table->dropColumn(['area', 'allowed_roles', 'confidentiality', 'status', 'failure_reason', 'stale_at']);
        });
    }
};
