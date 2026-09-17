<?php

namespace App\Mqtt;

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Contracts\MqttClient as MqttClientContract;
use PhpMqtt\Client\MqttClient;
use Psr\Log\NullLogger;

class MqttConnectionFactory
{
    private ?SubscriptionRepository $repository = null;

    public function create(): MqttClientContract
    {
        $this->repository = new SubscriptionRepository;

        return new MqttClient(
            config('mqtt.host'), (int) config('mqtt.port'), config('mqtt.client_id'),
            MqttClient::MQTT_3_1_1, $this->repository, new NullLogger,
        );
    }

    /** @param list<string> $topics */
    public function hasAcknowledgedSubscriptions(array $topics): bool
    {
        return $this->repository?->hasAcknowledgedSubscriptions($topics) ?? false;
    }

    /** The subscriber only queues outgoing SUBSCRIBE requests. */
    public function hasPendingSubscriptions(): bool
    {
        return ($this->repository?->countPendingOutgoingMessages() ?? 0) > 0;
    }

    public function settings(): ConnectionSettings
    {
        return (new ConnectionSettings)
            ->setUsername(config('mqtt.username'))
            ->setPassword(config('mqtt.password'))
            ->setUseTls(config('mqtt.tls'))
            ->setTlsVerifyPeer(config('mqtt.tls_verify_peer'))
            ->setTlsVerifyPeerName(config('mqtt.tls_verify_peer_name'))
            ->setTlsSelfSignedAllowed(config('mqtt.tls_allow_self_signed'))
            ->setTlsCertificateAuthorityFile(config('mqtt.tls_ca_file'))
            ->setConnectTimeout(config('mqtt.connect_timeout'))
            ->setSocketTimeout(config('mqtt.socket_timeout'))
            ->setKeepAliveInterval(config('mqtt.keep_alive'))
            ->setReconnectAutomatically(false);
    }
}
