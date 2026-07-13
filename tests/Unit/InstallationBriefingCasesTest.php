<?php

namespace Tests\Unit;

use App\Ai\Evaluation\InstallationBriefingCases;
use PHPUnit\Framework\TestCase;

class InstallationBriefingCasesTest extends TestCase
{
    public function test_the_evaluation_suite_contains_at_least_thirty_anonymized_cases(): void
    {
        $cases = InstallationBriefingCases::all();

        $this->assertGreaterThanOrEqual(30, count($cases));
        $this->assertCount(count($cases), array_unique(array_column($cases, 'name')));
    }
}
