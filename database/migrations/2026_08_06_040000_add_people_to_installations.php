<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installation_person', function (Blueprint $table) {
            $table->foreignId('installation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('role', 30)->default('co_owner');
            $table->decimal('ownership_percentage', 5, 2)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->primary(['installation_id', 'person_id']);
            $table->index(['person_id', 'role']);
        });

        $now = now();
        DB::table('installations')
            ->whereNotNull('person_id')
            ->orderBy('id')
            ->chunkById(500, function ($installations) use ($now): void {
                DB::table('installation_person')->insertOrIgnore(
                    $installations->map(fn ($installation) => [
                        'installation_id' => $installation->id,
                        'person_id' => $installation->person_id,
                        'role' => 'owner',
                        'ownership_percentage' => null,
                        'is_primary' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all(),
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('installation_person');
    }
};
