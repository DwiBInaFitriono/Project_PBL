<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitoringFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_and_node_restore_sensor_and_hours_from_url(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create());
        foreach (['/dashboard', '/nodes/2'] as $path) {
            $this->get($path.'?sensor=soil_moisture&hours=6')->assertOk()
                ->assertViewHas('activeSensor', 'soil_moisture')
                ->assertViewHas('activeHours', 6)
                ->assertSee('data-select-range="6" aria-pressed="true"', false);
        }
    }

    public function test_invalid_filter_values_are_rejected(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create());
        foreach (['/dashboard', '/nodes/1'] as $path) {
            foreach (['sensor=unknown', 'sensor[]=temperature', 'hours=5', 'hours[]=6', 'hours=01'] as $query) {
                $this->get($path.'?'.$query)->assertNotFound();
            }
        }
    }
}
