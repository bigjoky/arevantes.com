<?php

namespace Tests\Feature;

use App\Models\Installation;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Work\WorkOrderEvidenceRequirements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkOrderEvidenceRequirementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_evidence_counts_do_not_inherit_attachment_sorting_and_match_loaded_relations(): void
    {
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'scheduled', 'site_address_line_1' => 'Local test']);
        $order = WorkOrder::create([
            'installation_id' => $installation->id, 'number' => 'WO-EVIDENCE-TEST',
            'title' => 'Evidence regression', 'kind' => 'installation', 'status' => 'in_progress',
            'required_evidence' => [
                ['key' => 'overview', 'label' => 'Overview', 'min_count' => 2],
                ['key' => 'result', 'label' => 'Result'],
            ],
        ]);
        $user = User::factory()->create(['role' => 'commercial']);
        foreach (['overview', 'overview', null] as $index => $key) {
            $order->attachments()->create([
                'uploaded_by' => $user->id, 'kind' => 'photo', 'evidence_key' => $key,
                'original_name' => "photo-{$index}.jpg", 'path' => "test/photo-{$index}.jpg",
                'mime_type' => 'image/jpeg', 'size' => 10,
            ]);
        }

        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $progress = app(WorkOrderEvidenceRequirements::class)->progress($order->fresh());
            $aggregate = collect(DB::getQueryLog())->first(fn (array $query): bool => str_contains(strtolower($query['query']), 'group by'));
        } finally {
            DB::disableQueryLog();
        }
        $this->assertNotNull($aggregate);
        $this->assertStringNotContainsString('order by', strtolower($aggregate['query']));
        $this->assertSame([2, 0], array_column($progress, 'captured_count'));
        $this->assertSame([true, false], array_column($progress, 'is_complete'));
        $this->assertSame($progress, app(WorkOrderEvidenceRequirements::class)->progress($order->fresh()->load('attachments')));

        $this->actingAs($user)->from('/work-orders/manage?work_order='.$order->id)
            ->withHeader('X-Inertia', 'true')
            ->patch(route('work-orders.update', $order), ['status' => 'completed', 'completion_notes' => 'Ready'])
            ->assertRedirect()->assertSessionHasErrors('status');
        $this->assertSame('in_progress', $order->refresh()->status);
    }
}
