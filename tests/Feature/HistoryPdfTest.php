<?php

namespace Tests\Feature;

use App\Models\SensorReading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HistoryPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_over_5000_matches_are_rejected_without_truncation(): void
    {
        $this->freezeTime();
        $rows = SensorReading::factory()->count(5001)->make([
            'node_id' => '1', 'sensor_id' => 'temperature', 'value' => 12345.75,
            'recorded_at' => now()->subHour(),
        ])->map(fn (SensorReading $reading): array => $reading->getAttributes());
        foreach ($rows->chunk(500) as $chunk) {
            SensorReading::query()->insert($chunk->all());
        }
        $this->actingAs(User::factory()->create());
        $queries = [];
        DB::listen(function ($event) use (&$queries): void {
            if (str_contains($event->sql, 'sensor_readings')) {
                $queries[] = $event->sql;
            }
        });

        $response = $this->get('/history/export');

        $response->assertUnprocessable()->assertViewIs('history.export')
            ->assertSeeText('Laporan melebihi batas 5000 pembacaan. Persempit filter node, parameter, atau tanggal lalu coba lagi.')
            ->assertSeeText('Kembali ke riwayat')->assertDontSee('id="print-report"', false)
            ->assertDontSee('12345.75')->assertDontSeeText('Total:')
            ->assertViewHas('readings', fn ($readings): bool => $readings->isEmpty());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('limit 5001', strtolower($queries[0]));
    }

    public function test_exactly_5000_filtered_matches_are_complete_even_when_other_rows_exist(): void
    {
        $this->travelTo(Carbon::parse('2026-09-17 12:00:00', 'UTC'));
        $rows = SensorReading::factory()->count(5000)->make([
            'node_id' => '2', 'sensor_id' => 'temperature', 'value' => 0,
            'recorded_at' => '2026-09-16 17:00:00',
        ])->map(fn (SensorReading $reading): array => $reading->getAttributes());
        foreach ($rows->chunk(500) as $chunk) {
            SensorReading::query()->insert($chunk->all());
        }
        foreach ([
            ['node_id' => '1', 'sensor_id' => 'temperature', 'recorded_at' => '2026-09-16 17:00:00'],
            ['node_id' => '2', 'sensor_id' => 'air_humidity', 'recorded_at' => '2026-09-16 17:00:00'],
            ['node_id' => '2', 'sensor_id' => 'temperature', 'recorded_at' => '2026-09-16 16:59:59'],
        ] as $outside) {
            SensorReading::factory()->create($outside);
        }
        $filters = ['node' => '2', 'sensor' => 'temperature', 'from' => '2026-09-17', 'to' => '2026-09-17', 'timezone' => 'Asia/Jakarta', 'page' => 2];

        $response = $this->actingAs(User::factory()->create())->get(route('history.export', $filters));

        $response->assertOk()->assertSeeText('Total: 5000 pembacaan')->assertSee('id="print-report"', false);
        $this->assertCount(5000, $response->viewData('readings'));
        $this->assertSame(range(5000, 1), $response->viewData('readings')->pluck('id')->all());
        $this->assertSame(5000, substr_count($response->getContent(), '>2026-09-17 00:00:00</time>'));
    }

    public function test_export_is_a_standalone_print_ready_report_with_applied_filters(): void
    {
        $this->travelTo(Carbon::parse('2026-09-17 12:00:00', 'UTC'));
        SensorReading::factory()->create([
            'node_id' => '2', 'sensor_id' => 'temperature', 'value' => -2.5,
            'recorded_at' => '2026-09-16 17:00:00',
        ]);
        $filters = ['node' => '2', 'sensor' => 'temperature', 'from' => '2026-09-17', 'to' => '2026-09-17', 'timezone' => 'Asia/Jakarta'];

        $response = $this->actingAs(User::factory()->create())->get(route('history.export', $filters));

        $response->assertOk()->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertViewIs('history.export')->assertViewHas('filters', $filters)
            ->assertSeeText('Rebung Pintar')->assertSeeText('Riwayat pembacaan')
            ->assertSeeText('Node 2')->assertSeeText('Suhu udara')->assertSeeText('2026-09-17')
            ->assertSeeText('Waktu (WIB)')->assertSeeText('2026-09-17 00:00:00')
            ->assertSeeText('-2.5')->assertSeeText('°C')->assertSeeText('Total: 1 pembacaan')
            ->assertSeeText('Cetak / Simpan PDF')->assertSeeText('Ctrl+P')
            ->assertSee('id="print-report" type="button"', false)
            ->assertSee('window.print()', false)->assertSee('@media print', false)
            ->assertSee('size: A4 landscape', false)->assertSee('display: table-header-group', false)
            ->assertSee('break-inside: avoid', false)
            ->assertDontSee('onclick=', false)->assertDontSee('cdn.', false);
        $this->assertFalse($response->headers->has('Content-Disposition'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertSame('2026-09-16 17:00:00', $response->viewData('readings')->first()->recorded_at->format('Y-m-d H:i:s'));
    }
}
