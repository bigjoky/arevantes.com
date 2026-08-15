<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            $table->string('phone_normalized', 24)->nullable()->after('phone');
        });

        DB::table('people')
            ->select(['id', 'phone'])
            ->whereNotNull('phone')
            ->orderBy('id')
            ->chunkById(500, function ($people): void {
                foreach ($people as $person) {
                    $digits = preg_replace('/\D+/', '', trim((string) $person->phone)) ?? '';

                    if (str_starts_with($digits, '00')) {
                        $digits = substr($digits, 2);
                    } elseif (strlen($digits) === 9) {
                        $digits = '34'.$digits;
                    }

                    DB::table('people')->where('id', $person->id)->update([
                        'phone_normalized' => $digits !== '' ? $digits : null,
                    ]);
                }
            });

        Schema::table('people', function (Blueprint $table): void {
            $table->index(
                ['category', 'phone_normalized', 'deleted_at'],
                'people_customer_mobile_lookup_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            $table->dropIndex('people_customer_mobile_lookup_idx');
            $table->dropColumn('phone_normalized');
        });
    }
};
