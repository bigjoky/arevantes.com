<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });

        Schema::table('opportunity_stage_histories', function (Blueprint $table): void {
            $table->text('reason')->nullable()->after('to_stage');
        });

        Schema::table('quote_versions', function (Blueprint $table): void {
            $table->text('change_note')->nullable()->after('created_by');
        });

        Schema::create('opportunity_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 40);
            $table->string('summary');
            $table->text('note')->nullable();
            $table->json('changes')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['opportunity_id', 'occurred_at']);
            $table->index(['event_type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_events');
        Schema::table('quote_versions', fn (Blueprint $table) => $table->dropColumn('change_note'));
        Schema::table('opportunity_stage_histories', fn (Blueprint $table) => $table->dropColumn('reason'));
        Schema::table('opportunities', fn (Blueprint $table) => $table->dropConstrainedForeignId('updated_by'));
    }
};
