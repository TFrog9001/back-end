<?php

return [

    'default' => env('BROADCAST_DRIVER', 'null'),

    'connections' => [
        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'host' => env('LARAVEL_WEBSOCKETS_HOST', '127.0.0.1'),
                'port' => env('LARAVEL_WEBSOCKETS_PORT', 6001),
                'cluster' => env('PUSHER_APP_CLUSTER', 'ap1'),
                'useTLS' => env('PUSHER_SCHEME') === 'https',  // Cập nhật `useTLS` theo scheme
                'scheme' => env('PUSHER_SCHEME', 'http'),
                'encrypted' => false,
            ],
        ],

        'ably' => [
            'driver' => 'ably',
            'key' => env('ABLY_KEY'),
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],
    ],

];
