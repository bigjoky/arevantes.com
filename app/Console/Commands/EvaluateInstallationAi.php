<?php

namespace App\Console\Commands;

use App\Ai\Agents\InstallationAdvisor;
use App\Ai\Evaluation\InstallationBriefingCases;
use Illuminate\Console\Command;
use Throwable;

class EvaluateInstallationAi extends Command
{
    protected $signature = 'ai:evaluate-installations {--limit=32}';

    protected $description = 'Evaluate structured installation briefings against anonymized cases';

    public function handle(): int
    {
        $cases = array_slice(InstallationBriefingCases::all(), 0, (int) $this->option('limit'));
        $passed = 0;
        foreach ($cases as $case) {
            try {
                $result = InstallationAdvisor::make()->prompt($case['prompt'])->toArray();
                $valid = collect([
                    'summary',
                    'health_assessment',
                    'commercial_angle',
                    'missing_information',
                    'risks',
                    'next_actions',
                    'action_items',
                    'confidence',
                ])->every(fn ($key) => array_key_exists($key, $result));
            } catch (Throwable) {
                $valid = false;
            }
            $passed += (int) $valid;
            $this->line(($valid ? 'PASS ' : 'FAIL ').$case['name']);
        }
        $this->info("{$passed}/".count($cases).' structured responses passed.');

        return $passed === count($cases) ? self::SUCCESS : self::FAILURE;
    }
}
