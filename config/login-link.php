<?php

use App\Http\Controllers\DemoLoginController;
use App\Models\User;

$appUrl = env('APP_URL', 'http://localhost');

return [
    'allowed_environments' => ['local', 'testing', 'staging', 'demo', 'production'],
    'allowed_hosts' => is_string($appUrl) ? [parse_url($appUrl, PHP_URL_HOST)] : [],
    'automatically_create_missing_users' => false,
    'user_model' => User::class,
    'redirect_route_name' => null,
    'login_link_controller' => DemoLoginController::class,
    'middleware' => ['web', 'guest', 'throttle:10,1'],
];
