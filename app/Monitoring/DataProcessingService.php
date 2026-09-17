<?php

namespace App\Monitoring;

class DataProcessingService
{
    /**
     * Hitung Vapor Pressure Deficit (VPD) dalam satuan kiloPascal (kPa).
     * VPD optimal untuk rebung bambu berada di kisaran 0.8 - 1.2 kPa.
     */
    public function calculateVpd(float $temperatureC, float $relativeHumidity): float
    {
        if ($temperatureC <= -50.0 || $temperatureC >= 80.0 || $relativeHumidity < 0.0 || $relativeHumidity > 100.0) {
            return 0.0;
        }

        // Saturated Vapor Pressure (SVP) berdasarkan rumus Tetens
        $svp = 0.61078 * exp((17.27 * $temperatureC) / ($temperatureC + 237.3));

        // Actual Vapor Pressure (AVP)
        $avp = $svp * ($relativeHumidity / 100.0);

        // VPD = SVP - AVP
        return round(max(0.0, $svp - $avp), 2);
    }

    /**
     * Klasifikasikan status VPD untuk panduan iklim mikro rebung bambu.
     */
    public function classifyVpd(float $vpdKpa): array
    {
        if ($vpdKpa < 0.4) {
            return [
                'status' => 'Sangat Rendah',
                'description' => 'Transpirasi terhambat, kelembapan terlalu jenuh (risiko jamur/pembusukan).',
                'color' => '#6A7FC0',
            ];
        }

        if ($vpdKpa <= 1.2) {
            return [
                'status' => 'Optimal',
                'description' => 'Kondisi iklim mikro optimal untuk laju fotosintesis dan pertumbuhan rebung.',
                'color' => '#2E7D32',
            ];
        }

        if ($vpdKpa <= 1.6) {
            return [
                'status' => 'Waspada Tinggi',
                'description' => 'Udara kering, laju penguapan air mulai meningkat.',
                'color' => '#F7B700',
            ];
        }

        return [
            'status' => 'Kritis Kering',
            'description' => 'Stres air parah, stomata menutup. Perlu peningkatan kelembapan segera.',
            'color' => '#C62828',
        ];
    }

    /**
     * Evaluasi kondisi kelembapan tanah dan rekomendasi irigasi.
     */
    public function evaluateSoilMoisture(float $soilMoisturePercent): array
    {
        if ($soilMoisturePercent < 35.0) {
            return [
                'condition' => 'Kering',
                'action_needed' => true,
                'recommendation' => 'Aktifkan pompa irigasi untuk menyiram media perakaran.',
            ];
        }

        if ($soilMoisturePercent <= 75.0) {
            return [
                'condition' => 'Ideal',
                'action_needed' => false,
                'recommendation' => 'Kelembapan tanah dalam kisaran ideal untuk penyerapan nutrisi rebung.',
            ];
        }

        return [
            'condition' => 'Terlalu Basah / Jenuh',
            'action_needed' => false,
            'recommendation' => 'Hentikan penyiraman, periksa drainase tanah untuk mencegah busuk tunas.',
        ];
    }

    /**
     * Ringkas kumpulan data titik numerik (Moving Average, Min, Max, Standar Deviasi).
     *
     * @param  array<int, float>  $values
     * @return array<string, float|null>
     */
    public function summarize(array $values): array
    {
        $filtered = array_values(array_filter($values, fn ($v): bool => is_numeric($v)));
        $count = count($filtered);

        if ($count === 0) {
            return [
                'count' => 0.0,
                'average' => null,
                'min' => null,
                'max' => null,
            ];
        }

        $avg = array_sum($filtered) / $count;

        return [
            'count' => (float) $count,
            'average' => round($avg, 2),
            'min' => round(min($filtered), 2),
            'max' => round(max($filtered), 2),
        ];
    }
}
