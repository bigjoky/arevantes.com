<?php

use App\Support\Database\BackendTableSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('service_type', 40)->nullable();
            $table->json('lines');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        BackendTableSecurity::restrict('quote_templates');
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_templates');
    }
};
