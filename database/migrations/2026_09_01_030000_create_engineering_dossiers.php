<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engineering_dossiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('installation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->unique();
            $table->string('title');
            $table->string('business_line', 30);
            $table->string('kind', 30);
            $table->string('status', 20)->default('draft')->index();
            $table->unsignedInteger('current_version')->default(1);
            $table->text('scope');
            $table->text('site_conditions')->nullable();
            $table->text('calculations')->nullable();
            $table->text('proposed_solution')->nullable();
            $table->text('risks')->nullable();
            $table->text('standards')->nullable();
            $table->text('conclusions')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('handed_over_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handed_over_at')->nullable();
            $table->timestamps();

            $table->index(['installation_id', 'status']);
            $table->index(['opportunity_id', 'status']);
        });

        Schema::create('engineering_dossier_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('engineering_dossier_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->char('fingerprint', 64);
            $table->text('change_note');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['engineering_dossier_id', 'version']);
        });

        Schema::create('engineering_dossier_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('engineering_dossier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 30);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->unsignedInteger('dossier_version');
            $table->text('note');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['engineering_dossier_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engineering_dossier_events');
        Schema::dropIfExists('engineering_dossier_versions');
        Schema::dropIfExists('engineering_dossiers');
    }
};
