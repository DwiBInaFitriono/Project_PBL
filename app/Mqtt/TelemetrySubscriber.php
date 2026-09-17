<?php

namespace App\Mqtt;

use PhpMqtt\Client\Contracts\MqttClient;
use RuntimeException;
use Throwable;

class TelemetrySubscriber
{
    public function __construct(private MqttConnectionFactory $factory, private TelemetryIngestor $ingestor) {}

    /** @return array{accepted: int, duplicate: int, rejected: int} */
    public function run(bool $once, int $timeout): array
    {
        $ackTimeout = filter_var(config('mqtt.subscription_ack_timeout'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 60]]);
        if ($ackTimeout === false) {
            throw new RuntimeException('Batas konfirmasi langganan MQTT tidak valid.');
        }

        $client = $this->factory->create();
        $counts = ['accepted' => 0, 'duplicate' => 0, 'rejected' => 0];
        $failed = false;
        $subscriptionFailed = false;
        $stopped = false;
        $receivedOnce = false;
        $topics = array_map(fn (array $node): string => 'rebung-pintar/v1/nodes/'.$node['id'].'/telemetry', config('monitoring.nodes'));

        try {
            $client->connect($this->factory->settings(), true);
            $client->registerLoopEventHandler(function (MqttClient $mqtt, float $elapsed) use ($timeout, $ackTimeout, $topics, &$stopped, &$subscriptionFailed, &$receivedOnce): void {
                if ($stopped) {
                    return;
                }
                if (! $this->factory->hasAcknowledgedSubscriptions($topics)
                    && (! $this->factory->hasPendingSubscriptions() || $elapsed >= $ackTimeout)) {
                    $subscriptionFailed = true;
                    $stopped = true;
                    $mqtt->interrupt();
                } elseif ($timeout > 0 && $elapsed >= $timeout) {
                    $subscriptionFailed = ! $this->factory->hasAcknowledgedSubscriptions($topics);
                    $stopped = true;
                    $mqtt->interrupt();
                } elseif ($receivedOnce && $this->factory->hasAcknowledgedSubscriptions($topics)) {
                    $stopped = true;
                    $mqtt->interrupt();
                }
            });
            foreach ($topics as $topic) {
                $client->subscribe($topic, function (string $topic, string $payload, bool $retained) use ($client, $once, $topics, &$counts, &$failed, &$stopped, &$receivedOnce): void {
                    if ($stopped || $receivedOnce) {
                        return;
                    }
                    try {
                        $result = $this->ingestor->ingest($topic, $payload, $retained);
                        $counts[$result]++;
                    } catch (InvalidTelemetry) {
                        $counts['rejected']++;
                    } catch (Throwable) {
                        $failed = true;
                    }
                    $receivedOnce = $once;
                    if ($failed || ($receivedOnce && $this->factory->hasAcknowledgedSubscriptions($topics))) {
                        $stopped = true;
                        $client->interrupt();
                    }
                }, 1);
            }
            $client->loop(true, false);
            if ($failed) {
                throw new RuntimeException('Penyimpanan MQTT gagal.');
            }
            if ($subscriptionFailed || ! $this->factory->hasAcknowledgedSubscriptions($topics)) {
                throw new RuntimeException('Langganan MQTT tidak dikonfirmasi.');
            }
        } finally {
            if ($client->isConnected()) {
                $client->disconnect();
            }
        }

        return $counts;
    }
}
