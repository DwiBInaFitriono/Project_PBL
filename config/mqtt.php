<?php

return [
    'enabled' => env('MQTT_ENABLED', false),
    'host' => env('MQTT_HOST', ''),
    'port' => env('MQTT_PORT', 8883),
    'client_id' => env('MQTT_CLIENT_ID', 'rebung-pintar-ingestor'),
    'username' => env('MQTT_USERNAME'),
    'password' => env('MQTT_PASSWORD'),
    'tls' => env('MQTT_TLS', true),
    'allow_insecure_local' => env('MQTT_ALLOW_INSECURE_LOCAL', false),
    'tls_verify_peer' => true,
    'tls_verify_peer_name' => true,
    'tls_allow_self_signed' => false,
    'tls_ca_file' => env('MQTT_TLS_CA_FILE'),
    'connect_timeout' => 10,
    'socket_timeout' => 5,
    'subscription_ack_timeout' => 10,
    'keep_alive' => 30,
];
