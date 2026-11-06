<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_graph_test_authorizations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('authorized_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('executed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('authorized');
            $table->timestampTz('attempted_at')->nullable();
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('receipt_confirmed_at')->nullable();
            $table->foreignId('receipt_confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('error_class')->nullable();
            $table->timestampsTz();
        });
        // One consumed attempt in the entire initial campaign, regardless of UUID.
        DB::statement('CREATE UNIQUE INDEX website_graph_test_one_attempt ON website_graph_test_authorizations ((1)) WHERE attempted_at IS NOT NULL');
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE website_graph_test_authorizations ADD CONSTRAINT website_graph_test_attempt_state CHECK ((status = 'authorized' AND attempted_at IS NULL) OR (status IN ('sending', 'accepted', 'uncertain') AND attempted_at IS NOT NULL))");
            DB::statement('ALTER TABLE website_graph_test_authorizations ENABLE ROW LEVEL SECURITY');
            DB::statement('REVOKE ALL PRIVILEGES ON TABLE website_graph_test_authorizations FROM PUBLIC');
            DB::statement(<<<'SQL'
                DO $$ DECLARE r text; BEGIN
                  FOREACH r IN ARRAY ARRAY['anon', 'authenticated', 'service_role'] LOOP
                    IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = r) THEN
                      EXECUTE format('REVOKE ALL PRIVILEGES ON TABLE website_graph_test_authorizations FROM %I', r);
                    END IF;
                  END LOOP;
                END $$
                SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('website_graph_test_authorizations');
    }
};
