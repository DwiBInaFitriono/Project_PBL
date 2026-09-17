<?php

return [
    'stale_after_seconds' => 300,
    'poll_interval_seconds' => 15,

    'nodes' => [
        ['id' => '1', 'name' => 'Node 1'],
        ['id' => '2', 'name' => 'Node 2'],
    ],

    // Proposed parameters only; confirm these against the actual devices.
    'sensors' => [
        ['id' => 'temperature', 'name' => 'Suhu udara', 'unit' => '°C', 'decimals' => 1],
        ['id' => 'air_humidity', 'name' => 'Kelembapan udara', 'unit' => '% RH', 'decimals' => 1],
        ['id' => 'soil_moisture', 'name' => 'Kelembapan tanah', 'unit' => '%', 'decimals' => 1],
    ],
];
