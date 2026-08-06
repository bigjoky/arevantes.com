<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('approval_status', 20)->default('not_required')->after('status')->index();
            $table->foreignId('approved_by')->nullable()->after('approval_status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('approval_notes')->nullable()->after('approved_at');
            $table->decimal('expected_margin_amount', 12, 2)->nullable()->after('tax_amount');
            $table->decimal('actual_margin_amount', 12, 2)->nullable()->after('expected_margin_amount');
        });

        Schema::create('quote_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('amount', 12, 2);
            $table->decimal('expected_margin_amount', 12, 2)->nullable();
            $table->json('lines');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['quote_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_versions');
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropIndex(['approval_status']);
            $table->dropColumn(['approval_status', 'approved_at', 'approval_notes', 'expected_margin_amount', 'actual_margin_amount']);
        });
    }
};
