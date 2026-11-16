<?php

return [
    'enabled' => env('DEMO_LOGIN_ENABLED', in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)),
];
