<?php

// معادل:
// app.enableCors({ origin: '*', methods: ['GET','POST','PATCH','DELETE','PUT','OPTIONS'] })
// در main.ts نسخه NestJS اصلی

return [
    'paths' => ['bookings/*', 'up'],

    'allowed_methods' => ['GET', 'POST', 'PATCH', 'DELETE', 'PUT', 'OPTIONS'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
