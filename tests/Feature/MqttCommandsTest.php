<?php

namespace Tests\Feature;

use App\Mqtt\MqttConnectionFactory;
use App\Mqtt\SubscriptionRepository;
use App\Mqtt\TelemetryIngestor;
use App\Mqtt\TelemetrySubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Contracts\MqttClient;
use PhpMqtt\Client\Subscription;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class MqttCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_once_feeds_real_ingestor_and_disconnects_with_secure_settings(): void
    {
        config(['mqtt.enabled' => true, 'mqtt.host' => 'broker.invalid']);
        $this->freezeTime();
        $client = $this->createMock(MqttClient::class);
        $this->partialMock(MqttConnectionFactory::class, function (MockInterface $mock) use ($client): void {
            $mock->shouldReceive('create')->once()->andReturn($client);
            $mock->shouldReceive('hasAcknowledgedSubscriptions')->andReturn(true);
        });
        $client->expects($this->once())->method('connect')->with($this->callback(function (ConnectionSettings $settings): bool {
            $this->assertTrue($settings->shouldUseTls());
            $this->assertTrue($settings->shouldTlsVerifyPeer());
            $this->assertTrue($settings->shouldTlsVerifyPeerName());
            $this->assertFalse($settings->isTlsSelfSignedAllowed());
            $this->assertFalse($settings->shouldReconnectAutomatically());
            $this->assertSame(10, $settings->getConnectTimeout());

            return true;
        }), true);
        $callbacks = [];
        $client->expects($this->exactly(2))->method('subscribe')->willReturnCallback(function (string $topic, callable $callback, int $qos) use (&$callbacks): void {
            $this->assertContains($topic, ['rebung-pintar/v1/nodes/1/telemetry', 'rebung-pintar/v1/nodes/2/telemetry']);
            $this->assertSame(1, $qos);
            $callbacks[$topic] = $callback;
        });
        $client->method('registerLoopEventHandler')->willReturnSelf();
        $client->expects($this->once())->method('loop')->willReturnCallback(function () use (&$callbacks): void {
            $topic = 'rebung-pintar/v1/nodes/1/telemetry';
            $callbacks[$topic]($topic, json_encode([
                'schema_version' => 1, 'message_id' => '5ef03860-30ae-44b2-a946-2ec66b397510', 'node_id' => '1',
                'recorded_at' => now('UTC')->toIso8601String(), 'readings' => ['temperature' => 27.5],
            ], JSON_THROW_ON_ERROR), false, []);
        });
        $client->expects($this->once())->method('interrupt');
        $client->method('isConnected')->willReturn(true);
        $client->expects($this->once())->method('disconnect');

        $this->artisan('mqtt:subscribe', ['--once' => true, '--timeout' => 1])->assertSuccessful();
        $this->assertDatabaseCount('mqtt_messages', 1);
        $this->assertDatabaseHas('sensor_readings', ['node_id' => '1', 'sensor_id' => 'temperature', 'value' => 27.5]);
    }

    public function test_timeout_interrupts_and_once_without_messages_fails(): void
    {
        $client = $this->fakeClient();
        $hook = null;
        $client->expects($this->once())->method('registerLoopEventHandler')->willReturnCallback(function (\Closure $callback) use (&$hook, $client): MqttClient {
            $hook = $callback;

            return $client;
        });
        $client->method('loop')->willReturnCallback(function () use (&$hook, $client): void {
            $this->assertIsCallable($hook);
            $hook($client, 2.0);
        });
        $client->expects($this->once())->method('interrupt');
        $client->expects($this->once())->method('disconnect');

        $this->artisan('mqtt:subscribe', ['--once' => true, '--timeout' => 1])->assertFailed();
        $this->assertDatabaseCount('sensor_readings', 0);
    }

    public function test_cli_timeout_with_pending_suback_cannot_be_rescued_by_late_ack(): void
    {
        config(['mqtt.enabled' => true, 'mqtt.host' => 'broker.invalid']);
        $client = $this->createMock(MqttClient::class);
        $acknowledged = false;
        $this->partialMock(MqttConnectionFactory::class, function (MockInterface $mock) use ($client, &$acknowledged): void {
            $mock->shouldReceive('create')->once()->andReturn($client);
            $mock->shouldReceive('hasPendingSubscriptions')->andReturn(true);
            $mock->shouldReceive('hasAcknowledgedSubscriptions')->andReturnUsing(function () use (&$acknowledged): bool {
                return $acknowledged;
            });
        });
        $hook = null;
        $client->method('registerLoopEventHandler')->willReturnCallback(function (\Closure $callback) use (&$hook, $client): MqttClient {
            $hook = $callback;

            return $client;
        });
        $client->method('loop')->willReturnCallback(function () use (&$hook, $client, &$acknowledged): void {
            $hook($client, 2.0);
            $acknowledged = true;
        });
        $client->expects($this->once())->method('interrupt');
        $client->method('isConnected')->willReturn(true);
        $client->expects($this->once())->method('disconnect');
        $this->artisan('mqtt:subscribe', ['--timeout' => 1])
            ->expectsOutputToContain('MQTT gagal')->assertFailed();
    }

    #[DataProvider('invalidAcknowledgementTimeouts')]
    public function test_invalid_ack_deadline_is_rejected_before_creating_client(mixed $timeout): void
    {
        config(['mqtt.subscription_ack_timeout' => $timeout]);
        $factory = $this->createMock(MqttConnectionFactory::class);
        $factory->expects($this->never())->method('create');
        $subscriber = new TelemetrySubscriber($factory, app(TelemetryIngestor::class));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Batas konfirmasi langganan MQTT tidak valid.');
        $subscriber->run(false, 0);
    }

    /** @return array<string, array{mixed}> */
    public static function invalidAcknowledgementTimeouts(): array
    {
        return [
            'zero' => [0], 'negative' => [-1], 'too large' => [61],
            'fraction' => [0.5], 'text' => ['private-secret'], 'missing' => [null],
        ];
    }

    public function test_subscription_status_requires_exact_acknowledged_topics_and_resets(): void
    {
        $repository = new SubscriptionRepository;
        $topics = ['rebung-pintar/v1/nodes/1/telemetry', 'rebung-pintar/v1/nodes/2/telemetry'];
        $this->assertFalse($repository->hasAcknowledgedSubscriptions([]));
        $this->assertFalse($repository->hasAcknowledgedSubscriptions($topics));
        $repository->addSubscription(new Subscription('rebung-pintar/v1/nodes/+/telemetry', 1));
        $this->assertFalse($repository->hasAcknowledgedSubscriptions($topics));
        $repository->addSubscription(new Subscription($topics[0], 1));
        $this->assertFalse($repository->hasAcknowledgedSubscriptions($topics));
        $repository->addSubscription(new Subscription($topics[1], 1));
        $this->assertTrue($repository->hasAcknowledgedSubscriptions($topics));
        $repository->reset();
        $this->assertFalse($repository->hasAcknowledgedSubscriptions($topics));
    }

    public function test_factory_status_fails_closed_before_client_creation(): void
    {
        $factory = new MqttConnectionFactory;
        $this->assertFalse($factory->hasAcknowledgedSubscriptions(['rebung-pintar/v1/nodes/1/telemetry']));
        $this->assertFalse($factory->hasPendingSubscriptions());
    }

    public function test_retained_payload_is_rejected_without_exposing_raw_payload(): void
    {
        $client = $this->fakeClient();
        $callback = null;
        $client->method('subscribe')->willReturnCallback(function (string $topic, callable $handler) use (&$callback): void {
            $callback = $handler;
        });
        $client->method('loop')->willReturnCallback(function () use (&$callback): void {
            $callback('rebung-pintar/v1/nodes/1/telemetry', 'raw-secret-payload', true);
        });
        $client->expects($this->once())->method('interrupt');
        $client->expects($this->once())->method('disconnect');
        $this->artisan('mqtt:subscribe', ['--once' => true])->expectsOutputToContain('ditolak=1')
            ->doesntExpectOutputToContain('raw-secret-payload')->assertFailed();
        $this->assertDatabaseCount('sensor_readings', 0);
    }

    public function test_connection_failure_exits_nonzero_without_credentials_or_retries(): void
    {
        $client = $this->fakeClient();
        $client->expects($this->once())->method('connect')->willThrowException(new \RuntimeException('private-password'));
        $client->expects($this->never())->method('loop');
        $this->artisan('mqtt:subscribe')->expectsOutputToContain('MQTT gagal')
            ->doesntExpectOutputToContain('private-password')->assertFailed();
    }

    public function test_storage_failure_inside_callback_stops_loop_and_is_not_swallowed(): void
    {
        $client = $this->fakeClient();
        $this->mock(TelemetryIngestor::class, function (MockInterface $mock): void {
            $mock->shouldReceive('ingest')->once()->andThrow(new \RuntimeException('private-payload'));
        });
        $callback = null;
        $client->method('subscribe')->willReturnCallback(function (string $topic, callable $handler) use (&$callback): void {
            $callback = $handler;
        });
        $client->method('loop')->willReturnCallback(function () use (&$callback): void {
            try {
                $callback('rebung-pintar/v1/nodes/1/telemetry', '{}', false);
            } catch (\Throwable) {
                // The actual library catches subscriber callback exceptions too.
            }
        });
        $client->expects($this->once())->method('interrupt');
        $client->expects($this->once())->method('disconnect');
        $this->artisan('mqtt:subscribe')->expectsOutputToContain('MQTT gagal')
            ->doesntExpectOutputToContain('private-payload')->assertFailed();
    }

    private function fakeClient(): MqttClient&MockObject
    {
        config(['mqtt.enabled' => true, 'mqtt.host' => 'broker.invalid']);
        $client = $this->createMock(MqttClient::class);
        $client->method('isConnected')->willReturn(true);
        $this->partialMock(MqttConnectionFactory::class, function (MockInterface $mock) use ($client): void {
            $mock->shouldReceive('create')->once()->andReturn($client);
            $mock->shouldReceive('hasAcknowledgedSubscriptions')->andReturn(true);
        });

        return $client;
    }

    #[DataProvider('unsafeConfigurations')]
    public function test_unsafe_configuration_is_rejected_offline(array $overrides): void
    {
        config(array_replace(['mqtt.enabled' => true, 'mqtt.host' => 'broker.invalid'], $overrides));
        $this->app->bind(MqttConnectionFactory::class, function (): never {
            $this->fail('Unsafe configuration reached the factory.');
        });
        $this->artisan('mqtt:check')->assertFailed();
        $this->artisan('mqtt:subscribe')->assertFailed();
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function unsafeConfigurations(): array
    {
        return [
            'remote cleartext' => [['mqtt.tls' => false]],
            'remote opt-in cleartext' => [['mqtt.tls' => false, 'mqtt.allow_insecure_local' => true]],
            'local cleartext no opt-in' => [['mqtt.host' => '127.0.0.1', 'mqtt.tls' => false]],
            'peer verification off' => [['mqtt.tls_verify_peer' => false]],
            'name verification off' => [['mqtt.tls_verify_peer_name' => false]],
            'self signed' => [['mqtt.tls_allow_self_signed' => true]],
            'bad port' => [['mqtt.port' => 0]],
            'large port' => [['mqtt.port' => 65536]],
            'bad timeout' => [['mqtt.connect_timeout' => 0]],
            'empty client' => [['mqtt.client_id' => '']],
            'URI host' => [['mqtt.host' => 'mqtt://user:password@broker.invalid']],
            'missing CA' => [['mqtt.tls_ca_file' => '/missing-mqtt-test-ca.pem']],
        ];
    }

    public function test_local_cleartext_requires_explicit_opt_in_and_once_is_bounded_by_default(): void
    {
        config(['mqtt.enabled' => true, 'mqtt.host' => '127.0.0.1', 'mqtt.tls' => false, 'mqtt.allow_insecure_local' => true]);
        $this->artisan('mqtt:check')->assertSuccessful();
        $this->mock(TelemetrySubscriber::class, function (MockInterface $mock): void {
            $mock->shouldReceive('run')->once()->with(true, 30)->andReturn(['accepted' => 1, 'duplicate' => 0, 'rejected' => 0]);
        });
        $this->artisan('mqtt:subscribe', ['--once' => true])->assertSuccessful();
    }

    #[DataProvider('invalidTimeouts')]
    public function test_invalid_cli_timeout_never_connects(string $timeout): void
    {
        config(['mqtt.enabled' => true, 'mqtt.host' => 'broker.invalid']);
        $this->app->bind(MqttConnectionFactory::class, function (): never {
            $this->fail('Invalid timeout reached the factory.');
        });
        $this->artisan('mqtt:subscribe', ['--timeout' => $timeout])->expectsOutputToContain('Timeout MQTT tidak valid')->assertFailed();
    }

    /** @return array<string, array{string}> */
    public static function invalidTimeouts(): array
    {
        return ['negative' => ['-1'], 'fraction' => ['0.5'], 'text' => ['abc'], 'too large' => ['86401']];
    }

    public function test_missing_host_is_refused_before_connection_resolution(): void
    {
        config(['mqtt.enabled' => true, 'mqtt.host' => '']);
        $this->app->bind(MqttConnectionFactory::class, function (): never {
            $this->fail('Unconfigured subscriber resolved a connection.');
        });

        $this->artisan('mqtt:subscribe', ['--once' => true])
            ->expectsOutputToContain('MQTT_HOST wajib diisi')
            ->assertFailed();
    }

    public function test_readiness_check_is_offline_and_never_prints_credentials(): void
    {
        config(['mqtt.enabled' => true, 'mqtt.host' => 'broker.invalid', 'mqtt.username' => 'private-user', 'mqtt.password' => 'private-password']);
        $this->app->bind(MqttConnectionFactory::class, function (): never {
            $this->fail('Offline readiness check resolved a connection.');
        });

        $this->artisan('mqtt:check')->expectsOutputToContain('Konfigurasi MQTT siap')
            ->doesntExpectOutputToContain('private-user')->doesntExpectOutputToContain('private-password')
            ->assertSuccessful();
    }

    public function test_readiness_check_reports_disabled_and_missing_host(): void
    {
        config(['mqtt.enabled' => false, 'mqtt.host' => '']);
        $this->artisan('mqtt:check')->expectsOutputToContain('MQTT nonaktif')
            ->expectsOutputToContain('MQTT_HOST wajib diisi')->assertFailed();
    }

    public function test_subscriber_is_disabled_by_default_without_resolving_a_connection(): void
    {
        $this->assertFalse(config('mqtt.enabled'));
        $this->app->bind(MqttConnectionFactory::class, function (): never {
            $this->fail('Disabled subscriber attempted to resolve a connection.');
        });

        $this->artisan('mqtt:subscribe', ['--once' => true, '--timeout' => 1])
            ->expectsOutputToContain('MQTT nonaktif')
            ->assertFailed();
    }
}
