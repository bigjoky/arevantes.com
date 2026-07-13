<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->foreignId('person_id')->nullable()->after('installation_id')->constrained('people')->nullOnDelete();
            $table->foreignId('document_series_id')->nullable()->after('created_by')->constrained('document_series')->nullOnDelete();
            $table->unsignedInteger('version')->default(1)->after('number');
            $table->date('issue_date')->nullable()->after('status');
            $table->decimal('subtotal', 12, 2)->default(0)->after('amount');
            $table->decimal('discount_amount', 12, 2)->default(0)->after('subtotal');
            $table->decimal('tax_amount', 12, 2)->default(0)->after('discount_amount');
            $table->json('customer_snapshot')->nullable()->after('notes');
        });

        DB::table('quotes')->orderBy('id')->each(function ($quote) {
            $personId = DB::table('installations')->where('id', $quote->installation_id)->value('person_id');
            DB::table('quotes')->where('id', $quote->id)->update([
                'person_id' => $personId,
                'issue_date' => substr($quote->created_at, 0, 10),
                'subtotal' => $quote->amount,
            ]);
        });

        Schema::create('quote_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tax_rate_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('position')->default(1);
            $table->text('description');
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2);
            $table->decimal('total', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_lines');
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('person_id');
            $table->dropConstrainedForeignId('document_series_id');
            $table->dropColumn(['version', 'issue_date', 'subtotal', 'discount_amount', 'tax_amount', 'customer_snapshot']);
        });
    }
};
