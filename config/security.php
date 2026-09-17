<?php

return [
    // Exact additional hostnames only, without schemes, ports, or wildcards.
    'allowed_hosts' => array_filter(array_map('trim', explode(',', (string) env('APP_ALLOWED_HOSTS', '')))),
    'vite_origin' => env('CSP_VITE_ORIGIN'),
];
