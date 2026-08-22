<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('database_backups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('engine', 12);
            $table->string('status', 12);
            $table->string('disk');
            $table->text('path');
            $table->unsignedBigInteger('bytes')->nullable();
            $table->char('checksum', 64)->nullable();
            $table->json('manifest')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'completed_at']);
            $table->index('expires_at');
        });

        Schema::table('backup_restorations', function (Blueprint $table) {
            $table->foreignUuid('database_backup_id')->nullable()->constrained('database_backups')->nullOnDelete();
            $table->string('engine', 12)->default('sqlite');
            $table->unsignedBigInteger('rpo_seconds')->nullable();
            $table->unsignedInteger('rto_seconds')->nullable();
            $table->json('metrics')->nullable();
            $table->index(['status', 'verified_at']);
        });
    }

    public function down(): void
    {
        Schema::table('backup_restorations', function (Blueprint $table) {
            $table->dropForeign(['database_backup_id']);
            $table->dropIndex(['status', 'verified_at']);
            $table->dropColumn(['database_backup_id', 'engine', 'rpo_seconds', 'rto_seconds', 'metrics']);
        });

        Schema::dropIfExists('database_backups');
    }
};
