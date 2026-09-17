<?php

namespace App\Mqtt;

class MqttConfiguration
{
    /** @return list<string> */
    public function problems(): array
    {
        $problems = [];
        if (config('mqtt.enabled') !== true) {
            $problems[] = 'MQTT nonaktif. Aktifkan hanya setelah persiapan broker dan migrasi disetujui.';
        }
        if (! is_string(config('mqtt.host')) || trim(config('mqtt.host')) === '') {
            $problems[] = 'MQTT_HOST wajib diisi.';
        }

        $host = config('mqtt.host');
        if (is_string($host) && $host !== '' && ! filter_var($host, FILTER_VALIDATE_IP)
            && ! filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            $problems[] = 'MQTT_HOST harus hostname atau alamat IP tanpa skema, path, atau kredensial.';
        }
        foreach (['port' => 65535, 'connect_timeout' => 60, 'socket_timeout' => 60, 'keep_alive' => 65535] as $key => $maximum) {
            if (filter_var(config('mqtt.'.$key), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $maximum]]) === false) {
                $problems[] = 'Konfigurasi numerik MQTT tidak valid: '.$key.'.';
            }
        }
        if (! is_string(config('mqtt.client_id')) || trim(config('mqtt.client_id')) === '') {
            $problems[] = 'MQTT_CLIENT_ID wajib diisi.';
        }
        if (config('mqtt.tls') !== true && ! (config('mqtt.tls') === false
            && config('mqtt.allow_insecure_local') === true
            && in_array($host, ['127.0.0.1', '::1'], true)
            && app()->environment(['local', 'testing']))) {
            $problems[] = 'TLS MQTT wajib; pengecualian hanya IP loopback dengan MQTT_ALLOW_INSECURE_LOCAL pada local/testing.';
        }
        if (config('mqtt.tls_verify_peer') !== true || config('mqtt.tls_verify_peer_name') !== true
            || config('mqtt.tls_allow_self_signed') !== false) {
            $problems[] = 'Verifikasi sertifikat TLS MQTT wajib diaktifkan tanpa self-signed.';
        }
        $ca = config('mqtt.tls_ca_file');
        if ($ca !== null && (! is_string($ca) || ! is_file($ca) || ! is_readable($ca))) {
            $problems[] = 'MQTT_TLS_CA_FILE tidak dapat dibaca.';
        }

        return $problems;
    }
}
