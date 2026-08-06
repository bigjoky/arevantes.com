<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->string('lifecycle_status', 30)->nullable()->default('active')->after('customer_type')->index();
            $table->string('preferred_contact_channel', 30)->nullable()->after('preferred_language');
            $table->boolean('do_not_contact')->default(false)->after('preferred_contact_channel');
            $table->string('data_source')->nullable()->after('lead_source');
        });

        Schema::create('customer_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('position')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('preferred_channel', 30)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['person_id', 'is_primary']);
            $table->index('email');
            $table->index('phone');
        });

        Schema::create('crm_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('color', 20)->default('slate');
            $table->timestamps();
        });

        Schema::create('crm_tag_person', function (Blueprint $table) {
            $table->foreignId('crm_tag_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['crm_tag_id', 'person_id']);
        });

        Schema::create('customer_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 30);
            $table->string('status', 30);
            $table->string('lawful_basis', 40);
            $table->string('source')->nullable();
            $table->timestamp('captured_at');
            $table->timestamp('withdrawn_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['person_id', 'channel', 'captured_at']);
        });

        Schema::create('saved_crm_filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 30);
            $table->string('name');
            $table->json('filters');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'scope', 'name']);
            $table->index(['user_id', 'scope', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_crm_filters');
        Schema::dropIfExists('customer_consents');
        Schema::dropIfExists('crm_tag_person');
        Schema::dropIfExists('crm_tags');
        Schema::dropIfExists('customer_contacts');

        Schema::table('people', function (Blueprint $table) {
            $table->dropIndex(['lifecycle_status']);
            $table->dropColumn(['lifecycle_status', 'preferred_contact_channel', 'do_not_contact', 'data_source']);
        });
    }
};
