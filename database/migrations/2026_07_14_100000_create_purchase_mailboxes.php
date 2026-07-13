<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_mailboxes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Supplier invoices');
            $table->string('email');
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(993);
            $table->string('encryption', 10)->default('ssl');
            $table->boolean('validate_certificate')->default(true);
            $table->string('username');
            $table->text('password');
            $table->string('folder')->default('INBOX');
            $table->unsignedSmallInteger('lookback_days')->default(30);
            $table->boolean('auto_extract')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sync_attempt_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_mailbox_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_mailbox_id')->constrained()->cascadeOnDelete();
            $table->string('message_uid');
            $table->string('message_id')->nullable();
            $table->string('subject')->nullable();
            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('status', 30)->default('processing')->index();
            $table->unsignedSmallInteger('attachment_count')->default(0);
            $table->unsignedSmallInteger('imported_count')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['purchase_mailbox_id', 'message_uid']);
        });

        Schema::table('purchase_inbox_documents', function (Blueprint $table) {
            $table->foreignId('purchase_mailbox_message_id')->nullable()->after('purchase_document_id')->constrained()->nullOnDelete();
            $table->string('source', 20)->default('manual')->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_inbox_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_mailbox_message_id');
            $table->dropColumn('source');
        });
        Schema::dropIfExists('purchase_mailbox_messages');
        Schema::dropIfExists('purchase_mailboxes');
    }
};
