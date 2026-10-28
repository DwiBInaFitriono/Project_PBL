<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_node_has_its_configured_hydroponic_sensor_parameters_with_no_real_values(): void
    {
        $this->withoutVite();

        $response = $this->actingAs(User::factory()->create())->get('/dashboard');

        $response->assertOk()
            ->assertSeeText('pH nutrisi')
            ->assertDontSeeText('TDS nutrisi')
            ->assertSeeText('Suhu air')
            ->assertSeeText('Level tandon')
            ->assertSeeText('Suhu udara')
            ->assertSeeText('Kelembapan udara')
            ->assertSeeText('Intensitas cahaya')
            ->assertSeeText('Debit air')
            ->assertSeeText('Parameter usulan')
            ->assertViewHas('nodes', function (array $nodes): bool {
                if (count($nodes[0]['sensors'] ?? []) !== 3 || count($nodes[1]['sensors'] ?? []) !== 4) {
                    return false;
                }

                foreach ($nodes as $node) {
                    foreach ($node['sensors'] as $sensor) {
                        if ($sensor['value'] !== null || $sensor['readings'] !== []) {
                            return false;
                        }
                    }
                }

                return count($nodes) === 2;
            });
    }

    public function test_dashboard_contains_no_trial_mode_or_fabricated_data(): void
    {
        $this->withoutVite();

        $this->actingAs(User::factory()->create())->get('/dashboard')
            ->assertOk()
            ->assertDontSee('data-demo-toggle', false)
            ->assertDontSeeText('Lihat simulasi grafik')
            ->assertDontSeeText('SIMULASI')
            ->assertSeeText('Grafik menunggu data sensor');
    }

    public function test_dashboard_shows_both_nodes_without_fabricated_sensor_readings(): void
    {
        $this->withoutVite();

        $this->actingAs(User::factory()->create())->get('/dashboard')
            ->assertOk()
            ->assertSeeText('Ringkasan monitoring')
            ->assertSeeText('Node Nutrisi')
            ->assertSeeText('Node Iklim')
            ->assertSeeText('Menunggu integrasi')
            ->assertSeeText('Belum ada pembacaan')
            ->assertSeeText('Riwayat pembacaan')
            ->assertViewHas('nodes', fn (array $nodes): bool => count($nodes) === 2
                && $nodes[0]['id'] === '1' && $nodes[1]['id'] === '2'
                && $nodes[0]['last_reading'] === null && $nodes[1]['last_reading'] === null);
    }
}
