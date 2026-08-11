<?php

namespace Tests\Feature;

use App\Models\Installation;
use App\Models\WorkOrder;
use App\Models\WorkOrderTemplate;
use App\Services\Work\WorkOrderWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WorkOrderWorkflowMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_orders_receive_an_immutable_workflow_snapshot(): void
    {
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'approved', 'site_address_line_1' => 'Calle Migración 1']);
        $template = WorkOrderTemplate::create([
            'name' => 'Legacy controlled workflow',
            'kind' => 'maintenance',
            'title' => 'Legacy maintenance',
            'estimated_duration_minutes' => 60,
            'required_skills' => [],
            'checklist' => [],
            'allowed_transitions' => [
                'planned' => ['blocked'],
                'blocked' => ['planned'],
                'in_progress' => [],
                'completed' => [],
                'cancelled' => [],
            ],
            'is_active' => true,
        ]);
        $templated = WorkOrder::create(['installation_id' => $installation->id, 'work_order_template_id' => $template->id, 'number' => 'WO-LEGACY-1', 'title' => 'Legacy templated']);
        $standard = WorkOrder::create(['installation_id' => $installation->id, 'number' => 'WO-LEGACY-2', 'title' => 'Legacy standard']);
        $migration = require database_path('migrations/2026_08_11_121000_snapshot_work_order_workflows.php');

        $migration->down();
        $this->assertFalse(Schema::hasColumn('work_orders', 'allowed_transitions_snapshot'));
        $migration->up();

        $this->assertSame(['blocked'], $templated->refresh()->allowed_transitions_snapshot['planned']);
        $this->assertSame(WorkOrderWorkflow::DEFAULT_TRANSITIONS, $standard->refresh()->allowed_transitions_snapshot);
    }
}
