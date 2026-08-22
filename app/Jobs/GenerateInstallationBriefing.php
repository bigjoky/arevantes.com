<?php

namespace App\Jobs;

use App\Ai\Agents\InstallationAdvisor;
use App\Ai\Agents\InstallationSnapshot;
use App\Models\AiGeneration;
use App\Services\Ai\AiActionProposalService;
use App\Services\Ai\InstallationBriefingPrompt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateInstallationBriefing implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 240;

    public function __construct(public int $generationId) {}

    public function handle(InstallationBriefingPrompt $promptBuilder, AiActionProposalService $actionProposals): void
    {
        $generation = AiGeneration::with([
            'installation.customer',
            'installation.monitoringAlerts',
            'installation.monitoringReadings',
            'installation.activities.assignedUser',
            'installation.quotes',
            'user',
        ])->findOrFail($this->generationId);
        $generation->update(['status' => 'processing', 'started_at' => now(), 'error' => null]);
        $started = hrtime(true);

        try {
            $built = $promptBuilder->build($generation->installation, $generation->mode, $generation->user);
            $generation->update(['prompt' => $built['prompt'], 'sources' => $built['sources']]);
            $response = ($generation->mode === 'quick' ? InstallationSnapshot::make() : InstallationAdvisor::make())
                ->prompt($built['prompt'], timeout: $generation->mode === 'quick' ? 90 : 180);
            $structured = method_exists($response, 'toArray')
                ? $response->toArray()
                : [
                    'summary' => trim($response->text),
                    'health_assessment' => 'No health assessment was returned.',
                    'commercial_angle' => $generation->mode === 'quick' ? null : 'No commercial angle was returned.',
                    'missing_information' => [],
                    'risks' => [],
                    'next_actions' => [],
                    'action_items' => [],
                    'confidence' => 0.5,
                ];

            $generation->update([
                'status' => 'completed',
                'provider' => $response->meta->provider,
                'model' => $response->meta->model,
                'structured_response' => $structured,
                'raw_response' => $response->text,
                'usage' => $response->usage->toArray(),
                'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
                'completed_at' => now(),
            ]);
            $actionProposals->proposeFor($generation->refresh());
        } catch (Throwable $exception) {
            $willRetry = $this->attempts() < $this->tries;
            $generation->update([
                'status' => $willRetry ? 'queued' : 'failed',
                'error' => $exception->getMessage(),
                'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
                'completed_at' => $willRetry ? null : now(),
            ]);
            throw $exception;
        }
    }
}
