<?php

namespace Tests\Feature;

use App\Models\SensorReading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HistoryTimezoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::factory()->create());
    }

    public function test_relative_default_dates_follow_midnight_in_the_selected_timezone(): void
    {
        foreach ([
            ['2026-09-16 16:59:59', 'Asia/Jakarta', '2026-09-10', '2026-09-16'],
            ['2026-09-16 17:00:00', 'Asia/Jakarta', '2026-09-11', '2026-09-17'],
            ['2026-09-16 17:00:00', 'UTC', '2026-09-10', '2026-09-16'],
            ['2026-09-17 00:00:00', 'UTC', '2026-09-11', '2026-09-17'],
        ] as [$now, $timezone, $from, $to]) {
            $this->travelTo(Carbon::parse($now, 'UTC'));
            $response = $this->get('/history?'.http_build_query(['timezone' => $timezone]))->assertOk();
            $this->assertSame(['node' => null, 'sensor' => null, 'from' => $from, 'to' => $to, 'timezone' => $timezone], $response->viewData('filters'));
        }

        $this->travelTo(Carbon::parse('2026-09-16 17:00:00', 'UTC'));
        foreach (['2026-09-10 16:59:59', '2026-09-10 17:00:00', '2026-09-16 17:00:00', '2026-09-16 17:00:01'] as $timestamp) {
            SensorReading::factory()->create(['node_id' => '1', 'sensor_id' => 'temperature', 'recorded_at' => $timestamp]);
        }
        $response = $this->get('/history')->assertOk();
        $this->assertSame([3, 2], $response->viewData('readings')->pluck('id')->all());
        $this->assertSame('2026-09-11', $response->viewData('filters')['from']);
        $this->assertSame('2026-09-17', $response->viewData('filters')['to']);
        $this->get('/history?from=2026-09-17')->assertOk()
            ->assertViewHas('filters', fn (array $filters): bool => $filters['to'] === '2026-09-17');
        $this->get('/history?to=2026-09-17')->assertOk()
            ->assertViewHas('filters', fn (array $filters): bool => $filters['from'] === '2026-09-11');
    }

    public function test_maximum_range_remains_366_inclusive_days_in_both_timezones(): void
    {
        foreach (['Asia/Jakarta', 'UTC'] as $timezone) {
            foreach (['/history', '/history/export'] as $path) {
                $query = ['from' => '2025-01-01', 'to' => '2026-01-01', 'timezone' => $timezone];
                $this->get($path.'?'.http_build_query($query))->assertOk();
                $this->getJson($path.'?'.http_build_query([...$query, 'to' => '2026-01-02']))->assertUnprocessable()
                    ->assertJsonPath('errors.to.0', 'Rentang tanggal maksimal 366 hari termasuk tanggal awal dan akhir.');
            }
        }
    }

    public function test_invalid_html_timezone_redirects_to_a_safe_form_with_an_indonesian_error(): void
    {
        $this->from('/history?timezone[]=UTC')->get('/history?timezone[]=UTC')
            ->assertRedirect('/history')->assertSessionHasErrors('timezone');
        $this->withCookie(session()->getName(), session()->getId())->get('/history')
            ->assertOk()->assertSeeText('Zona waktu harus berupa WIB (Asia/Jakarta) atau UTC.');
    }

    public function test_filter_links_and_pagination_retain_timezone(): void
    {
        $this->travelTo(Carbon::parse('2026-09-17 12:00:00', 'UTC'));
        SensorReading::factory()->count(26)->create(['node_id' => '2', 'sensor_id' => 'temperature', 'recorded_at' => '2026-09-16 17:00:00']);

        foreach (['Asia/Jakarta', 'UTC'] as $timezone) {
            $filters = ['node' => '2', 'sensor' => 'temperature', 'from' => '2026-09-16', 'to' => '2026-09-17', 'timezone' => $timezone];
            $response = $this->get('/history?'.http_build_query($filters))->assertOk();
            $this->assertSame($filters, $response->viewData('filters'));
            parse_str(parse_url($response->viewData('readings')->nextPageUrl(), PHP_URL_QUERY), $nextQuery);
            $this->assertSame([...$filters, 'page' => '2'], $nextQuery);
            $response->assertSee(route('history.export', $filters))
                ->assertSee('href="'.e(route('history.index', ['timezone' => $timezone])).'">Reset filter</a>', false);
            $this->get('/history?'.http_build_query($nextQuery))->assertOk()
                ->assertViewHas('readings', fn ($rows): bool => $rows->count() === 1);
            $this->get('/history?'.http_build_query(['timezone' => $timezone]))->assertOk()
                ->assertViewHas('filters', fn (array $reset): bool => $reset['timezone'] === $timezone && $reset['node'] === null && $reset['sensor'] === null);
        }
    }

    public function test_print_report_exports_all_matches_in_the_selected_timezone_without_changing_storage(): void
    {
        $this->travelTo(Carbon::parse('2026-09-17 12:00:00', 'UTC'));
        SensorReading::factory()->count(26)->create(['node_id' => '2', 'sensor_id' => 'temperature', 'value' => 0, 'recorded_at' => '2026-09-16 17:00:00']);
        SensorReading::factory()->create(['node_id' => '1', 'sensor_id' => 'temperature', 'recorded_at' => '2026-09-16 17:00:00']);
        SensorReading::factory()->create(['node_id' => '2', 'sensor_id' => 'air_humidity', 'recorded_at' => '2026-09-16 17:00:00']);
        $stored = DB::table('sensor_readings')->orderBy('id')->get()->all();

        foreach (['Asia/Jakarta' => ['WIB', '2026-09-17', '2026-09-17 00:00:00'], 'UTC' => ['UTC', '2026-09-16', '2026-09-16 17:00:00']] as $timezone => [$label, $date, $display]) {
            $response = $this->get('/history/export?'.http_build_query([
                'node' => '2', 'sensor' => 'temperature', 'from' => $date, 'to' => $date, 'timezone' => $timezone, 'page' => 2,
            ]))->assertOk()->assertViewIs('history.export')
                ->assertSeeText('Waktu ('.$label.')')->assertSeeText('Total: 26 pembacaan');
            $content = $response->getContent();
            $rows = $response->viewData('readings');
            $this->assertCount(26, $rows);
            $this->assertSame(range(26, 1), $rows->pluck('id')->all());
            $this->assertSame(array_fill(0, 26, 0.0), $rows->pluck('value')->all());
            $this->assertSame(26, substr_count($content, '>'.$display.'</time>'));
            $this->assertSame('2026-09-16 17:00:00', $rows->first()->recorded_at->format('Y-m-d H:i:s'));
            $this->assertEquals($stored, DB::table('sensor_readings')->orderBy('id')->get()->all());
        }
        $this->get('/history/export')->assertOk()->assertSeeText('Waktu (WIB)');
    }

    public function test_html_displays_selected_timezone_without_mutating_stored_timestamps(): void
    {
        $this->travelTo(Carbon::parse('2026-09-17 12:00:00', 'UTC'));
        $reading = SensorReading::factory()->create(['node_id' => '1', 'sensor_id' => 'temperature', 'recorded_at' => '2026-09-16 17:00:00']);
        $stored = DB::table('sensor_readings')->where('id', $reading->id)->first();

        foreach (['Asia/Jakarta' => ['WIB', '2026-09-17 00:00:00'], 'UTC' => ['UTC', '2026-09-16 17:00:00']] as $timezone => [$label, $display]) {
            $response = $this->get('/history?'.http_build_query(['from' => '2026-09-16', 'to' => '2026-09-17', 'timezone' => $timezone]));
            $response->assertOk()->assertSeeText('Waktu ('.$label.')')->assertSeeText($display)
                ->assertSee('datetime="2026-09-16T17:00:00+00:00"', false)
                ->assertDontSee('name="timezone"', false);
            $this->assertEquals($stored, DB::table('sensor_readings')->where('id', $reading->id)->first());
            $this->assertSame('2026-09-16 17:00:00', $response->viewData('readings')->first()->recorded_at->format('Y-m-d H:i:s'));
        }
        $this->get('/history')->assertOk()->assertDontSee('name="timezone"', false)->assertSeeText('Waktu (WIB)');
    }

    public function test_invalid_timezones_are_rejected_in_indonesian_before_date_parsing(): void
    {
        // Validation matrix is independent of the separately tested export rate limit.
        $this->withoutMiddleware(ThrottleRequests::class);
        foreach (['timezone=Asia/Tokyo', 'timezone=WIB', 'timezone=utc', 'timezone[]=UTC', 'timezone[zone]=Asia/Jakarta', 'timezone=', 'timezone=0'] as $query) {
            foreach (['/history', '/history/export'] as $path) {
                $this->getJson($path.'?'.$query)->assertUnprocessable()
                    ->assertJsonValidationErrors('timezone')
                    ->assertJsonPath('errors.timezone.0', 'Zona waktu harus berupa WIB (Asia/Jakarta) atau UTC.');
            }
        }
    }

    public function test_explicit_timezone_selects_utc_or_wib_day_boundaries(): void
    {
        $this->travelTo(Carbon::parse('2026-09-16 12:00:00', 'UTC'));
        foreach (['2026-09-09 16:59:59', '2026-09-09 17:00:00', '2026-09-10 16:59:59', '2026-09-10 17:00:00', '2026-09-10 23:59:59', '2026-09-11 00:00:00'] as $timestamp) {
            SensorReading::factory()->create(['node_id' => '1', 'sensor_id' => 'temperature', 'recorded_at' => $timestamp]);
        }

        foreach (['UTC' => [5, 4, 3], 'Asia/Jakarta' => [3, 2]] as $timezone => $expected) {
            $response = $this->get('/history?'.http_build_query(['from' => '2026-09-10', 'to' => '2026-09-10', 'timezone' => $timezone]))->assertOk();
            $this->assertSame($expected, $response->viewData('readings')->pluck('id')->all());
            $this->assertSame($timezone, $response->viewData('filters')['timezone']);
        }
    }

    public function test_default_history_uses_inclusive_wib_dates_against_utc_storage(): void
    {
        $this->travelTo(Carbon::parse('2026-09-16 12:00:00', 'UTC'));
        foreach (['2026-09-09 16:59:59', '2026-09-09 17:00:00', '2026-09-10 16:59:59', '2026-09-10 17:00:00'] as $timestamp) {
            SensorReading::factory()->create(['node_id' => '1', 'sensor_id' => 'temperature', 'recorded_at' => $timestamp]);
        }

        $response = $this->get('/history?from=2026-09-10&to=2026-09-10')->assertOk();

        $this->assertSame([3, 2], $response->viewData('readings')->pluck('id')->all());
        $this->assertSame('Asia/Jakarta', $response->viewData('filters')['timezone']);
        $this->assertSame('2026-09-10', $response->viewData('filters')['from']);
        $this->assertSame('2026-09-10', $response->viewData('filters')['to']);
    }
}
