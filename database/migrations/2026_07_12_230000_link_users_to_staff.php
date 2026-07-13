<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('person_id')->nullable()->unique()->after('id')->constrained('people')->nullOnDelete();
        });

        DB::table('users')->orderBy('id')->get()->each(function ($user): void {
            $personId = DB::table('people')->where('category', 'staff')->where('email', $user->email)->value('id');
            if (! $personId) {
                $parts = preg_split('/\s+/', trim($user->name), 2);
                $personId = DB::table('people')->insertGetId([
                    'category' => 'staff',
                    'first_name' => $parts[0] ?: $user->name,
                    'last_name' => $parts[1] ?? null,
                    'email' => $user->email,
                    'staff_role' => 'CRM user',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            DB::table('users')->where('id', $user->id)->update(['person_id' => $personId]);
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('person_id'));
    }
};
