<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('opportunities', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('crm_activities', function (Blueprint $table) {
            $table->foreignId('opportunity_id')
                ->nullable()
                ->after('person_id')
                ->constrained()
                ->nullOnDelete();
            $table->softDeletes();
            $table->index(['opportunity_id', 'status', 'due_at']);
        });

        Schema::create('opportunity_stage_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_stage')->nullable();
            $table->string('to_stage');
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['opportunity_id', 'changed_at']);
        });

        DB::table('opportunities')
            ->select(['id', 'stage', 'created_by', 'created_at'])
            ->orderBy('id')
            ->chunkById(500, function ($opportunities): void {
                DB::table('opportunity_stage_histories')->insert(
                    $opportunities->map(fn ($opportunity) => [
                        'opportunity_id' => $opportunity->id,
                        'changed_by' => $opportunity->created_by,
                        'from_stage' => null,
                        'to_stage' => $opportunity->stage,
                        'changed_at' => $opportunity->created_at,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])->all(),
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_stage_histories');

        Schema::table('crm_activities', function (Blueprint $table) {
            $table->dropIndex(['opportunity_id', 'status', 'due_at']);
            $table->dropConstrainedForeignId('opportunity_id');
            $table->dropSoftDeletes();
        });

        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('people', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
