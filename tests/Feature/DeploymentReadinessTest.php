<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeploymentReadinessTest extends TestCase
{
    public function test_routes_can_be_cached_for_production(): void
    {
        try {
            $this->artisan('route:cache')->assertSuccessful();
        } finally {
            $this->artisan('route:clear')->assertSuccessful();
        }
    }
}
