<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_contact_receipts', function (Blueprint $table): void {
            $table->id();
            $table->string('fingerprint', 64)->index();
            $table->text('payload')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('expires_at')->index();
            $table->timestampsTz();
            $table->index(['processed_at', 'created_at']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE website_contact_receipts ENABLE ROW LEVEL SECURITY');
            DB::statement('REVOKE ALL PRIVILEGES ON TABLE website_contact_receipts FROM PUBLIC');
            DB::statement(<<<'SQL'
                DO $$
                DECLARE api_role text; sequence_name text;
                BEGIN
                    sequence_name := pg_get_serial_sequence('website_contact_receipts', 'id');
                    IF sequence_name IS NOT NULL THEN
                        EXECUTE format('REVOKE ALL PRIVILEGES ON SEQUENCE %s FROM PUBLIC', sequence_name::regclass);
                    END IF;
                    FOREACH api_role IN ARRAY ARRAY['anon', 'authenticated', 'service_role'] LOOP
                        IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = api_role) THEN
                            EXECUTE format('REVOKE ALL PRIVILEGES ON TABLE website_contact_receipts FROM %I', api_role);
                            IF sequence_name IS NOT NULL THEN
                                EXECUTE format('REVOKE ALL PRIVILEGES ON SEQUENCE %s FROM %I', sequence_name::regclass, api_role);
                            END IF;
                        END IF;
                    END LOOP;
                END $$
                SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('website_contact_receipts');
    }
};
