<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Blynk Cloud credentials
    |--------------------------------------------------------------------------
    |
    | The token is kept on the server only. It is never exposed to the browser;
    | every call to Blynk is proxied through this application.
    |
    */

    'token' => env('BLYNK_TOKEN'),

    'base_url' => rtrim(env('BLYNK_BASE_URL', 'https://blynk.cloud/external/api'), '/'),

    'timeout' => (int) env('BLYNK_TIMEOUT', 8),

    /*
    |--------------------------------------------------------------------------
    | Snapshot cache
    |--------------------------------------------------------------------------
    |
    | Blynk is polled by every visitor. A very short cache keeps the load on
    | Blynk predictable while the dashboard still refreshes every two seconds.
    |
    */

    'cache_seconds' => (int) env('BLYNK_CACHE_SECONDS', 1),

    'device' => env('BLYNK_DEVICE_NAME', 'CharThermCell-01'),

    /*
    |--------------------------------------------------------------------------
    | Datastreams
    |--------------------------------------------------------------------------
    */

    'pins' => [
        'temperature_hot' => 'V0',
        'temperature_cold' => 'V1',
        'delta_temperature' => 'V2',
        'voltage' => 'V3',
        'current' => 'V4',
        'power' => 'V5',
        'status' => 'V6',
    ],

    /*
    |--------------------------------------------------------------------------
    | Writable datastreams
    |--------------------------------------------------------------------------
    |
    | Only the pins listed here can be written to. Keep this list as small as
    | possible: the dashboard is public and writes reach real hardware.
    |
    */

    'controls' => [
        'buzzer' => 'V7',
        'calibrate' => 'V8',
    ],

    /*
    |--------------------------------------------------------------------------
    | Control availability
    |--------------------------------------------------------------------------
    |
    | Controls are disabled when the Blynk product does not expose the write
    | datastreams yet. Set BLYNK_CONTROLS_ENABLED=true once V7/V8 exist.
    |
    */

    'controls_enabled' => (bool) env('BLYNK_CONTROLS_ENABLED', true),

    'gauge_max' => 150,

    /*
    |--------------------------------------------------------------------------
    | Operating status
    |--------------------------------------------------------------------------
    */

    'status' => [
        0 => ['key' => 'cold', 'label' => 'Panas tidak cukup — alarm aktif'],
        1 => ['key' => 'safe', 'label' => 'Ambang aman (50–60 °C)'],
        2 => ['key' => 'optimal', 'label' => 'Operasi optimal (> 60 °C)'],
    ],

    'thresholds' => [
        'safe' => 50.0,
        'optimal' => 60.0,
    ],

];
