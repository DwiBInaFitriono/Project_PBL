<?php

namespace App\Mqtt;

use App\Models\SensorReading;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class TelemetryIngestor
{
    public function __construct(private TelemetryValidator $validator) {}

    public function ingest(string $topic, string $payload, bool $retained = false): string
    {
        $message = $this->validator->validate($topic, $payload, $retained);

        $identity = ['node_id' => $message['node_id'], 'message_id' => $message['message_id']];
        if (DB::table('mqtt_messages')->where($identity)->exists()) {
            return 'duplicate';
        }

        try {
            return DB::transaction(function () use ($message): string {
                DB::table('mqtt_messages')->insert([
                    'node_id' => $message['node_id'],
                    'message_id' => $message['message_id'],
                    'received_at' => now('UTC'),
                ]);

                foreach ($message['readings'] as $sensor => $value) {
                    SensorReading::create([
                        'node_id' => $message['node_id'],
                        'sensor_id' => $sensor,
                        'value' => $value,
                        'recorded_at' => $message['recorded_at'],
                    ]);
                }

                return 'accepted';
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (DB::table('mqtt_messages')->where($identity)->exists()) {
                return 'duplicate';
            }

            throw $exception;
        }
    }
}
