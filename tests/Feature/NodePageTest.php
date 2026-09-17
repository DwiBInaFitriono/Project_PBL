<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NodePageTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('nodeIds')]
    public function test_each_node_has_a_dedicated_protected_page(string $id): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create())->get('/nodes/'.$id)
            ->assertOk()
            ->assertViewIs('nodes.show')
            ->assertSeeText('Monitoring Node '.$id)
            ->assertViewHas('node', fn (array $node): bool => $node['id'] === $id && count($node['sensors']) === 3)
            ->assertViewHas('monitoring', fn (array $data): bool => count($data['nodes']) === 1 && $data['nodes'][0]['id'] === $id);
    }

    public function test_node_sensor_link_selects_the_requested_parameter(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create())->get('/nodes/2?sensor=soil_moisture')
            ->assertOk()
            ->assertViewHas('activeSensor', 'soil_moisture')
            ->assertViewHas('monitoring', fn (array $data): bool => $data['activeSensor'] === 'soil_moisture');
    }

    public function test_unknown_node_or_sensor_returns_not_found(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['/nodes/3', '/nodes/01', '/nodes/abc', '/nodes/1?sensor=unknown', '/nodes/1?sensor[]=temperature'] as $path) {
            $this->get($path)->assertNotFound();
        }
    }

    public function test_guests_cannot_access_node_pages(): void
    {
        $this->get('/nodes/1')->assertRedirect('/login');
        $this->get('/nodes/2')->assertRedirect('/login');
    }

    public static function nodeIds(): array
    {
        return [['1'], ['2']];
    }
}
