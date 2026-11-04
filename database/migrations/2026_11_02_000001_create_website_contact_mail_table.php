<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_contact_mail', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('receipt_id')->constrained('website_contact_receipts')->restrictOnDelete();
            $table->foreignId('opportunity_id')->constrained()->restrictOnDelete();
            $table->string('kind', 20);
            $table->string('recipient_hash', 64);
            $table->text('recipient');
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestampTz('attempted_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->string('error_class')->nullable();
            $table->timestampsTz();
            $table->unique(['receipt_id', 'kind', 'recipient_hash']);
            $table->index(['status', 'id']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE website_contact_mail ENABLE ROW LEVEL SECURITY');
            DB::statement('REVOKE ALL PRIVILEGES ON TABLE website_contact_mail FROM PUBLIC');
            DB::statement(<<<'SQL'
                DO $$
                DECLARE api_role text; sequence_name text;
                BEGIN
                    sequence_name := pg_get_serial_sequence('website_contact_mail', 'id');
                    IF sequence_name IS NOT NULL THEN
                        EXECUTE format('REVOKE ALL PRIVILEGES ON SEQUENCE %s FROM PUBLIC', sequence_name::regclass);
                    END IF;
                    FOREACH api_role IN ARRAY ARRAY['anon', 'authenticated', 'service_role'] LOOP
                        IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = api_role) THEN
                            EXECUTE format('REVOKE ALL PRIVILEGES ON TABLE website_contact_mail FROM %I', api_role);
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
        Schema::dropIfExists('website_contact_mail');
    }
};
