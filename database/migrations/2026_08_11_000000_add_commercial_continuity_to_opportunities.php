<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->string('commercial_kind', 24)->default('new_business');
            $table->foreignId('parent_opportunity_id')->nullable()->constrained('opportunities')->nullOnDelete();
            $table->index('parent_opportunity_id');
            $table->string('recurrence_interval', 16)->nullable();
            $table->date('renewal_due_on')->nullable();
            $table->index(['commercial_kind', 'renewal_due_on'], 'opportunities_kind_renewal_due_idx');
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->dropForeign(['parent_opportunity_id']);
            $table->dropIndex(['parent_opportunity_id']);
            $table->dropIndex('opportunities_kind_renewal_due_idx');
            $table->dropColumn([
                'commercial_kind',
                'parent_opportunity_id',
                'recurrence_interval',
                'renewal_due_on',
            ]);
        });
    }
};
