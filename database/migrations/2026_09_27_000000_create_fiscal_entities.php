<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_entities', function (Blueprint $table): void {
            $table->id();
            $table->char('country', 2);
            $table->string('tax_id', 32);
            $table->string('tax_id_key', 32);
            $table->string('legal_name');
            $table->json('trade_names');
            $table->json('addresses');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['country', 'tax_id_key']);
        });
        Schema::table('expenses', fn (Blueprint $table) => $table->char('issuer_country', 2)->nullable());
    }

    public function down(): void
    {
        Schema::table('expenses', fn (Blueprint $table) => $table->dropColumn('issuer_country'));
        Schema::dropIfExists('fiscal_entities');
    }
};
