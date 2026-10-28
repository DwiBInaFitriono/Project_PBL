<?php

return [
    'stale_after_seconds' => 300,
    'poll_interval_seconds' => 15,

    'nodes' => [
        ['id' => '1', 'name' => 'Node Nutrisi'],
        ['id' => '2', 'name' => 'Node Iklim'],
    ],

    'sensors' => [
        ['id' => 'ph', 'name' => 'pH nutrisi', 'unit' => 'pH', 'decimals' => 1, 'node_id' => '1'],
        ['id' => 'water_temperature', 'name' => 'Suhu air', 'unit' => '°C', 'decimals' => 1, 'node_id' => '1'],
        ['id' => 'water_level', 'name' => 'Level tandon', 'unit' => '%', 'decimals' => 0, 'node_id' => '1'],
        ['id' => 'air_temperature', 'name' => 'Suhu udara', 'unit' => '°C', 'decimals' => 1, 'node_id' => '2'],
        ['id' => 'air_humidity', 'name' => 'Kelembapan udara', 'unit' => '% RH', 'decimals' => 0, 'node_id' => '2'],
        ['id' => 'light_intensity', 'name' => 'Intensitas cahaya', 'unit' => 'lux', 'decimals' => 0, 'node_id' => '2'],
        ['id' => 'water_flow', 'name' => 'Debit air', 'unit' => 'L/menit', 'decimals' => 1, 'node_id' => '2'],
    ],

    'plants' => [
        'kangkung' => ['name' => 'Kangkung', 'ph' => [6.0, 7.0]],
        'pakcoy' => ['name' => 'Pakcoy', 'ph' => [5.5, 6.5]],
    ],
];
