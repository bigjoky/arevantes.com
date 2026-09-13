<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('service_cycle_commitments', 'technical_snapshot')) {
            Schema::table('service_cycle_commitments', function (Blueprint $table): void {
                $table->json('technical_snapshot')->nullable()->after('capacity_snapshot');
                $table->foreignId('service_ticket_id')->nullable()->unique()->after('technical_snapshot')->constrained('service_tickets')->nullOnDelete();
                $table->foreignId('work_order_id')->nullable()->unique()->after('service_ticket_id')->constrained('work_orders')->nullOnDelete();
                $table->string('schedule_status', 30)->default('unplanned')->after('work_order_id');
                $table->foreignId('materialized_by')->nullable()->after('schedule_status')->constrained('users')->nullOnDelete();
                $table->timestamp('materialized_at')->nullable()->after('materialized_by');
                $table->foreignId('schedule_confirmed_by')->nullable()->after('materialized_at')->constrained('users')->nullOnDelete();
                $table->timestamp('schedule_confirmed_at')->nullable()->after('schedule_confirmed_by');
            });
        }

        if (! Schema::hasIndex('service_cycle_commitments', 'service_cycle_schedule_status_period_idx')) {
            Schema::table('service_cycle_commitments', function (Blueprint $table): void {
                $table->index(['schedule_status', 'period_starts_on'], 'service_cycle_schedule_status_period_idx');
            });
        }

        if (! Schema::hasTable('service_cycle_schedule_revisions')) {
            Schema::create('service_cycle_schedule_revisions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('service_cycle_commitment_id');
                $table->foreignId('work_order_id')->nullable();
                $table->unsignedInteger('version');
                $table->string('status', 30)->default('pending_confirmation');
                $table->timestampTz('scheduled_start');
                $table->timestampTz('scheduled_end');
                $table->foreignId('assigned_user_id')->nullable();
                $table->text('reason');
                $table->foreignId('created_by')->nullable();
                $table->foreignId('confirmed_by')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamp('superseded_at')->nullable();
                $table->timestamps();
            });
        }

        $revisionForeignKeys = [
            'service_cycle_commitment_id' => ['service_cycle_commitments', 'cascade', 'svc_schedule_commitment_fk'],
            'work_order_id' => ['work_orders', 'null', 'svc_schedule_work_order_fk'],
            'assigned_user_id' => ['users', 'null', 'svc_schedule_assignee_fk'],
            'created_by' => ['users', 'null', 'svc_schedule_creator_fk'],
            'confirmed_by' => ['users', 'null', 'svc_schedule_confirmer_fk'],
        ];

        foreach ($revisionForeignKeys as $column => [$foreignTable, $deleteAction, $constraint]) {
            if (! Schema::hasForeignKey('service_cycle_schedule_revisions', [$column])) {
                Schema::table('service_cycle_schedule_revisions', function (Blueprint $table) use ($column, $foreignTable, $deleteAction, $constraint): void {
                    $foreign = $table->foreign($column, $constraint)->references('id')->on($foreignTable);
                    $deleteAction === 'cascade' ? $foreign->cascadeOnDelete() : $foreign->nullOnDelete();
                });
            }
        }

        if (! Schema::hasIndex('service_cycle_schedule_revisions', 'service_cycle_schedule_revision_unique')) {
            Schema::table('service_cycle_schedule_revisions', function (Blueprint $table): void {
                $table->unique(['service_cycle_commitment_id', 'version'], 'service_cycle_schedule_revision_unique');
            });
        }

        if (! Schema::hasIndex('service_cycle_schedule_revisions', 'service_cycle_schedule_revision_status_idx')) {
            Schema::table('service_cycle_schedule_revisions', function (Blueprint $table): void {
                $table->index(['service_cycle_commitment_id', 'status'], 'service_cycle_schedule_revision_status_idx');
            });
        }

        if (! Schema::hasIndex('service_cycle_communications', 'service_cycle_communications_service_cycle_commitment_id_index')) {
            Schema::table('service_cycle_communications', function (Blueprint $table): void {
                $table->index('service_cycle_commitment_id');
            });
        }

        if (Schema::hasIndex('service_cycle_communications', 'service_cycle_communication_unique')) {
            Schema::table('service_cycle_communications', function (Blueprint $table): void {
                $table->dropUnique('service_cycle_communication_unique');
            });
        }

        if (! Schema::hasColumn('service_cycle_communications', 'revision')) {
            Schema::table('service_cycle_communications', function (Blueprint $table): void {
                $table->unsignedInteger('revision')->default(1)->after('type');
            });
        }

        if (! Schema::hasColumn('service_cycle_communications', 'supersedes_communication_id')) {
            Schema::table('service_cycle_communications', function (Blueprint $table): void {
                $table->foreignId('supersedes_communication_id')->nullable()->after('revision');
            });
        }

        if (! Schema::hasForeignKey('service_cycle_communications', ['supersedes_communication_id'])) {
            Schema::table('service_cycle_communications', function (Blueprint $table): void {
                $table->foreign('supersedes_communication_id', 'svc_communication_supersedes_fk')
                    ->references('id')
                    ->on('service_cycle_communications')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('service_cycle_communications', 'superseded_at')) {
            Schema::table('service_cycle_communications', function (Blueprint $table): void {
                $table->timestamp('superseded_at')->nullable()->after('delivery_note');
            });
        }

        if (! Schema::hasIndex('service_cycle_communications', 'service_cycle_communication_revision_unique')) {
            Schema::table('service_cycle_communications', function (Blueprint $table): void {
                $table->unique(['service_cycle_commitment_id', 'type', 'revision'], 'service_cycle_communication_revision_unique');
            });
        }

        if (! Schema::hasIndex('service_cycle_communications', 'service_cycle_communication_current_idx')) {
            Schema::table('service_cycle_communications', function (Blueprint $table): void {
                $table->index(['service_cycle_commitment_id', 'type', 'superseded_at'], 'service_cycle_communication_current_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::table('service_cycle_communications', function (Blueprint $table): void {
            $table->dropIndex('service_cycle_communication_current_idx');
            $table->dropUnique('service_cycle_communication_revision_unique');
            $table->dropForeign(['supersedes_communication_id']);
            $table->dropColumn(['revision', 'supersedes_communication_id', 'superseded_at']);
            $table->unique(['service_cycle_commitment_id', 'type'], 'service_cycle_communication_unique');
        });
        Schema::dropIfExists('service_cycle_schedule_revisions');
        Schema::table('service_cycle_commitments', function (Blueprint $table): void {
            $table->dropIndex('service_cycle_schedule_status_period_idx');
            $table->dropConstrainedForeignId('schedule_confirmed_by');
            $table->dropConstrainedForeignId('materialized_by');
            $table->dropConstrainedForeignId('work_order_id');
            $table->dropConstrainedForeignId('service_ticket_id');
            $table->dropColumn(['technical_snapshot', 'schedule_status', 'materialized_at', 'schedule_confirmed_at']);
        });
    }
};
