<?php

namespace App\Console\Commands;

use App\Mqtt\MqttConfiguration;
use Illuminate\Console\Command;

class MqttCheck extends Command
{
    protected $signature = 'mqtt:check';

    protected $description = 'Memeriksa konfigurasi MQTT secara offline tanpa koneksi atau perubahan database';

    public function handle(MqttConfiguration $configuration): int
    {
        $problems = $configuration->problems();
        foreach ($problems as $problem) {
            $this->error($problem);
        }
        if ($problems !== []) {
            return self::FAILURE;
        }
        $this->info('Konfigurasi MQTT siap. Broker, ACL, perangkat, dan migrasi database belum diverifikasi.');

        return self::SUCCESS;
    }
}
