<?php

namespace App\Console\Commands;

use App\Mqtt\MqttConfiguration;
use App\Mqtt\TelemetrySubscriber;
use Illuminate\Console\Command;

class MqttSubscribe extends Command
{
    protected $signature = 'mqtt:subscribe {--once : Berhenti setelah satu pesan} {--timeout=0 : Batas loop dalam detik, 0 tanpa batas}';

    protected $description = 'Menerima telemetri MQTT yang telah dikonfigurasi secara eksplisit';

    public function handle(MqttConfiguration $configuration): int
    {
        $problems = $configuration->problems();
        foreach ($problems as $problem) {
            $this->error($problem);
        }
        if ($problems !== []) {
            return self::FAILURE;
        }
        $timeout = filter_var($this->option('timeout'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 86400]]);
        if ($timeout === false) {
            $this->error('Timeout MQTT tidak valid; gunakan 0 sampai 86400 detik.');

            return self::FAILURE;
        }
        if ($this->option('once') && $timeout === 0) {
            $timeout = 30;
        }
        try {
            $counts = app(TelemetrySubscriber::class)->run((bool) $this->option('once'), $timeout);
        } catch (\Throwable) {
            $this->error('MQTT gagal. Periksa broker, izin, dan database; restart melalui supervisor.');

            return self::FAILURE;
        }
        $this->info('MQTT: diterima='.$counts['accepted'].', duplikat='.$counts['duplicate'].', ditolak='.$counts['rejected'].'.');

        return $this->option('once') && $counts['accepted'] + $counts['duplicate'] === 0 ? self::FAILURE : self::SUCCESS;
    }
}
