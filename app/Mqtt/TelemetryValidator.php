<?php

namespace App\Mqtt;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use JsonException;
use stdClass;

class TelemetryValidator
{
    /** @return array{node_id: string, message_id: string, recorded_at: string, readings: array<string, int|float>} */
    public function validate(string $topic, string $payload, bool $retained): array
    {
        if ($retained || strlen($payload) > 8192 || $payload === '') {
            throw new InvalidTelemetry('Envelope MQTT ditolak.');
        }

        try {
            $message = json_decode($payload, false, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidTelemetry('JSON MQTT tidak valid.');
        }

        $fields = ['schema_version', 'message_id', 'node_id', 'recorded_at', 'readings'];
        if (! $message instanceof stdClass || count(get_object_vars($message)) !== count($fields)) {
            throw new InvalidTelemetry('Skema MQTT tidak valid.');
        }
        foreach ($fields as $field) {
            if (! property_exists($message, $field)) {
                throw new InvalidTelemetry('Field MQTT tidak lengkap.');
            }
        }
        if ($message->schema_version !== 1 || ! is_string($message->message_id) || ! Str::isUuid($message->message_id)) {
            throw new InvalidTelemetry('Versi atau identitas pesan tidak valid.');
        }
        if (! is_string($message->node_id)
            || ! in_array($message->node_id, array_column(config('monitoring.nodes'), 'id'), true)
            || $topic !== 'rebung-pintar/v1/nodes/'.$message->node_id.'/telemetry') {
            throw new InvalidTelemetry('Node atau topik MQTT tidak valid.');
        }
        if (! $message->readings instanceof stdClass || count(get_object_vars($message->readings)) === 0) {
            throw new InvalidTelemetry('Pembacaan MQTT wajib berupa objek berisi sensor.');
        }
        foreach ($message->readings as $sensor => $value) {
            if (! in_array($sensor, array_column(config('monitoring.sensors'), 'id'), true)
                || (! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)) {
                throw new InvalidTelemetry('Sensor atau nilai MQTT tidak valid.');
            }
        }

        return [
            'node_id' => $message->node_id,
            'message_id' => strtolower($message->message_id),
            'recorded_at' => $this->timestamp($message->recorded_at),
            'readings' => get_object_vars($message->readings),
        ];
    }

    private function timestamp(mixed $value): string
    {
        if (! is_string($value) || ! preg_match('/\A(\d{4})-(\d{2})-(\d{2})T([01]\d|2[0-3]):([0-5]\d):([0-5]\d)(?:\.\d{1,6})?(Z|[+-](?:[01]\d|2[0-3]):[0-5]\d)\z/', $value, $parts)
            || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])
            || $parts[7] === '-00:00') {
            throw new InvalidTelemetry('Waktu MQTT wajib RFC3339 dengan zona waktu yang valid.');
        }

        $timestamp = CarbonImmutable::parse($value)->utc();
        if ($timestamp->greaterThan(now('UTC')->addSeconds(60))) {
            throw new InvalidTelemetry('Waktu MQTT melebihi toleransi masa depan.');
        }

        return $timestamp->format('Y-m-d H:i:s');
    }
}
