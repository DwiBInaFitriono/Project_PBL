<?php

namespace Tests\Feature;

use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MqttLocalBrokerTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_php_mqtt_client_receives_qos_one_from_isolated_loopback_fixture(): void
    {
        $this->withBroker('success', function ($output): void {
            $this->artisan('mqtt:subscribe', ['--once' => true, '--timeout' => 3])
                ->expectsOutputToContain('diterima=1, duplikat=0, ditolak=0')->assertSuccessful();

            $this->assertDatabaseCount('mqtt_messages', 1);
            $this->assertDatabaseCount('sensor_readings', 1);
            $this->assertDatabaseHas('sensor_readings', ['node_id' => '1', 'sensor_id' => 'temperature', 'value' => 28.25]);
            $this->assertSame('PUBACK+DISCONNECT', trim((string) fgets($output)));
        });
    }

    #[DataProvider('rejectedSubscriptions')]
    public function test_rejected_subacks_fail_promptly_without_cli_timeout(string $scenario): void
    {
        $this->withBroker($scenario, function ($output): void {
            config(['mqtt.subscription_ack_timeout' => 3]);
            $started = microtime(true);
            $this->artisan('mqtt:subscribe')
                ->expectsOutputToContain('MQTT gagal')->doesntExpectOutputToContain('diterima=')
                ->assertFailed();

            $this->assertLessThan(1.5, microtime(true) - $started);
            $this->assertDatabaseCount('mqtt_messages', 0);
            $this->assertDatabaseCount('sensor_readings', 0);
            $this->assertSame('DISCONNECT', trim((string) fgets($output)));
        });
    }

    /** @return array<string, array{string}> */
    public static function rejectedSubscriptions(): array
    {
        return ['one rejected' => ['one-rejected'], 'both rejected' => ['both-rejected']];
    }

    #[DataProvider('missingSubscriptions')]
    public function test_missing_subacks_fail_by_ack_deadline_without_cli_timeout(string $scenario): void
    {
        $this->withBroker($scenario, function ($output): void {
            $started = microtime(true);
            $this->artisan('mqtt:subscribe')
                ->expectsOutputToContain('MQTT gagal')->doesntExpectOutputToContain('diterima=')
                ->assertFailed();

            $this->assertLessThan(2.5, microtime(true) - $started);
            $this->assertDatabaseCount('sensor_readings', 0);
            $this->assertSame('DISCONNECT', trim((string) fgets($output)));
        });
    }

    /** @return array<string, array{string}> */
    public static function missingSubscriptions(): array
    {
        return [
            'no suback' => ['no-suback'],
            'second missing' => ['second-missing'],
            'first rejected second missing' => ['first-rejected-second-missing'],
        ];
    }

    #[DataProvider('earlyMessageSubscriptionFailures')]
    public function test_once_cannot_succeed_on_message_before_second_subscription_is_confirmed(string $scenario): void
    {
        $this->withBroker($scenario, function ($output): void {
            $this->artisan('mqtt:subscribe', ['--once' => true, '--timeout' => 3])
                ->expectsOutputToContain('MQTT gagal')->doesntExpectOutputToContain('diterima=')
                ->doesntExpectOutputToContain('28.25')->assertFailed();

            $this->assertDatabaseCount('mqtt_messages', 1);
            $this->assertDatabaseCount('sensor_readings', 1);
            $this->assertSame('PUBACK+DISCONNECT', trim((string) fgets($output)));
        });
    }

    /** @return array<string, array{string}> */
    public static function earlyMessageSubscriptionFailures(): array
    {
        return ['second rejected' => ['early-message-rejected'], 'second missing' => ['early-message-missing']];
    }

    public function test_normal_no_data_timeout_succeeds_only_after_both_subacks(): void
    {
        $this->withBroker('quiet', function ($output): void {
            $started = microtime(true);
            $this->artisan('mqtt:subscribe', ['--timeout' => 2])
                ->expectsOutputToContain('diterima=0, duplikat=0, ditolak=0')->assertSuccessful();

            $this->assertGreaterThanOrEqual(2.0, microtime(true) - $started);
            $this->assertDatabaseCount('sensor_readings', 0);
            $this->assertSame('DISCONNECT', trim((string) fgets($output)));
        });
    }

    public function test_once_no_data_timeout_is_not_reported_as_subscription_failure(): void
    {
        $this->withBroker('quiet', function ($output): void {
            $this->artisan('mqtt:subscribe', ['--once' => true, '--timeout' => 1])
                ->expectsOutputToContain('diterima=0, duplikat=0, ditolak=0')
                ->doesntExpectOutputToContain('MQTT gagal')->assertFailed();

            $this->assertDatabaseCount('sensor_readings', 0);
            $this->assertSame('DISCONNECT', trim((string) fgets($output)));
        });
    }

    public function test_once_waits_for_second_suback_after_receiving_first_message(): void
    {
        $this->withBroker('early-message', function ($output): void {
            $this->artisan('mqtt:subscribe', ['--once' => true, '--timeout' => 3])
                ->expectsOutputToContain('diterima=1, duplikat=0, ditolak=0')->assertSuccessful();

            $this->assertDatabaseCount('mqtt_messages', 1);
            $this->assertDatabaseCount('sensor_readings', 1);
            $this->assertSame('PUBACK+DISCONNECT', trim((string) fgets($output)));
        });
    }

    private function withBroker(string $scenario, Closure $assertions): void
    {
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $script = 'require '.var_export(base_path('vendor/autoload.php'), true).'; require '.var_export(__FILE__, true).'; '.self::class.'::serveBroker('.var_export($scenario, true).');';
        $process = proc_open([PHP_BINARY, '-r', $script], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
        $this->assertIsResource($process);

        try {
            stream_set_timeout($pipes[1], 10);
            $port = trim((string) fgets($pipes[1]));
            $this->assertMatchesRegularExpression('/^\d+$/', $port);
            config([
                'mqtt.enabled' => true, 'mqtt.host' => '127.0.0.1', 'mqtt.port' => (int) $port,
                'mqtt.tls' => false, 'mqtt.allow_insecure_local' => true,
                'mqtt.username' => null, 'mqtt.password' => null, 'mqtt.client_id' => 'mqtt-test-isolated',
                'mqtt.subscription_ack_timeout' => 1,
            ]);
            $assertions($pipes[1]);
        } finally {
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_terminate($process);
            proc_close($process);
        }
    }

    /** Isolated MQTT 3.1.1 wire fixture, not a production broker. */
    public static function serveBroker(string $scenario): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        if ($server === false) {
            throw new \RuntimeException('Cannot open loopback fixture.');
        }
        $address = stream_socket_get_name($server, false);
        fwrite(STDOUT, substr($address, strrpos($address, ':') + 1)."\n");
        fflush(STDOUT);
        $socket = stream_socket_accept($server, 5);
        if ($socket === false) {
            throw new \RuntimeException('Fixture accept timed out.');
        }
        stream_set_timeout($socket, 5);
        $read = static function (int $length) use ($socket): string {
            $data = '';
            while (strlen($data) < $length) {
                $chunk = fread($socket, $length - strlen($data));
                if ($chunk === false || $chunk === '') {
                    throw new \RuntimeException('Incomplete MQTT test packet.');
                }
                $data .= $chunk;
            }

            return $data;
        };
        $packet = static function () use ($read): array {
            $header = ord($read(1));
            $length = 0;
            $multiplier = 1;
            do {
                $digit = ord($read(1));
                $length += ($digit & 127) * $multiplier;
                $multiplier *= 128;
            } while (($digit & 128) !== 0 && $multiplier <= 268435456);

            return [$header, $read($length)];
        };
        try {
            [$header] = $packet();
            if ($header !== 0x10) {
                throw new \RuntimeException('Expected CONNECT.');
            }
            fwrite($socket, "\x20\x02\x00\x00");
            $ids = [];
            for ($index = 0; $index < 2; $index++) {
                [$header, $body] = $packet();
                $topic = 'rebung-pintar/v1/nodes/'.($index + 1).'/telemetry';
                if ($header !== 0x82 || substr($body, 2) !== pack('n', strlen($topic)).$topic."\x01") {
                    throw new \RuntimeException('Expected exact telemetry SUBSCRIBE with QoS 1.');
                }
                $ids[] = substr($body, 0, 2);
            }
            $earlyMessage = str_starts_with($scenario, 'early-message');
            if ($scenario !== 'no-suback') {
                $firstRejected = in_array($scenario, ['both-rejected', 'first-rejected-second-missing'], true);
                fwrite($socket, "\x90\x03".$ids[0].($firstRejected ? "\x80" : "\x01"));
                if (! $earlyMessage && ! in_array($scenario, ['second-missing', 'first-rejected-second-missing'], true)) {
                    $secondRejected = in_array($scenario, ['one-rejected', 'both-rejected'], true);
                    fwrite($socket, "\x90\x03".$ids[1].($secondRejected ? "\x80" : "\x01"));
                }
            }

            $publish = $scenario === 'success' || $earlyMessage;
            if ($publish) {
                $topic = 'rebung-pintar/v1/nodes/1/telemetry';
                $payload = json_encode([
                    'schema_version' => 1, 'message_id' => '5ef03860-30ae-44b2-a946-2ec66b397510', 'node_id' => '1',
                    'recorded_at' => gmdate('Y-m-d\TH:i:s\Z'), 'readings' => ['temperature' => 28.25],
                ], JSON_THROW_ON_ERROR);
                $body = pack('n', strlen($topic)).$topic."\x00\x01".$payload;
                $length = strlen($body);
                $encoded = '';
                do {
                    $digit = $length % 128;
                    $length = intdiv($length, 128);
                    $encoded .= chr($length > 0 ? $digit | 128 : $digit);
                } while ($length > 0);
                fwrite($socket, "\x32".$encoded.$body);
                [$header, $body] = $packet();
                if ($header !== 0x40 || $body !== "\x00\x01") {
                    throw new \RuntimeException('Expected PUBACK.');
                }
                if ($earlyMessage && $scenario !== 'early-message-missing') {
                    usleep(250000);
                    fwrite($socket, "\x90\x03".$ids[1].($scenario === 'early-message-rejected' ? "\x80" : "\x01"));
                }
            }
            [$header] = $packet();
            if ($header !== 0xE0) {
                throw new \RuntimeException('Expected DISCONNECT.');
            }
            fwrite(STDOUT, ($publish ? 'PUBACK+' : '')."DISCONNECT\n");
        } finally {
            fclose($socket);
            fclose($server);
        }
    }
}
