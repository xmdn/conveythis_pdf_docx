<?php

declare(strict_types=1);

return [
    'host' => env('RABBITMQ_HOST', '127.0.0.1'),
    'port' => (int) env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER', 'guest'),
    'password' => env('RABBITMQ_PASSWORD', 'guest'),
    'vhost' => env('RABBITMQ_VHOST', '/'),
    'exchange' => env('RABBITMQ_EXCHANGE', 'file.events'),
    'queue' => env('RABBITMQ_QUEUE', 'email.notifications'),
    'routing_key' => env('RABBITMQ_ROUTING_KEY', 'file.deleted'),
    'heartbeat' => (int) env('RABBITMQ_HEARTBEAT', 30),
    'connection_timeout' => (float) env('RABBITMQ_CONNECTION_TIMEOUT', 3),
    'read_write_timeout' => (float) env('RABBITMQ_READ_WRITE_TIMEOUT', 5),
    'confirm_timeout' => (float) env('RABBITMQ_CONFIRM_TIMEOUT', 5),
];
