<?php

namespace Tests\Feature;

use App\Services\DependencyProbe;
use Mockery\MockInterface;
use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_liveness_is_independent_of_dependencies(): void
    {
        $this->getJson('/api/v1/health/live')
            ->assertOk()
            ->assertExactJson(['status' => 'live'])
            ->assertHeader('X-Request-ID');
    }

    public function test_readiness_reports_ready_when_dependencies_are_available(): void
    {
        $this->mock(DependencyProbe::class, function (MockInterface $mock): void {
            $mock->shouldReceive('ready')->once()->andReturnTrue();
        });

        $this->getJson('/api/v1/health/ready')
            ->assertOk()
            ->assertExactJson(['status' => 'ready']);
    }

    public function test_readiness_is_sanitized_when_a_dependency_is_unavailable(): void
    {
        $this->mock(DependencyProbe::class, function (MockInterface $mock): void {
            $mock->shouldReceive('ready')->once()->andReturnFalse();
        });

        $this->getJson('/api/v1/health/ready')
            ->assertStatus(503)
            ->assertExactJson(['status' => 'not_ready']);
    }

    public function test_valid_request_id_is_reused(): void
    {
        $this->withHeader('X-Request-ID', 'cvortex-test-42')
            ->getJson('/api/v1/health/live')
            ->assertHeader('X-Request-ID', 'cvortex-test-42');
    }

    public function test_invalid_request_id_is_replaced(): void
    {
        $response = $this->withHeader('X-Request-ID', "invalid\nvalue")
            ->getJson('/api/v1/health/live');

        $response->assertOk();
        $this->assertNotSame("invalid\nvalue", $response->headers->get('X-Request-ID'));
    }
}
