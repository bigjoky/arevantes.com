<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_order_attachments', function (Blueprint $table) {
            $table->unsignedInteger('document_version')->default(1)->after('evidence_key');
            $table->string('publication_status', 24)->default('internal')->after('customer_visible')->index();
            $table->foreignId('reviewed_by')->nullable()->after('published_at')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->foreignId('published_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $table->foreignId('supersedes_attachment_id')->nullable()->after('published_by')->constrained('work_order_attachments')->nullOnDelete();
            $table->index(['work_order_id', 'evidence_key', 'publication_status'], 'work_order_document_publication_index');
        });

        $versions = [];
        $latestPublished = [];
        foreach (DB::table('work_order_attachments')->join('work_orders', 'work_orders.id', '=', 'work_order_attachments.work_order_id')->where('work_order_attachments.kind', 'document')->orderBy('work_order_attachments.id')->get(['work_order_attachments.*', 'work_orders.installation_id']) as $attachment) {
            $key = $attachment->evidence_key ? $attachment->installation_id.'|'.$attachment->evidence_key : 'attachment|'.$attachment->id;
            $version = ($versions[$key] ?? 0) + 1;
            $versions[$key] = $version;
            $published = (bool) $attachment->customer_visible && $attachment->published_at !== null;
            $updates = [
                'document_version' => $version,
                'publication_status' => $published ? 'published' : 'internal',
            ];

            if ($published && isset($latestPublished[$key])) {
                DB::table('work_order_attachments')->where('id', $latestPublished[$key])->update([
                    'publication_status' => 'replaced',
                    'customer_visible' => false,
                ]);
                $updates['supersedes_attachment_id'] = $latestPublished[$key];
            }

            DB::table('work_order_attachments')->where('id', $attachment->id)->update($updates);
            if ($published) {
                $latestPublished[$key] = $attachment->id;
            }
        }

        if (Schema::hasTable('permissions')) {
            $now = now();
            DB::table('permissions')->insertOrIgnore([
                'key' => 'publish-customer-documents',
                'name' => 'Publicar documentación para clientes',
                'category' => 'technical',
                'is_sensitive' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $permissionId = DB::table('permissions')->where('key', 'publish-customer-documents')->value('id');
            $administratorId = DB::table('permission_profiles')->where('slug', 'administrator')->value('id');
            if ($permissionId && $administratorId) {
                DB::table('permission_profile_permission')->insertOrIgnore([
                    'permission_profile_id' => $administratorId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            $permissionId = DB::table('permissions')->where('key', 'publish-customer-documents')->value('id');
            if ($permissionId) {
                DB::table('permission_profile_permission')->where('permission_id', $permissionId)->delete();
                DB::table('user_permission_overrides')->where('permission_id', $permissionId)->delete();
                DB::table('permissions')->where('id', $permissionId)->delete();
            }
        }

        Schema::table('work_order_attachments', function (Blueprint $table) {
            $table->dropIndex('work_order_document_publication_index');
            $table->dropConstrainedForeignId('supersedes_attachment_id');
            $table->dropConstrainedForeignId('published_by');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['document_version', 'publication_status', 'reviewed_at']);
        });
    }
};
