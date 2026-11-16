<?php

use Spatie\LoginLink\Http\Controllers\LoginLinkController;

$appUrl = env('APP_URL', 'http://localhost');

return [
    'allowed_environments' => ['local', 'testing', 'demo'],
    'allowed_hosts' => is_string($appUrl) ? [parse_url($appUrl, PHP_URL_HOST)] : [],
    'automatically_create_missing_users' => false,
    'user_model' => null,
    'redirect_route_name' => null,
    'login_link_controller' => LoginLinkController::class,
    'middleware' => ['web', 'guest', 'throttle:10,1'],

    'demo_users' => [
        'admin@demo.prism.test' => ['name' => 'Demo Admin', 'balance' => '25', 'is_admin' => true],
        'user@demo.prism.test' => ['name' => 'Demo User', 'balance' => '10', 'is_admin' => false],
        'low-balance@demo.prism.test' => ['name' => 'Demo Low Balance', 'balance' => '0.001', 'is_admin' => false],
        'new-user@demo.prism.test' => ['name' => 'Demo New User', 'balance' => '0', 'is_admin' => false],
    ],
];
