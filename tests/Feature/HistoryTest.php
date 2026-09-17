<?php

namespace Tests\Feature;

use App\Http\Controllers\HistoryController;
use App\Models\SensorReading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class HistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        foreach (['index' => '/history', 'export' => '/history/export'] as $action => $path) {
            if (! Route::has('history.'.$action)) {
                Route::middleware(['web', 'auth'])->get($path, [HistoryController::class, $action])->name('history.'.$action);
            }
        }
        Route::getRoutes()->refreshNameLookups();
    }

    public function test_both_roles_can_read_history_and_export(): void
    {
        foreach ([User::factory()->create(), User::factory()->operator()->create()] as $user) {
            $this->actingAs($user)->get('/history')->assertOk();
            $this->get('/history/export')->assertOk()->assertViewIs('history.export');
        }
    }

    public function test_history_and_export_exclude_invalid_values_and_unknown_channels(): void
    {
        $this->freezeTime();
        SensorReading::factory()->create(['node_id' => '1', 'sensor_id' => 'temperature', 'value' => -2.5]);
        foreach (['NaN', 'invalid', '1e9999'] as $invalid) {
            DB::table('sensor_readings')->insert([
                'node_id' => '1', 'sensor_id' => 'temperature', 'value' => $invalid, 'recorded_at' => now(),
            ]);
        }
        SensorReading::factory()->create(['node_id' => '1', 'sensor_id' => 'unknown']);
        SensorReading::factory()->create(['node_id' => '99', 'sensor_id' => 'temperature']);
        SensorReading::factory()->create(['node_id' => '1', 'sensor_id' => 'temperature', 'recorded_at' => now()->addSecond()]);
        $this->actingAs(User::factory()->create());

        $this->get('/history')->assertOk()->assertSeeText('366 hari')
            ->assertViewHas('readings', fn ($rows): bool => $rows->total() === 1);
        $rows = $this->reportRows($this->get('/history/export')->assertOk()->getContent());
        $this->assertCount(1, $rows);
        $this->assertSame('-2.5', $rows[0][3]);
        $response = $this->get('/history/export?node=2')->assertOk()
            ->assertSeeText('Belum ada pembacaan untuk filter ini.')->assertSeeText('Total: 0 pembacaan');
        $this->assertSame([], $this->reportRows($response->getContent()));
    }

    public function test_print_report_escapes_markup_and_preserves_literal_text_and_zero(): void
    {
        $this->freezeTime();
        config([
            'monitoring.nodes.0.name' => '=HYPERLINK("bad")<script>alert("node")</script>',
            'monitoring.sensors.0.name' => "+SUM(1,2)\n<svg onload=alert(1)>",
            'monitoring.sensors.0.unit' => '@command<img src=x onerror=alert(1)>',
        ]);
        SensorReading::factory()->create(['node_id' => '1', 'sensor_id' => 'temperature', 'value' => 0]);
        $this->actingAs(User::factory()->create());

        $response = $this->get('/history/export?node=1&sensor=temperature')->assertOk()
            ->assertSee(config('monitoring.nodes.0.name'))
            ->assertSee(config('monitoring.sensors.0.name'))
            ->assertSee(config('monitoring.sensors.0.unit'))
            ->assertDontSee('<script>alert', false)->assertDontSee('<svg', false)->assertDontSee('<img', false);
        $rows = $this->reportRows($response->getContent());

        $this->assertCount(1, $rows);
        $this->assertSame(config('monitoring.nodes.0.name'), $rows[0][1]);
        $this->assertSame(config('monitoring.sensors.0.name'), $rows[0][2]);
        $this->assertSame('0', $rows[0][3]);
        $this->assertSame(config('monitoring.sensors.0.unit'), $rows[0][4]);
    }

    public function test_print_report_exports_every_match_in_history_order_without_exposing_account_data(): void
    {
        $this->get('/history/export')->assertRedirect('/login');
        $this->travelTo(Carbon::parse('2026-09-16 12:00:00', 'UTC'));
        $expected = [];
        for ($index = 0; $index < 510; $index++) {
            $reading = SensorReading::factory()->create([
                'node_id' => '2', 'sensor_id' => 'temperature', 'value' => $index,
                'recorded_at' => $index % 2 === 0 ? '2026-09-14 23:59:59' : '2026-09-13 00:00:00',
            ]);
            $expected[] = $reading;
        }
        $expected = collect($expected)->sortByDesc(fn (SensorReading $reading): string => $reading->recorded_at->format('Y-m-d H:i:s').sprintf('%010d', $reading->id))->pluck('value')->all();
        SensorReading::factory()->create(['node_id' => '1', 'sensor_id' => 'temperature', 'value' => 9999, 'recorded_at' => '2026-09-14 12:00:00']);
        SensorReading::factory()->create(['node_id' => '2', 'sensor_id' => 'air_humidity', 'value' => 9999, 'recorded_at' => '2026-09-14 12:00:00']);
        SensorReading::factory()->create(['node_id' => '2', 'sensor_id' => 'temperature', 'value' => 9999, 'recorded_at' => '2026-09-15 00:00:00']);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/history/export?node=2&sensor=temperature&from=2026-09-13&to=2026-09-14&page=2&timezone=UTC');

        $response->assertOk()->assertViewIs('history.export')->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSeeText('Total: 510 pembacaan')->assertSeeText('Waktu (UTC)');
        $content = $response->getContent();
        $this->assertStringNotContainsString($user->email, $content);
        $this->assertStringNotContainsString($user->password, $content);
        $this->assertStringNotContainsString($user->name, $content);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $rows = $this->reportRows($content);
        $this->assertCount(510, $rows);
        $this->assertSame($expected, array_map(fn (array $row): float => (float) $row[3], $rows));
        $this->assertSame(['2026-09-14 23:59:59', 'Node 2', 'Suhu udara', '508', '°C'], $rows[0]);
        $this->getJson('/history/export?node[]=2')->assertUnprocessable()->assertJsonValidationErrors('node');
    }

    /** @return array<int, array<int, string>> */
    private function reportRows(string $content): array
    {
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML($content);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new \DOMXPath($document);
        $rows = [];
        foreach ($xpath->query('//table/tbody/tr[count(td) = 5]') as $row) {
            $rows[] = array_map(fn (\DOMNode $cell): string => trim($cell->textContent), iterator_to_array($row->getElementsByTagName('td')));
        }

        return $rows;
    }

    public function test_invalid_html_filters_return_to_the_form_with_indonesian_errors(): void
    {
        $this->actingAs(User::factory()->create());
        $this->from('/history?from=invalid')->get('/history?from[]=invalid')
            ->assertRedirect('/history')->assertSessionHasErrors('from');
        $this->withCookie(session()->getName(), session()->getId())->get('/history')
            ->assertOk()->assertSeeText('Dari tanggal harus berupa tanggal valid dengan format YYYY-MM-DD.');
    }

    public function test_invalid_filters_are_rejected_without_array_or_date_errors(): void
    {
        // Validation matrix is independent of the separately tested export rate limit.
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->actingAs(User::factory()->create());
        foreach ([
            ['node=3', 'node'], ['node[]=1', 'node'], ['sensor=nope', 'sensor'], ['sensor[]=temperature', 'sensor'],
            ['from[]=2026-01-01', 'from'], ['to[]=2026-01-01', 'to'],
            ['from=2026-02-30', 'from'], ['from=not-a-date', 'from'], ['to=2026-2-1', 'to'],
            ['from=2026-09-15&to=2026-09-14', 'to'],
            ['from=2025-01-01&to=2026-01-02', 'to'],
            ['page[]=1', 'page'], ['page=-2', 'page'],
        ] as [$query, $field]) {
            foreach (['/history', '/history/export'] as $path) {
                $this->getJson($path.'?'.$query)->assertUnprocessable()->assertJsonValidationErrors($field);
            }
        }
        $this->get('/history?from=2026-09-15&to=2026-09-14')->assertRedirect()->assertSessionHasErrors('to');
        $this->get('/history?from=2025-01-01&to=2026-01-01')->assertOk();
    }

    public function test_filters_use_inclusive_utc_dates_and_retain_the_query_on_pagination(): void
    {
        $this->travelTo(Carbon::parse('2026-09-16 12:00:00', 'UTC'));
        SensorReading::factory()->count(25)->create(['node_id' => '2', 'sensor_id' => 'soil_moisture', 'recorded_at' => '2026-08-20 00:00:00']);
        $latest = SensorReading::factory()->create(['node_id' => '2', 'sensor_id' => 'soil_moisture', 'value' => 0, 'recorded_at' => '2026-08-21 23:59:59']);
        foreach ([
            ['node_id' => '1', 'recorded_at' => '2026-08-21 12:00:00'],
            ['sensor_id' => 'temperature', 'recorded_at' => '2026-08-21 12:00:00'],
            ['recorded_at' => '2026-08-19 23:59:59'],
            ['recorded_at' => '2026-08-22 00:00:00'],
        ] as $outside) {
            SensorReading::factory()->create([...['node_id' => '2', 'sensor_id' => 'soil_moisture'], ...$outside]);
        }

        $response = $this->actingAs(User::factory()->create())->get('/history?node=2&sensor=soil_moisture&from=2026-08-20&to=2026-08-21&timezone=UTC');

        $response->assertOk();
        $rows = $response->viewData('readings');
        $this->assertSame(26, $rows->total());
        $this->assertSame($latest->id, $rows->first()->id);
        $this->assertSame(0.0, $rows->first()->value);
        parse_str(parse_url($rows->nextPageUrl(), PHP_URL_QUERY), $query);
        $this->assertSame(['node' => '2', 'sensor' => 'soil_moisture', 'from' => '2026-08-20', 'to' => '2026-08-21', 'timezone' => 'UTC', 'page' => '2'], $query);
        $this->get('/history?node=1&sensor=air_humidity&from=2026-08-20&to=2026-08-21&timezone=UTC')->assertSeeText('Belum ada pembacaan untuk filter ini.');
    }

    public function test_history_defaults_to_seven_utc_days_and_paginates_all_results(): void
    {
        $this->get('/history')->assertRedirect('/login');
        $this->travelTo(Carbon::parse('2026-09-16 12:00:00', 'UTC'));
        SensorReading::factory()->count(27)->create(['node_id' => '1', 'sensor_id' => 'temperature', 'recorded_at' => '2026-09-10 00:00:00']);
        SensorReading::factory()->create(['node_id' => '1', 'sensor_id' => 'temperature', 'recorded_at' => '2026-09-09 23:59:59']);
        SensorReading::factory()->create(['node_id' => '1', 'sensor_id' => 'temperature', 'recorded_at' => now()->addSecond()]);

        $response = $this->actingAs(User::factory()->create())->get('/history?timezone=UTC');

        $response->assertOk()->assertViewIs('history.index')
            ->assertSeeText('Riwayat pembacaan')->assertSeeText('Waktu (UTC)')
            ->assertSeeText('Node')->assertSeeText('Parameter')
            ->assertSeeText('Dari tanggal')->assertSeeText('Sampai tanggal')
            ->assertSeeText('Ekspor PDF')->assertDontSeeText('Ekspor CSV')
            ->assertSee('target="_blank" rel="noopener noreferrer"', false)
            ->assertSee('title="Buka laporan untuk dicetak atau disimpan sebagai PDF (tab baru)"', false)
            ->assertViewHas('filters', fn (array $filters): bool => $filters['from'] === '2026-09-10' && $filters['to'] === '2026-09-16');
        $readings = $response->viewData('readings');
        $this->assertSame(27, $readings->total());
        $this->assertCount(25, $readings);
        $this->assertSame(range(27, 3), $readings->pluck('id')->all());
        $this->get('/history?page=2&timezone=UTC')->assertOk()->assertViewHas('readings', fn ($rows): bool => $rows->pluck('id')->all() === [2, 1]);
    }
}
