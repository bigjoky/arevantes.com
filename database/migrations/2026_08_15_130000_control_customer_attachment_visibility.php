<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_order_attachments', function (Blueprint $table): void {
            $table->boolean('customer_visible')->default(false)->after('kind');
            $table->timestamp('published_at')->nullable()->after('customer_visible');
            $table->index(['customer_visible', 'kind', 'published_at'], 'work_order_attachments_customer_visibility_idx');
        });
    }

    public function down(): void
    {
        Schema::table('work_order_attachments', function (Blueprint $table): void {
            $table->dropIndex('work_order_attachments_customer_visibility_idx');
            $table->dropColumn(['customer_visible', 'published_at']);
        });
    }
};
